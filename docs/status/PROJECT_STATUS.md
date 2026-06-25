# Project Status

> YAML 文件 `project-status.yaml` 是机器可读事实来源。本文件用于周报和人工阅读。

## 当前阶段

- Milestone: W1
- CI: configured and required on `main`
- Next safe task: SR-006 independent review, then EDD adapter task
- Gate 0: near complete

## 本周完成

- SR-001 文档基线已通过独立评审并标记 VERIFIED。
- SR-002 Bedrock / WordPress / EDD Composer 基线已通过独立评审并标记 VERIFIED。
- SR-003/SR-004 本地 Docker 环境与 Make 封装已完成。
- SR-005 CI 最小门禁已合入 `main`，GitHub Actions 的 PHP/Frontend gate 已通过并设为分支保护必需检查。
- SR-006 已补齐 EDD runtime spike：完成订单 hook、重复完成、整单退款和订单项部分退款均有证据。

## 阻塞

- 暂无 W1 阶段阻塞；SR-006 仍需独立评审后标记 VERIFIED。

## 下一步

1. 完成 SR-006 独立评审。
2. 合并 SR-006。
3. 进入 EDD adapter / entitlement 边界实现任务。
