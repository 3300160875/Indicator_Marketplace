# Project Status

> YAML 文件 `project-status.yaml` 是机器可读事实来源。本文件用于周报和人工阅读。

## 当前阶段

- Milestone: W6
- CI: configured and required on `main`
- Next safe task: 推进 SR-068
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
- SR-028 已通过远端 CI 并标记 VERIFIED：StorageService 契约、Fake adapter、MinIO/S3 兼容适配器、SigV4 签名、私有 ACL 和稳定错误映射已完成。
- SR-030 已通过独立 QA 并标记 VERIFIED：新增 S3-compatible 生产适配器契约，S3/COS/OSS 使用 virtual-hosted endpoint，MinIO 保持 path-style endpoint，并通过私有 ACL、签名和供应商 SDK 泄漏检查。
- SR-026 已通过独立 QA 并标记 VERIFIED：账户中心壳、登录门禁、对象所有权门禁、订单中心壳、下载中心壳和空态/异常态已完成，模板不直接访问 EDD 内部表。
- SR-029 已通过独立 QA 并标记 VERIFIED：版本隔离上传与扫描状态机已完成，文件先进入 quarantine，服务端 MIME/大小/压缩包限制与 SHA-256 生效，扫描失败停留 quarantine，clean 后才移动到正式前缀并激活版本。
- SR-031 已通过独立 QA 并标记 VERIFIED：EDD 3.6.9 订单、订单项、客户与退款 fixture 已封装为 `Integration/Edd` Adapter，完成/退款事件投影到 SR-007 合约 DTO，EDD API 触点未散落到领域层。
- SR-032 已通过独立 QA 并标记 VERIFIED：资源访问模式与价格校验已完成，free/purchase/vip/purchase_or_vip/unavailable 受控，服务端重新计算金额，`ResourcePurchaseValidator` 仅接受资源商品并拒绝会员套餐混入资源购买流程。
- SR-033 已通过独立 QA 并标记 VERIFIED：定制 EDD 结算与数字内容条款支持层已完成，未登录策略明确，Gate 0/付款开关关闭时不会调用真实订单创建回调，服务端金额、条款版本和数字内容确认会进入结算快照。
- SR-034 已通过独立 QA 并标记 VERIFIED：订单项业务快照支持层已完成，商品类型/规则版本/价格/资源或套餐元数据冻结，unit/discount/total 金额入快照，已有快照按订单项幂等复用，非本人订单、退款单、缺失用户映射和缺失必填业务元数据会安全失败。
- SR-035 已通过独立 QA 并标记 VERIFIED：用户订单列表与详情适配支持层已完成，只返回当前用户订单，状态映射为用户可读文案，缓存 key 包含用户与规则版本，空态/过期/撤权/配额重置有可复核投影，内部审核备注不会暴露。
- SR-036 已通过独立 QA 并标记 VERIFIED：个人码人工支付 Gateway 支持层已完成，受手动支付开关控制，创建 intent 后 EDD 订单保持 pending，页面文案明确人工核验且不会自动到账，状态机/唯一指纹/锁版本/幂等审批均有证据覆盖，审批 replay 已校验锁版本与真实账单参数。
- SR-037 已完成并进入 VERIFIED：`sr_payment_submissions` 实体、状态机、仓储、迁移与证据脚本已就绪。
- SR-038 已完成并进入 VERIFIED：付款凭证提交 API 支持层已完成（幂等键、状态机约束、文件校验、时间线与状态查询）。
- SR-039 已完成并进入 VERIFIED：审核队列并发控制支持层已完成（同一 reviewer 重入、锁超时、锁版本与超时释放）。
- SR-040 已完成并进入 VERIFIED：支付审核服务完成层已完成（重放保护、权限控制、指纹校验、订单完成回调）。
- SR-041 已随 PR #41 合入 `main` 并标记 VERIFIED：审核决策服务与支付审核用户时间线已完成，原 PR #38 已关闭为 superseded。
- SR-042 已随 PR #39 合入 `main` 并标记 VERIFIED：付款审核通知 Outbox 框架、重试/死信与内存仓储支持层已完成。
- SR-043 已随 PR #40 合入 `main` 并标记 VERIFIED：权益、配额计数与下载事件核心表迁移定义已完成。
- SR-044 已随 PR #40 合入 `main` 并标记 VERIFIED：会员套餐元数据解析、范围/配额/规则版本校验已完成。
- SR-045 已随 PR #42 合入 `main` 并标记 VERIFIED：权益快照仓储契约、内存实现、快照签名与不可变校验已完成。
- SR-046 已随 PR #44 合入 `main` 并标记 VERIFIED：AccessDecision 契约、AccessDecisionContext 与 EntitlementService 访问判断已完成。
- SR-047 已通过独立 QA 并进入 VERIFIED（PR #46）：订单完成授权监听器支持资源商品/会员套餐授权、`source_order_item_id` 幂等、重复完成 10 次只授权一次和部分失败安全重跑。
- SR-048 已随 PR #48 合入 `main` 并标记 VERIFIED：MembershipService 支持 active 同套餐续期顺延、过期/撤权后从当前购买时间起算、多套餐并存稳定择优和可解释选择结果。
- SR-049 已随 PR #50 合入 `main` 并标记 VERIFIED：RevocationService 支持退款撤权、人工授权/撤销、原因必填、审计事件和用户权益/下载令牌缓存失效信号。
- SR-050 已通过独立 QA 并进入 VERIFIED（PR #47）：QuotaService 支持 reserve/commit/release、request_id 幂等、deadlock retry、lock timeout fail-closed 和配额不超发检查。
- SR-051 已随 PR #52 合入 `main` 并标记 VERIFIED：ContentRestriction 服务端支持层可按 AccessDecision 渲染短代码/区块，未授权与编辑器预览不会输出隐藏内容，并提供用户/资源维度 cache vary keys。
- SR-052 已随 PR #54 合入 `main` 并标记 VERIFIED：会员中心 `/me` 权益投影、可注册 REST route wrapper、用户+规则版本缓存读写/失效、会员权益模板与独立 QA 证据已完成；启动入口和账户页接线留给后续允许改 bootstrap/template 入口的任务。
- 测试入口补强已完成：仓库级 `make test-unit MODULE=...`、`make test-integration TEST=...` 与 `make test-concurrency TEST=...` 已可用，`account`、`sr-private-downloads`、`Downloads` 和 `DownloadTokens` 路径验证通过。
- SR-053 已随 PR #59 合入 `main` 并标记 VERIFIED：`sr_download_tokens` schema 定义、HMAC token hash 存储、32 字节 Base64URL raw token、120 秒 TTL、request_id/token_hash 唯一、原子单次消费契约和 DownloadTokens 并发证据已完成。
- SR-054 已随 PR #62 合入 `main` 并标记 VERIFIED：创建下载令牌 API 支持层已完成，事务内重查 EntitlementService、VIP 配额通过 QuotaService 预占、幂等 claim/complete、失败重放稳定、响应不暴露 storage_key 或签名 URL。
- SR-055 已随 PR #64 合入 `main` 并标记 VERIFIED：令牌消费、短签名与 302 交付支持层已完成，成功路径 consumed + quota commit + redirected event 处于事务边界，失败路径 failed + quota release + failed event，OpenAPI 错误状态和 request_id 契约已对齐。
- SR-056 已随 PR #66 合入 `main` 并标记 VERIFIED：下载事件结算与失败补偿支持层已完成，覆盖 redirected 计数结算、failed/expired 释放、dry-run reconcile、request_id/token_id 幂等、QuotaService 真实 counter 变化和独立 QA PASS；真实 WP-CLI 注册留给后续允许改启动入口的任务。
- SR-057 已随 PR #69 合入 `main` 并标记 VERIFIED：下载限流、防重放与异常规则已完成，覆盖用户/IP/资源多维限流、token replay 阻断、账号共享风险提示与可逆限制、阻断安全事件，以及消费下载链路前置安全检查。
- SR-058 已随 PR #71 合入 `main` 并标记 VERIFIED：Nginx 防直链与动态页面缓存例外已完成，EDD 上传目录和私有对象/下载目录匿名访问返回 403，checkout/account/wp-json/download/download-tokens 动态入口返回 `Cache-Control: private, no-store`，并通过静态配置检查、runtime curl 检查、`docker compose config --quiet`、`make bootstrap`、`make test-smoke`、`nginx -t` 和独立 QA PASS。
- SR-059 已随 PR #73 合入 `main` 并标记 VERIFIED：版权记录与发布 Gate 支持层已完成，覆盖付费资源必须 `rights_status=approved` 且绑定匹配 approved rights record、证据 storage key 私有校验、合同/当前权限能力名兼容且 owner-scoped、到期 warn-only/pause-publication 可配置、下架阻断新 token，以及不泄露 evidence key 的审计事件；独立 QA 的权限命名与 owner-scope 发现已修复并复核 PASS。
- SR-060 已随 PR #75 合入 `main` 并标记 VERIFIED：审计日志支持层已完成，覆盖付款批准/撤权/发布/配置变更高风险动作、递归敏感字段脱敏、append-only 仓储接口、普通 administrator 无显式审计能力不可查询/删除、`request_id` 查询不绕过角色可见性、canonical `wp_sr_audit_logs` schema 对齐和查询视图 payload；独立 QA 的权限绕过、append-only 接口和 schema drift 发现已修复并复核 PASS。
- SR-061 已随 PR #77 合入 `main` 并标记 VERIFIED：工单与消息模块支持层已完成，覆盖订单/资源/下载事件关联、关联所有权校验、私有附件 key 策略、客户/内部消息投影隔离、分配工单权限、可配置 SLA 与状态流转，以及 support audit action 本地登记；独立 QA 复核 PASS。
- SR-062 已随 PR #79 合入 `main` 并标记 VERIFIED：收藏模块支持层已完成，覆盖 user+resource 唯一、幂等新增/删除/设置、用户维度列表、缓存失效 key，以及草稿/缺失/不可用资源收藏的不可泄露占位投影；独立 QA 复核 PASS。
- SR-063 已随 PR #81 合入 `main` 并标记 VERIFIED：付款/会员/下载/版权任务工作台支持层已完成，覆盖任务队列聚合、角色字段投影、高风险动作 reason/二次确认、基于 domain task context 的 item/action/queue 校验、per-item audit records、批量上限和分页上限；独立 QA 发现的跨队列授权和 view-only retry 问题已修复并复核 PASS。
- SR-064 已随 PR #83 合入 `main` 并标记 VERIFIED：MVP 业务报表与健康检查支持层已完成，覆盖订单完成无权益、下载失败率、审核时长、时区/新鲜度口径、窗口过滤、前台查询保护、聚合 CSV 导出权限与脱敏、outbox/download settlement/audit freshness 健康检查；独立 QA 发现的窗口过滤与 legacy 权限问题已修复并复核 PASS。
- SR-065 已随 PR #86 合入 `main` 并标记 VERIFIED：跨插件契约测试与全链路 Fixture 已完成，覆盖 Docker Compose、PHP 容器内 WordPress/EDD、live EDD active、临时 MariaDB schema install/insert/drop、MinIO put/head/sign/delete、免费/单购/VIP/排除/额度耗尽/退款/下架场景、顺序独立、request_id、数据库行 trace 与日志脱敏；第一轮 QA 发现的模拟链路问题已修复并由第二轮 QA 复核 PASS。
- SR-066 已进入 BLOCKED：独立 QA 判定在当前 allowed paths 仅 `tests/e2e/**` 的约束下，无法满足真实 Playwright P0 E2E、根级 `npm run e2e` 与 CI 可重复运行验收；需要后续允许修改 root `package.json`/lock、CI tooling 和真实应用工作流入口后重启。
- SR-067 已随 PR #89 合入 `main` 并标记 VERIFIED：100 条内容迁移与校验流程已完成，覆盖 deterministic import manifest、100 条生成候选、dry-run/validation/apply-state/rollback/release-readiness 报告、版权默认 `pending`、发布前完整性 100%、publication_ready=false 与 rollback hash 可复核；独立 QA 复核 PASS。
- 工作区已整理：真实项目仓库位于 `Indicator_Marketplace/project/`，原始执行指南和产品资料位于父级 `docs/`。

## 阻塞

- 暂无 W1 阶段阻塞。

## 下一步

1. 推进 SR-068。
2. 规划 SR-066 unblock：允许根级 e2e tooling/依赖/CI wiring 后重启。
