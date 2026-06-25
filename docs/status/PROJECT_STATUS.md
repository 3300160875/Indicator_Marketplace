# Project Status

> YAML 文件 `project-status.yaml` 是机器可读事实来源。本文件用于周报和人工阅读。

## 当前阶段

- Milestone: W3
- CI: configured and required on `main`
- Next safe task: SR-015 实现资源编辑工作台与发布检查
- Gate 0: complete

## 本周完成

- SR-001 文档基线已通过独立评审并标记 VERIFIED。
- SR-002 Bedrock / WordPress / EDD Composer 基线已通过独立评审并标记 VERIFIED。
- SR-003/SR-004 本地 Docker 环境与 Make 封装已完成。
- SR-005 CI 最小门禁已合入 `main`，GitHub Actions 的 PHP/Frontend gate 已通过并设为分支保护必需检查。
- SR-006 已补齐并合入 `main`：EDD runtime spike、ADR-001～006、完成订单 hook、重复完成、整单退款和订单项部分退款均有证据。
- SR-007 已合入 `main`：`packages/sr-contracts` 共享契约包、值对象、DTO、接口、错误类型与纯 PHP 测试已完成。
- SR-008 已通过远端 CI 并标记 VERIFIED：`packages/sr-platform-bootstrap` MU Plugin 启动层、依赖检查、服务容器、Provider 注册、Feature Flags 与依赖缺失后台阻断已完成。
- SR-009 已通过远端 CI 并标记 VERIFIED：五个一方普通插件骨架、入口、Composer、命名空间、运行时依赖守卫与包级测试已完成。
- SR-010 已通过远端 CI 并标记 VERIFIED：`stock-resource-theme` 服务端渲染主题骨架、`theme.json`、基础模板、CSS/TS 资产与主题内验证脚本已完成。
- SR-011 已通过远端 CI 并标记 VERIFIED：迁移接口、迁移记录、仓库抽象、schema 迁移定义、Runner、事务能力探测与 WP-CLI 命令类已完成。
- SR-012 已通过远端 CI 并标记 VERIFIED：Request ID、REST header middleware、结构化日志、默认脱敏与 AuditService 接口已完成。
- SR-013 已通过远端 CI 并标记 VERIFIED：资源分类法定义、受控词表、REST term schema 与被引用词表删除保护已完成。
- runtime-wiring 已补齐：`sr-core` 启动入口现在在依赖满足时接入 taxonomy `init` 注册、REST `X-Request-ID` header filter 与 WP-CLI migration 命令注册。
- SR-014 已通过远端 CI 并标记 VERIFIED：EDD Download 资源元数据 Schema 定义层、23 个字段、sanitize/auth callback、REST 公开边界与审查修复均已完成。
- SR-015 正在推进：已建立资源编辑字段分区、发布 Gate 与高风险修改审计支持层，待评审、CI 与合入。
- 工作区已整理：真实项目仓库位于 `Indicator_Marketplace/project/`，原始执行指南和产品资料位于父级 `docs/`。

## 阻塞

- 暂无 W1 阶段阻塞。

## 下一步

1. 完成 SR-015 评审、CI 与合入。
2. SR-016 创建 `sr_resource_versions` 表与仓储。
3. SR-021 实现设计令牌与基础组件。
