#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
from datetime import datetime, timezone
import fnmatch
import json
import re
import sys
import yaml

ROOT = Path(__file__).resolve().parents[2]
BACKLOG = ROOT / 'docs/tasks/backlog.yaml'
STATUS = ROOT / 'docs/status/task-status.yaml'
LOCKS = ROOT / 'docs/status/agent-locks.yaml'
PLAN = ROOT / 'docs/EXECUTION_PLAN.md'
REQUIRED_CARD_SECTIONS = [
    '## Objective',
    '## Allowed paths',
    '## Preconditions / Definition of Ready',
    '## Reference implementation sequence',
    '## Acceptance criteria',
    '## Required commands',
    '## Required evidence',
    '## Definition of Done',
    '## Completion report / handoff',
]


def load_yaml(path: Path, errors: list[str]):
    try:
        return yaml.safe_load(path.read_text(encoding='utf-8'))
    except Exception as exc:
        errors.append(f'{path.relative_to(ROOT)}: invalid YAML: {exc}')
        return None


def path_conflict(left: str, right: str) -> bool:
    a = left.rstrip('/**')
    b = right.rstrip('/**')
    return a.startswith(b) or b.startswith(a) or fnmatch.fnmatch(a, right) or fnmatch.fnmatch(b, left)


def main() -> int:
    errors: list[str] = []
    backlog = load_yaml(BACKLOG, errors)
    status_doc = load_yaml(STATUS, errors)
    locks_doc = load_yaml(LOCKS, errors)
    openapi = load_yaml(ROOT / 'docs/contracts/openapi.yaml', errors)
    data_dictionary = load_yaml(ROOT / 'docs/contracts/data-dictionary.yaml', errors)
    if errors:
        print('\n'.join('ERROR: ' + item for item in errors))
        return 1

    valid_states = set(backlog.get('task_states', []))
    task_list = backlog.get('tasks', [])
    task_ids = [task.get('id') for task in task_list]
    if len(set(task_ids)) != len(task_ids):
        errors.append('duplicate task id')
    tasks = {task['id']: task for task in task_list}
    statuses = status_doc.get('tasks', {})

    if set(tasks) != set(statuses):
        errors.append('task-status ids differ from backlog ids')

    for task_id, task in tasks.items():
        for required in ('milestone', 'epic', 'module', 'title', 'points', 'deps', 'paths', 'acceptance'):
            if required not in task:
                errors.append(f'{task_id}: missing field {required}')
        for dependency in task.get('deps', []):
            if dependency not in tasks:
                errors.append(f'{task_id}: unknown dependency {dependency}')
            if dependency == task_id:
                errors.append(f'{task_id}: self dependency')
        if int(task.get('points', 0)) <= 0:
            errors.append(f'{task_id}: points must be positive')
        if not task.get('paths'):
            errors.append(f'{task_id}: allowed paths missing')
        if not task.get('acceptance'):
            errors.append(f'{task_id}: acceptance criteria missing')
        card_path = ROOT / 'docs/tasks' / f'{task_id}.md'
        if not card_path.exists():
            errors.append(f'{task_id}: task card missing')
        else:
            card = card_path.read_text(encoding='utf-8')
            for section in REQUIRED_CARD_SECTIONS:
                if section not in card:
                    errors.append(f'{task_id}: task card missing section {section}')
            if f'# {task_id} ' not in card:
                errors.append(f'{task_id}: task card title mismatch')

    for task_id, record in statuses.items():
        state = record.get('status')
        if state not in valid_states:
            errors.append(f'{task_id}: invalid status {state}')
        if state == 'IN_PROGRESS' and not record.get('owner'):
            errors.append(f'{task_id}: IN_PROGRESS without owner')
        if state in {'REVIEW', 'VERIFIED', 'DONE'} and not record.get('evidence'):
            errors.append(f'{task_id}: {state} without evidence')
        for evidence in record.get('evidence', []):
            if isinstance(evidence, str) and evidence.startswith('/'):
                errors.append(f'{task_id}: evidence path must be relative or URL: {evidence}')

    # Dependency cycle and invalid completed-before-dependency checks.
    visiting: set[str] = set()
    visited: set[str] = set()

    def visit(task_id: str) -> None:
        if task_id in visiting:
            errors.append(f'dependency cycle at {task_id}')
            return
        if task_id in visited:
            return
        visiting.add(task_id)
        for dependency in tasks[task_id].get('deps', []):
            visit(dependency)
        visiting.remove(task_id)
        visited.add(task_id)

    for task_id in tasks:
        visit(task_id)

    terminal = {'VERIFIED', 'DONE'}
    for task_id, record in statuses.items():
        if record.get('status') in {'IN_PROGRESS', 'REVIEW', 'VERIFIED', 'DONE'}:
            for dependency in tasks[task_id].get('deps', []):
                if statuses[dependency].get('status') not in terminal:
                    errors.append(f'{task_id}: active/completed while dependency {dependency} is not VERIFIED/DONE')

    # Active locks.
    locks = locks_doc.get('locks', [])
    seen_tasks: set[str] = set()
    seen_agents: set[str] = set()
    current = datetime.now(timezone.utc)
    for index, lock in enumerate(locks):
        task_id = lock.get('task_id')
        agent = lock.get('agent')
        if task_id in seen_tasks:
            errors.append(f'duplicate active task lock {task_id}')
        seen_tasks.add(task_id)
        if agent in seen_agents:
            errors.append(f'agent {agent} has more than one active lock')
        seen_agents.add(agent)
        if task_id not in tasks:
            errors.append(f'lock references unknown task {task_id}')
            continue
        if statuses[task_id].get('status') != 'IN_PROGRESS':
            errors.append(f'{task_id}: active lock but status is {statuses[task_id].get("status")}')
        if statuses[task_id].get('owner') != agent:
            errors.append(f'{task_id}: lock owner and task owner differ')
        try:
            expiry = datetime.fromisoformat(lock['expires_at'])
            if expiry.tzinfo is None:
                expiry = expiry.replace(tzinfo=timezone.utc)
            if expiry <= current:
                errors.append(f'{task_id}: expired lock should be pruned')
        except Exception:
            errors.append(f'{task_id}: invalid lock expires_at')
        for other in locks[index + 1:]:
            for left in lock.get('paths', []):
                for right in other.get('paths', []):
                    if path_conflict(left, right):
                        errors.append(f'path lock conflict: {task_id}:{left} <> {other.get("task_id")}:{right}')

    plan = PLAN.read_text(encoding='utf-8')
    for token in ('{md_table(', "{code('", '/mnt/data/agent_exec_build/'):
        if token in plan:
            errors.append(f'execution plan contains unresolved/generated token: {token}')
    for heading in ('## 1. 基础项目', '## 6. 字段', '## 12. 14 周执行路线', '## 18. Agent 可直接落地的代码蓝图'):
        if heading not in plan:
            errors.append(f'execution plan missing heading {heading}')

    if not isinstance(openapi, dict) or openapi.get('openapi') != '3.1.0':
        errors.append('OpenAPI must be a parseable 3.1.0 document')
    if not openapi.get('paths') or len(openapi['paths']) < 10:
        errors.append('OpenAPI path coverage is unexpectedly small')
    if not isinstance(data_dictionary, dict) or not data_dictionary.get('invariants'):
        errors.append('data dictionary invariants missing')
    schema_path = ROOT / 'docs/contracts/schema.sql'
    schema = schema_path.read_text(encoding='utf-8')
    for table in ('sr_schema_migrations', 'sr_idempotency_keys', 'sr_entitlements', 'sr_download_tokens', 'sr_audit_logs'):
        if f'wp_{table}' not in schema:
            errors.append(f'schema missing {table}')

    if errors:
        print('\n'.join('ERROR: ' + item for item in errors))
        return 1
    print(f'OK: {len(tasks)} tasks, {len(locks)} locks, no cycles, task cards/contracts consistent')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
