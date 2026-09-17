# ADR-006 — Object Storage and Artifact Lifecycle

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

ImportPilot must store multiple categories of binary object:
- Original uploaded files (immutable source).
- Parsed Parquet artifacts (derived, also immutable once generated).
- Final committed output artifacts.
- Potentially correction patch archives.

These must be stored behind a vendor-neutral S3-compatible abstraction to avoid lock-in.

## Local Development Storage Decision

**Context:** The specification referenced MinIO as the local-development S3-compatible server. However, MinIO's community edition has shifted to a source-only distribution model as of 2025-2026, removing pre-compiled binaries and changing Docker image availability for the community edition.

**Recommended replacement for local development:** LocalStack (community edition) — actively maintained, Docker-native, implements the S3 API, and is an industry standard for AWS-compatible local development. It supports the subset of S3 features required by ImportPilot (bucket creation, object put/get/delete, presigned URLs).

See CHANGE-CONTROL.md for the formal change proposal.

## Abstraction Layer

All application code must interact with object storage exclusively through an abstraction:
- **Laravel (PHP):** Laravel's `Storage` facade with an S3-compatible driver (e.g., Flysystem S3 adapter).
- **Python:** `boto3` with the endpoint URL configured via environment variable.

Neither the Laravel nor Python codebase may hardcode S3 endpoint URLs, bucket names, or credentials. All storage configuration must be environment-variable-driven.

## Artifact Lifecycle

| Artifact Type | Retention | Deletion Trigger |
|---|---|---|
| Original upload | Per retention policy | Policy expiry or user-initiated deletion |
| Parsed Parquet | Per retention policy | Same as original |
| Final output | Per retention policy | Same as original |
| Failed session artifacts | Configurable short TTL | Configurable TTL or explicit cleanup |

Retention policy enforcement is the control plane's responsibility.

## Open Questions

- [ ] What is the minimum S3 API surface required? (Needed to evaluate storage backends.)
- [ ] Will presigned URLs be used for direct browser-to-storage uploads?
- [ ] What is the default retention period?

---

*This ADR is a placeholder pending storage architecture design in Phase 1.*
