#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
from datetime import datetime, timezone, timedelta
import argparse
import fnmatch
import os
import subprocess
import sys
import tempfile
import yaml

ROOT = Path(__file__).resolve().parents[2]
BACKLOG = ROOT / 'docs/tasks/backlog.yaml'
STATUS = ROOT / 'docs/status/task-status.yaml'
LOCKS = ROOT / 'docs/status/agent-locks.yaml'
TERMINAL_DEP_STATES = {'VERIFIED', 'DONE'}
TRANSITIONS = {
    'BACKLOG': {'READY'},
    'READY': {'IN_PROGRESS', 'BLOCKED'},
    'IN_PROGRESS': {'REVIEW', 'BLOCKED'},
    'BLOCKED': {'READY'},
    'REVIEW': {'IN_PROGRESS', 'VERIFIED', 'BLOCKED'},
    'VERIFIED': {'DONE', 'IN_PROGRESS'},
    'DONE': set(),
}
EVIDENCE_REQUIRED = {'REVIEW', 'VERIFIED', 'DONE'}


def now() -> datetime:
    return datetime.now(timezone.utc)


def iso(value: datetime | None = None) -> str:
    return (value or now()).isoformat()


def load(path: Path):
    return yaml.safe_load(path.read_text(encoding='utf-8'))


def atomic_write(path: Path, data) -> None:
    text = yaml.safe_dump(data, allow_unicode=True, sort_keys=False)
    fd, temp_name = tempfile.mkstemp(prefix=path.name + '.', dir=path.parent)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as handle:
            handle.write(text)
        os.replace(temp_name, path)
    finally:
        if os.path.exists(temp_name):
            os.unlink(temp_name)


def task_map():
    backlog = load(BACKLOG)
    return backlog, {task['id']: task for task in backlog['tasks']}


def parse_iso(value: str) -> datetime:
    parsed = datetime.fromisoformat(value)
    return parsed if parsed.tzinfo else parsed.replace(tzinfo=timezone.utc)


def prune_expired_locks(locks: dict) -> list[dict]:
    active: list[dict] = []
    expired: list[dict] = []
    current = now()
    for lock in locks.get('locks', []):
        try:
            expiry = parse_iso(lock['expires_at'])
        except Exception:
            # Malformed locks are preserved for manual inspection rather than silently discarded.
            active.append(lock)
            continue
        (active if expiry > current else expired).append(lock)
    locks['locks'] = active
    return expired


def path_conflict(left: str, right: str) -> bool:
    a = left.rstrip('/**')
    b = right.rstrip('/**')
    return (
        a.startswith(b)
        or b.startswith(a)
        or fnmatch.fnmatch(a, right)
        or fnmatch.fnmatch(b, left)
    )


def dependencies_ready(task: dict, statuses: dict) -> tuple[bool, list[str]]:
    bad = [dep for dep in task.get('deps', []) if statuses[dep]['status'] not in TERMINAL_DEP_STATES]
    return not bad, bad


def ensure_agent_owns(record: dict, agent: str | None) -> None:
    owner = record.get('owner')
    if owner and agent and owner != agent:
        raise SystemExit(f'task is owned by {owner}, not {agent}')


def cmd_validate(_args) -> None:
    proc = subprocess.run([sys.executable, str(ROOT / 'tools/agent/validate_docs.py')], check=False)
    raise SystemExit(proc.returncode)


def cmd_summary(_args) -> None:
    _, tasks = task_map()
    status_doc = load(STATUS)
    counts: dict[str, int] = {}
    points: dict[str, int] = {}
    for task_id, record in status_doc['tasks'].items():
        state = record['status']
        counts[state] = counts.get(state, 0) + 1
        points[state] = points.get(state, 0) + int(tasks[task_id]['points'])
    total = sum(int(task['points']) for task in tasks.values())
    print('Tasks:', counts)
    print('Points:', points, '/', total)


def cmd_ready(_args) -> None:
    _, tasks = task_map()
    statuses = load(STATUS)['tasks']
    for task_id, task in tasks.items():
        record = statuses[task_id]
        deps_ok, _ = dependencies_ready(task, statuses)
        if record['status'] in {'BACKLOG', 'READY'} and deps_ok:
            print(task_id, task['title'])


def cmd_refresh_ready(_args) -> None:
    _, tasks = task_map()
    status_doc = load(STATUS)
    changed: list[str] = []
    for task_id, task in tasks.items():
        record = status_doc['tasks'][task_id]
        deps_ok, _ = dependencies_ready(task, status_doc['tasks'])
        if record['status'] == 'BACKLOG' and deps_ok:
            record['status'] = 'READY'
            record['updated_at'] = iso()
            changed.append(task_id)
    status_doc['updated_at'] = iso()
    atomic_write(STATUS, status_doc)
    print('READY:', ', '.join(changed) if changed else 'no changes')


def cmd_claim(args) -> None:
    _, tasks = task_map()
    status_doc = load(STATUS)
    locks = load(LOCKS)
    prune_expired_locks(locks)

    if args.task not in tasks:
        raise SystemExit('unknown task')
    if not args.branch:
        raise SystemExit('--branch is required')

    task = tasks[args.task]
    record = status_doc['tasks'][args.task]
    if record['status'] not in {'BACKLOG', 'READY'}:
        raise SystemExit(f'task status is {record["status"]}')
    deps_ok, bad = dependencies_ready(task, status_doc['tasks'])
    if not deps_ok:
        raise SystemExit('dependencies not VERIFIED/DONE: ' + ', '.join(bad))

    for lock in locks.get('locks', []):
        if lock.get('agent') == args.agent:
            raise SystemExit(f'agent already owns {lock.get("task_id")}; WIP=1')
        if lock.get('task_id') == args.task:
            raise SystemExit('task already locked')
        for target_path in task.get('paths', []):
            for locked_path in lock.get('paths', []):
                if path_conflict(target_path, locked_path):
                    raise SystemExit(
                        f'path conflict with {lock.get("task_id")}: {target_path} <> {locked_path}'
                    )

    started = now()
    expiry = started + timedelta(hours=args.ttl_hours)
    locks.setdefault('locks', []).append({
        'task_id': args.task,
        'agent': args.agent,
        'paths': task.get('paths', []),
        'claimed_at': iso(started),
        'heartbeat_at': iso(started),
        'expires_at': iso(expiry),
        'branch': args.branch,
    })
    record.update({
        'status': 'IN_PROGRESS',
        'owner': args.agent,
        'branch': args.branch,
        'claimed_at': iso(started),
        'updated_at': iso(started),
    })
    status_doc['updated_at'] = iso(started)
    atomic_write(LOCKS, locks)
    atomic_write(STATUS, status_doc)
    print(f'CLAIMED {args.task} by {args.agent} until {iso(expiry)}')


def cmd_heartbeat(args) -> None:
    locks = load(LOCKS)
    prune_expired_locks(locks)
    target = None
    for lock in locks.get('locks', []):
        if lock.get('task_id') == args.task:
            target = lock
            break
    if target is None:
        raise SystemExit('active lock not found')
    if target.get('agent') != args.agent:
        raise SystemExit(f'lock belongs to {target.get("agent")}')
    current = now()
    target['heartbeat_at'] = iso(current)
    target['expires_at'] = iso(current + timedelta(hours=args.ttl_hours))
    atomic_write(LOCKS, locks)
    print(f'HEARTBEAT {args.task} until {target["expires_at"]}')


def cmd_set_status(args) -> None:
    backlog, tasks = task_map()
    status_doc = load(STATUS)
    locks = load(LOCKS)
    prune_expired_locks(locks)

    if args.task not in tasks:
        raise SystemExit('unknown task')
    if args.status not in set(backlog['task_states']):
        raise SystemExit('invalid status')

    record = status_doc['tasks'][args.task]
    ensure_agent_owns(record, args.agent)
    current = record['status']
    if args.status == current:
        print(args.task, current, '(unchanged)')
        return
    if args.status not in TRANSITIONS.get(current, set()):
        raise SystemExit(f'invalid transition: {current} -> {args.status}')

    if args.status in EVIDENCE_REQUIRED and not args.evidence:
        raise SystemExit(f'--evidence is required for {args.status}')
    if args.status == 'BLOCKED' and not args.blocker:
        raise SystemExit('--blocker is required for BLOCKED')
    if args.status == 'VERIFIED' and args.agent and record.get('owner') == args.agent:
        raise SystemExit('VERIFIED must be set by an independent reviewer/QA agent')

    record['status'] = args.status
    record['updated_at'] = iso()
    if args.evidence:
        record.setdefault('evidence', []).append(args.evidence)
    if args.status == 'BLOCKED':
        record.setdefault('blockers', []).append(args.blocker)
    elif args.status == 'READY':
        record['blockers'] = []

    if args.status in {'REVIEW', 'VERIFIED', 'DONE', 'BLOCKED'}:
        locks['locks'] = [lock for lock in locks.get('locks', []) if lock.get('task_id') != args.task]
    if args.status == 'DONE':
        record['completed_at'] = iso()

    status_doc['updated_at'] = iso()
    atomic_write(STATUS, status_doc)
    atomic_write(LOCKS, locks)
    print(args.task, args.status)


def cmd_release(args) -> None:
    locks = load(LOCKS)
    prune_expired_locks(locks)
    before = len(locks.get('locks', []))
    retained = []
    for lock in locks.get('locks', []):
        if lock.get('task_id') == args.task:
            if args.agent and lock.get('agent') != args.agent:
                raise SystemExit(f'lock belongs to {lock.get("agent")}')
            continue
        retained.append(lock)
    locks['locks'] = retained
    atomic_write(LOCKS, locks)
    print('released', before - len(retained))


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description='Atomic task status and path-lock manager')
    sub = parser.add_subparsers(dest='cmd', required=True)
    sub.add_parser('validate').set_defaults(fn=cmd_validate)
    sub.add_parser('summary').set_defaults(fn=cmd_summary)
    sub.add_parser('ready').set_defaults(fn=cmd_ready)
    sub.add_parser('refresh-ready').set_defaults(fn=cmd_refresh_ready)

    claim = sub.add_parser('claim')
    claim.add_argument('task')
    claim.add_argument('--agent', required=True)
    claim.add_argument('--branch', required=True)
    claim.add_argument('--ttl-hours', type=int, default=4)
    claim.set_defaults(fn=cmd_claim)

    heartbeat = sub.add_parser('heartbeat')
    heartbeat.add_argument('task')
    heartbeat.add_argument('--agent', required=True)
    heartbeat.add_argument('--ttl-hours', type=int, default=4)
    heartbeat.set_defaults(fn=cmd_heartbeat)

    status = sub.add_parser('set-status')
    status.add_argument('task')
    status.add_argument('status')
    status.add_argument('--agent')
    status.add_argument('--evidence')
    status.add_argument('--blocker')
    status.set_defaults(fn=cmd_set_status)

    release = sub.add_parser('release')
    release.add_argument('task')
    release.add_argument('--agent')
    release.set_defaults(fn=cmd_release)
    return parser


if __name__ == '__main__':
    parsed = build_parser().parse_args()
    parsed.fn(parsed)
