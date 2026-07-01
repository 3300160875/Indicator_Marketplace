# SR-067 Content Import

SR-067 defines a deterministic 100-item content migration workflow.

The source manifest is `sr067-import-manifest.json`. The import tool generates
100 synthetic resource candidates from the manifest, validates them, simulates an
apply batch, and verifies rollback by natural key. These records are not
production content and do not contain customer data, credentials, cookies,
tokens, storage keys or real payment proof.

Important publishing rule: every generated record defaults to
`rights_status=pending`. A 100% completeness report means the migration payload is
structurally ready for review; it does not mean paid resources can be published
before copyright review.
