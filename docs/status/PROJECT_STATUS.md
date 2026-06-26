# Project Status

> YAML 文件 `project-status.yaml` 是机器可读事实来源。本文件用于周报和人工阅读。

## 当前阶段

- Milestone: W5
- CI: configured and required on `main`
- Next safe task: SR-028 实现 StorageService 接口与 MinIO 适配器
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
- SR-015 已通过远端 CI 并标记 VERIFIED：资源编辑字段分区、发布 Gate、高风险修改审计支持层与审查修复均已完成。
- SR-016 已通过远端 CI 并标记 VERIFIED：版本表迁移定义、版本状态枚举、可重试阶段、仓储契约和 current 激活事务锁支持层均已完成。
- SR-017 已通过远端 CI 并标记 VERIFIED：ResourceService、ResourceView 与 VersionView 已完成，公开 DTO 会阻断未发布/下架资源并排除 storage_key/internal notes。
- SR-018 已通过远端 CI 并标记 VERIFIED：公开资源与词表 REST 契约层、canonical 查询、列表/详情 Presenter、稳定错误码与 OpenAPI Schema 已完成；WordPress route runtime 接线留给后续允许启动入口的任务。
- SR-019 已通过远端 CI 并标记 VERIFIED：资源 SEO 文档模型、canonical/meta Presenter、下架 noindex、永久移除 410、安全 JSON-LD 与 sitemap entry/XML 渲染支持层已完成。
- SR-020 已通过远端 CI 并标记 VERIFIED：20 条合成资源 Fixture、边界版本状态、幂等 seed 脚本和根级 Pest 配置已完成。
- SR-021 已通过远端 CI 并标记 VERIFIED：主题设计令牌、组件 CSS、按钮、通知、资源元信息和资源卡组件均已完成。
- SR-022 已通过远端 CI 并标记 VERIFIED：首页模型、导航、页脚、专题区、精选资源区和可恢复空状态已完成，页面内容可通过 `sr_theme_front_page_model` 从服务层注入。
- SR-023 已通过远端 CI 并标记 VERIFIED：资源归档页、URL 筛选、canonical 查询、分页、可恢复空状态和非法筛选 noindex 已完成。
- SR-024 已通过远端 CI 并标记 VERIFIED：资源详情页、兼容性/限制/版本/风险同屏展示、AccessDecision CTA Presenter 和隐藏内容泄露检查已完成。
- SR-025 已通过远端 CI 并标记 VERIFIED：VIP 营销页与套餐对比壳、EDD 价格来源标记、支付禁用 CTA、加载/空/错误/无权状态已完成。
- SR-027 已通过远端 CI 并标记 VERIFIED：角色能力矩阵、管理员高风险能力限制、对象所有权授权判断和稳定拒绝原因支持层已完成。
- 工作区已整理：真实项目仓库位于 `Indicator_Marketplace/project/`，原始执行指南和产品资料位于父级 `docs/`。

## 阻塞

- 暂无 W1 阶段阻塞。

## 下一步

1. SR-028 实现 StorageService 接口与 MinIO 适配器。
2. SR-030 实现 COS/OSS/S3 生产适配器契约。
3. SR-026 实现登录、用户中心、订单与下载中心壳。
