
# Runbook：下载与配额事故

## 立即动作
- 关闭 `SR_DOWNLOAD_TOKEN_ISSUE_ENABLED`，保留内容浏览和工单；
- 保存 request_id、用户、资源、版本、权益、令牌和存储日志；
- 不手工删除事件或直接改计数器。

## 诊断顺序
1. AccessDecision；2. 配额预占/结算；3. 令牌状态；4. 存储签名；5. 对象存在性；6. CDN/Nginx；7. 用户网络。

## 修复
- 先写可复现测试；
- 数据修复命令必须 `--dry-run`、有幂等键和影响数量；
- 修复后运行并发、重放、退款撤权和 P0 E2E；
- 恢复开关前对账 reserved/used/token/event。
