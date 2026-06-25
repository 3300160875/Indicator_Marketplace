
# Agent progress tools

```bash
python -m pip install -r requirements-agent.txt
python tools/agent/validate_docs.py
python tools/agent/taskctl.py ready
python tools/agent/taskctl.py claim SR-001 --agent codex-1 --branch docs/SR-001-baseline
python tools/agent/taskctl.py set-status SR-001 REVIEW --evidence "PR #1; CI URL"
python tools/agent/progress_report.py
```

`taskctl.py` 只管理仓库内状态和锁，不替代 GitHub 分支保护或人工评审。
