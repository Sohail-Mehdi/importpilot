# ADR-005 — Immutable Original Source + Patch-Based Corrections

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

When a user uploads a file, ImportPilot must never mutate the original bytes. This is both an auditing requirement and a correctness requirement: the original source is the ground truth from which all subsequent derivations are traceable.

When validation fails and a user corrects rows through the importer UI, those corrections must be stored as an explicit, auditable patch layer — not by overwriting the parsed data in place.

## Decision

1. **Immutable source storage:** The original uploaded file is stored exactly as received, in object storage, under a content-addressed key (e.g., SHA-256 of file bytes). The stored object is write-once; it is never overwritten or deleted during the retention window.

2. **Parsed representation:** The data engine produces a normalized Parquet representation of the original file. This is a derived artifact, also stored immutably.

3. **Correction patches:** User corrections are stored as a separate, ordered patch set. Each patch records: row identifier, field key, original parsed value, corrected value, author, and timestamp.

4. **Final artifact generation:** The committed output is produced by applying the patch set to the parsed representation. The full derivation chain (source → parsed → patches → output) must be reconstructable at audit time.

## Consequences

**Positive:**
- Full auditability of every data change.
- Ability to replay corrections.
- Clear legal/compliance defensibility regarding source data.

**Negative / Risks:**
- More complex storage model than a simple mutable table.
- Final artifact generation requires a patch-application step.
- Patch conflict resolution needs design if concurrent corrections are allowed.

## Open Questions

- [ ] How are patches stored — as a JSONB column in PostgreSQL or as a separate object in storage?
- [ ] Are patches ordered by sequence number or timestamp?
- [ ] Can patches be reverted, or are they also immutable once submitted?

---

*This ADR is a placeholder pending storage design in Phase 1.*
