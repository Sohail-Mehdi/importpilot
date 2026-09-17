# ADR-004 — Import State Machine and Idempotency

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

An import session in ImportPilot passes through multiple states as processing progresses. Given that processing is asynchronous, distributed across workers, and may fail at any point, the state machine must be:
- Clearly defined with explicit legal transitions.
- Persisted durably in the control plane database (PostgreSQL via Laravel).
- Recoverable — a crashed worker must not corrupt session state.
- Idempotent — re-processing the same job must produce the same outcome.

## Intended State Machine (Draft)

```
PENDING_UPLOAD
    → UPLOADING
        → UPLOADED
            → INSPECTING          (data engine: format/encoding detection)
                → PROFILING       (data engine: column statistics)
                    → AWAITING_MAPPING  (user reviews auto-mapped fields)
                        → VALIDATING   (data engine: row-level validation)
                            → AWAITING_CORRECTION  (user fixes errors)
                            → DRY_RUN_READY        (all rows valid)
                                → COMMITTING       (data engine: final artifacts)
                                    → COMPLETED
                                    → FAILED
            → FAILED
```

Terminal states: `COMPLETED`, `FAILED`, `CANCELLED`.

## Idempotency Requirements

- Every background processing job must carry a deterministic idempotency key derived from the import session ID and processing stage.
- Re-queuing a job for an already-completed stage must be a safe no-op.
- Worker crashes must be recoverable by re-enqueueing — not by human intervention.

## Open Questions

- [ ] Should partial FAILED sessions be retryable, or must the user re-upload?
- [ ] What is the maximum retry count per stage before permanent FAILED status?
- [ ] Are state transitions exclusively controlled by the Laravel control plane, or can Python workers directly update state?

---

*This ADR is a placeholder pending full state machine design in Phase 1.*
