
# Runbook：发布与回滚

## 发布
1. CI、P0 E2E、迁移 dry-run、备份和恢复抽检；
2. 构建不可变制品并记录 Git SHA、Composer lock、Node lock、Schema；
3. Staging 数据脱敏验证；
4. 数据库备份；
5. 运行 forward migration；
6. 部署应用；
7. smoke、订单/权益/令牌合成检查；
8. 白名单→5%→25%→100%；
9. 记录 `docs/releases/<version>.md`。

## 回滚
- 优先关闭支付/令牌 Feature Flag；
- 应用回退到上一制品；
- 禁止盲目 down migration；应用需兼容已添加字段；
- 破坏性数据问题按备份恢复方案执行；
- 对订单/权益/计数运行对账修复；
- 创建事故复盘和后续任务。
