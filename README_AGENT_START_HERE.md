# AI Agent 实施包：START HERE

本包不是最终业务代码，而是复制到新仓库根目录的执行治理层。

1. 人工阅读 `docs/EXECUTION_PLAN.md`，确认工程版本、Gate 0、人工付款边界和对象存储选择。
2. 从 Roots Bedrock 1.31.0 初始化新仓库；不要 fork 参考网站、WordPress 或 EDD。
3. 把本执行包复制到仓库根目录，运行：

   ```bash
   python tools/agent/validate_docs.py
   python tools/agent/taskctl.py summary
   python tools/agent/taskctl.py ready
   ```

4. 只领取 `SR-001`。不要为了“先看到页面”跳过 CI、技术 Spike、契约、迁移和权限底座。
5. Codex 读取 `AGENTS.md`；Claude Code 读取 `CLAUDE.md` 后继续读取 `AGENTS.md`。
6. `backlog.yaml` 定义任务；`task-status.yaml` 记录状态；`agent-locks.yaml` 防止冲突；`docs/evidence/` 保存可复核证据。
7. 每个任务一个分支/PR。高风险支付、权益、下载、迁移、安全任务必须独立复核。

首个可展示目标不是首页，而是：空仓库可一键安装、CI 可重复、EDD 行为已验证、插件边界和迁移框架稳定。
