
# SR-006 技术 Spike 清单

Spike 必须在一次性分支/测试环境运行，不把试验代码直接并入业务模块。

## EDD
- [x] 建单、订单项 ID、客户/用户映射；
- [x] `edd_complete_purchase` 同步时点和重复调用；
- [x] 后台/服务端人工完成订单时 Hook 行为；
- [x] 订单项退款、整单退款、取消时的数据和 Hook；
- [x] 可用公开 API，禁止依赖私有实现；
- [x] WordPress 7.0 + PHP 8.3 兼容和日志。

Observed: disposable WordPress install and EDD activation succeeded from `docs/spikes/SR-006/runtime-edd-spike.php`. First complete transition returned true, duplicate complete returned false, and completion/refund hooks were observed synchronously. Full refund produced sale status `refunded`; item-level partial refund produced sale status `partially_refunded`.

## 数据库
- [x] MariaDB 10.11 事务、唯一键冲突和 `SELECT ... FOR UPDATE`；
- [x] 两连接并发配额预占；
- [x] 死锁重试策略；
- [x] dbDelta 局限和自研迁移工具可行性。

Observed: second connection timed out with `ERROR 1205` while the first held `SELECT ... FOR UPDATE`; duplicate token insert returned `ERROR 1062`; conflicting two-row updates produced `ERROR 1213 (40001)` for one connection.

## 存储
- [x] MinIO 私有对象上传、HEAD、删除；
- [x] 120 秒预签名 URL；
- [x] 令牌重放和 Range 请求；
- [x] 失败时配额释放。

Observed: direct private object GET returned 403; presigned GET returned content; Range request returned HTTP 206; expired 1-second URL returned 403. HEAD against a GET presign was not used as acceptance evidence because the signed method matters.

## 输出
每项保存：代码片段、命令、版本、日志、结论、风险、推荐接口；最终完成 ADR-001～006。
