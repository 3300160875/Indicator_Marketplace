# Indicator Marketplace Project: START HERE

本目录是实际项目 Git 仓库。父级 workspace 的 `docs/agent-guide/` 和 `docs/product-spec/` 保存原始执行指南与产品资料；本仓库只保存项目代码、治理文档、任务卡和可复核证据。

1. 在本目录运行开发命令：

   ```bash
   pwd
   python tools/agent/validate_docs.py
   python tools/agent/taskctl.py summary
   python tools/agent/taskctl.py ready
   ```

2. Codex 读取 `AGENTS.md`；Claude Code 读取 `CLAUDE.md` 后继续读取 `AGENTS.md`。
3. `docs/tasks/backlog.yaml` 定义任务；`docs/status/task-status.yaml` 记录状态；`docs/status/agent-locks.yaml` 防止冲突；`docs/evidence/` 保存可复核证据。
4. 每个任务一个分支/PR。高风险支付、权益、下载、迁移、安全任务必须独立复核。
5. 不要为了“先看到页面”跳过 CI、技术 Spike、契约、迁移和权限底座。

当前真实代码目录为 `packages/**`，运行入口和 WordPress Web 根目录在 `web/**`。仓库布局说明见 `docs/architecture/REPOSITORY_LAYOUT.md`。
