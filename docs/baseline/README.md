
# 受控产品基线

- `PRODUCT_TECH_PRD_v1.1.full.md` 是 V1.1 DOCX 的文本化完整基线，供 Agent 检索；其图片位于 `media/`。
- V1.0/V1.1 摘要 Markdown 保留用于版本差异和快速阅读。
- 原始 DOCX 的 SHA-256 登记在 `BASELINE_MANIFEST.yaml`，不要求 Agent 解析 DOCX 后再猜测需求。
- 发生冲突时，以 Gate 0、已接受 ADR 和 `docs/EXECUTION_PLAN.md` 为先。
- 任何范围或规则变化均新建 ADR/变更任务，不直接覆盖受控基线。
