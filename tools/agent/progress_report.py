#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
from collections import defaultdict
from datetime import datetime, timezone
import argparse
import json
import re
import yaml

ROOT = Path(__file__).resolve().parents[2]
WEIGHTS = {'BACKLOG': 0.0, 'READY': 0.0, 'BLOCKED': 0.0, 'IN_PROGRESS': 0.3, 'REVIEW': 0.7, 'VERIFIED': 0.9, 'DONE': 1.0}


def natural_milestone(value: str):
    match = re.fullmatch(r'W(\d+)', value)
    return (0, int(match.group(1))) if match else (1, value)


def build_report() -> dict:
    backlog = yaml.safe_load((ROOT / 'docs/tasks/backlog.yaml').read_text(encoding='utf-8'))
    status = yaml.safe_load((ROOT / 'docs/status/task-status.yaml').read_text(encoding='utf-8'))['tasks']
    tasks = {item['id']: item for item in backlog['tasks']}
    by_milestone = defaultdict(lambda: {'total': 0, 'earned': 0.0, 'tasks': 0, 'states': defaultdict(int)})
    blocked = []
    for task_id, record in status.items():
        task = tasks[task_id]
        milestone = task['milestone']
        points = int(task['points'])
        state = record['status']
        row = by_milestone[milestone]
        row['total'] += points
        row['earned'] += points * WEIGHTS[state]
        row['tasks'] += 1
        row['states'][state] += 1
        if state == 'BLOCKED':
            blocked.append({'id': task_id, 'title': task['title'], 'blockers': record.get('blockers', [])})
    total = sum(row['total'] for row in by_milestone.values())
    earned = sum(row['earned'] for row in by_milestone.values())
    milestones = []
    for milestone in sorted(by_milestone, key=natural_milestone):
        row = by_milestone[milestone]
        milestones.append({
            'milestone': milestone,
            'total_points': row['total'],
            'earned_points': round(row['earned'], 1),
            'percent': round(row['earned'] / row['total'] * 100, 1) if row['total'] else 0.0,
            'task_count': row['tasks'],
            'states': dict(row['states']),
        })
    return {
        'generated_at': datetime.now(timezone.utc).isoformat(),
        'total_points': total,
        'earned_points': round(earned, 1),
        'percent': round(earned / total * 100, 1) if total else 0.0,
        'blocked': blocked,
        'milestones': milestones,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--json', action='store_true')
    parser.add_argument('--output')
    args = parser.parse_args()
    report = build_report()
    if args.output:
        Path(args.output).write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return
    print(f"Weighted progress: {report['earned_points']:.1f}/{report['total_points']} points ({report['percent']:.1f}%)")
    for row in report['milestones']:
        print(f"{row['milestone']}: {row['earned_points']:.1f}/{row['total_points']} ({row['percent']:.1f}%) {row['states']}")
    if report['blocked']:
        print('Blocked:')
        for item in report['blocked']:
            print(f"- {item['id']} {item['title']}: {item['blockers']}")


if __name__ == '__main__':
    main()
