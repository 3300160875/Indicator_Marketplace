# Project Status

> YAML 文件 `project-status.yaml` 是机器可读事实来源。本文件用于周报和人工阅读。

## 当前阶段

- Milestone: W1
- CI: configured and required on `main`
- Next safe task: SR-009 创建五个插件骨架与激活依赖
- Gate 0: complete

## 本周完成

- SR-001 文档基线已通过独立评审并标记 VERIFIED。
- SR-002 Bedrock / WordPress / EDD Composer 基线已通过独立评审并标记 VERIFIED。
- SR-003/SR-004 本地 Docker 环境与 Make 封装已完成。
- SR-005 CI 最小门禁已合入 `main`，GitHub Actions 的 PHP/Frontend gate 已通过并设为分支保护必需检查。
- SR-006 已补齐并合入 `main`：EDD runtime spike、ADR-001～006、完成订单 hook、重复完成、整单退款和订单项部分退款均有证据。
- SR-007 已合入 `main`：`packages/sr-contracts` 共享契约包、值对象、DTO、接口、错误类型与纯 PHP 测试已完成。
- SR-008 已进入 REVIEW：`packages/sr-platform-bootstrap` MU Plugin 启动层、依赖检查、服务容器、Provider 注册、Feature Flags 与依赖缺失后台阻断已完成。
- 工作区已整理：真实项目仓库位于 `Indicator_Marketplace/project/`，原始执行指南和产品资料位于父级 `docs/`。

## 阻塞

- 暂无 W1 阶段阻塞。

## 下一步

1. SR-009 创建五个插件骨架与激活依赖。
2. 进入 EDD adapter / entitlement 边界实现任务。
3. 将 SR-006 中的 MariaDB/MinIO probe 在对应实现任务中提升为可重复脚本或自动化测试。
