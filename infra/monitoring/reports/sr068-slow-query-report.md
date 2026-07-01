# SR-068 Slow Query and N+1 Report

- Status: pass
- Slow query threshold: 100ms
- Endpoint query count checks: 5

## Query Plans

| Plan | Table | Required index | Covers filter | Index columns | Estimate |
| --- | --- | --- | --- | --- | --- |
| active_entitlements_for_user | wp_sr_entitlements | idx_user_active | yes | user_id, status, expires_at | 24ms |
| download_token_by_hash | wp_sr_download_tokens | uq_token_hash | yes | token_hash | 12ms |
| download_token_idempotency | wp_sr_download_tokens | uq_request_id | yes | request_id | 12ms |
| download_token_expiry_sweep | wp_sr_download_tokens | idx_expiry_status | yes | expires_at, status | 24ms |
| download_events_user_window | wp_sr_download_events | idx_user_date | yes | user_id, created_at | 24ms |
| download_events_resource_result_window | wp_sr_download_events | idx_resource_result | yes | resource_id, result, created_at | 24ms |
| rights_record_publish_gate | wp_sr_rights_records | idx_resource_status | yes | resource_id, status | 24ms |

## Endpoint Query Counts

| Endpoint | Queries | Budget | N+1 risk |
| --- | ---: | ---: | --- |
| public_resource_archive | 5 | 8 | no |
| public_resource_detail | 3 | 5 | no |
| me_entitlements | 4 | 6 | no |
| create_download_token | 7 | 9 | no |
| consume_download_token | 7 | 9 | no |
