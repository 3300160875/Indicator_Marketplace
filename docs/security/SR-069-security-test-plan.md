# SR-069 Security Test Plan

## Scope

SR-069 is a verification and closure task for the security controls delivered by earlier tasks. It does not change payment, entitlement, quota, download, storage or WordPress/EDD business behavior.

Executable coverage is provided by:

- `tests/security/sr069_security_closure.php`

## Coverage Matrix

| Risk | Verification |
| --- | --- |
| IDOR | Download token route ignores caller-supplied `user_id` and uses the authenticated current user. Token binding mismatch remains server-side. |
| CSRF | Contract and route sentinel: OpenAPI requires `wordpressCookieNonce` / `X-WP-Nonce` for `/download-tokens`, and the state-changing route is POST-only with an explicit non-public permission callback. Runtime missing/invalid nonce rejection is not directly proven by this harness. |
| XSS | Public resource content rendering is escaped by the theme helper; HTML meta sanitizer strips script tags while keeping approved formatting tags. |
| SQLi | Static risk sentinel rejects direct request-superglobal interpolation in package SQL-like paths; this repository currently has no package-level direct SQL query surface for this test to exercise as an injected runtime request. |
| SSRF | Storage object keys reject URL-like, absolute, control-character and traversal payloads before storage signing. |
| Path traversal | Storage object keys reject `..`, leading slash and null byte payloads. |
| Compression bomb | `VersionUploadPolicy` rejects excessive archive entries, depth, expanded bytes and compression ratio. |
| Replay | Download token service permits first consume only; delivery security gateway blocks replay before signing or quota mutation. |
| Rate limiting and audit | Download security policy blocks repeated IP attempts and records sanitized security events without raw tokens or private storage keys. |

## Known Boundaries

- `make test-security` is required by SR-069 but is not defined in the current `Makefile`; this task records the failed command and uses the direct PHP security harness as the executable replacement.
- The current allowed paths do not include business-code fixes. If this harness later finds a high-risk issue, the fix must be handled by a follow-up task with explicit ownership of the affected package path.
- Runtime WordPress REST cookie+nonce negative tests are not introduced here. The current harness verifies the API contract and first-party route registration shape, not an authenticated browser request with a bad nonce.
- Real archive decompression and external malware-engine integration remain outside this task; this task verifies the existing policy-level archive guardrails.

## Rollback

Remove `tests/security/sr069_security_closure.php`, this document and the SR-069 evidence files. No runtime code, database schema, feature flag or dependency changes are introduced.
