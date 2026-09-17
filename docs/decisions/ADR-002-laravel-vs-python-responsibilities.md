# ADR-002 — Laravel vs Python Responsibilities

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

ImportPilot requires both a mature web framework for control-plane concerns (authentication, multi-tenancy, API key lifecycle, billing readiness) and a high-performance data processing runtime for heavy file parsing and validation.

A single-language approach would force unacceptable compromises: PHP lacks production-ready columnar data processing and scientific computing libraries; Python lacks a mature, battle-tested ecosystem for multi-tenant SaaS control planes.

## Decision

**Laravel (PHP) is responsible for:**
- User authentication and session management.
- Organization and membership management.
- API key issuance, rotation, and revocation.
- Schema lifecycle (creation, versioning, publication).
- Import session lifecycle state machine orchestration.
- Audit log metadata.
- Webhook registration and orchestration.
- Retention and deletion policy enforcement.
- Billing readiness and subscription gating.
- Queuing data-processing jobs to the background broker.
- REST/JSON API surface for the control plane.

**Python (FastAPI + Celery workers) is responsible for:**
- File inspection (format detection, encoding, sheet enumeration).
- Parsing: CSV, XLSX, XLS, ODS.
- Statistical profiling of imported columns.
- Deterministic header-to-schema-field mapping calculation with confidence scoring.
- Row-level validation against schema field rules.
- Transformation and normalization.
- Duplicate detection.
- Parquet/Arrow artifact generation.
- All CPU-bound and memory-intensive data operations.

**Hard boundaries:**
- Python MUST NOT own authentication, authorization, or subscription decisions.
- Laravel MUST NOT perform row-by-row data processing inside web requests.
- All cross-service calls from Laravel to Python are asynchronous via the job queue, except for synchronous lightweight inspection calls where latency is bounded and justified.

## Consequences

**Positive:**
- Each service uses the best-available ecosystem for its workload type.
- Python workers can scale horizontally without scaling the web layer.
- Laravel retains full control over security-sensitive operations.

**Negative / Risks:**
- Two language runtimes require broader operational expertise.
- Inter-service communication adds latency and failure modes.
- Contract drift is possible if API versions are not strictly managed.

## Open Questions

- [ ] Which specific Python calls (if any) will be synchronous HTTP vs queue-only? To be defined in Phase 1 service contracts.
- [ ] How will Python workers authenticate callbacks to Laravel? Design in Phase 1.

---

*This ADR will be finalized when service API contracts are defined.*
