# SR-029 Review Report

- Review scope: `packages/sr-core/src/Version/Upload/**`, `packages/sr-private-downloads/src/Scan/**` and `docs/evidence/SR-029/version-upload-scan-check.php`.
- Result: pass for REVIEW handoff.
- Quarantine flow: upload service writes to `quarantine/resources/{resource}/versions/{version}/...` before invoking the scanner.
- Clean flow: clean scan writes a final private object, removes the quarantine object, creates a review version and activates it through `ResourceVersionRepository::activateCurrent`.
- Failed scan flow: infected/failed scans remain on quarantine storage, persist non-clean scan status and do not replace the current version.
- Limit checks: MIME, byte size, archive entry count, max depth, expanded bytes and compression ratio are enforced before storage writes.
- Data boundary: no WordPress media-library helper or public ACL is used in the upload/scan layer.
- Residual risk: real archive inspection and external antivirus engines are deferred to later runtime integration tasks; this task provides the stable state-machine contract.
