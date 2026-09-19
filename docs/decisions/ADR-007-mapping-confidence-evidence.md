# ADR-007 — Mapping Confidence and Evidence Model

| Field | Value |
|---|---|
| **Status** | Accepted |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

A core feature of ImportPilot is automatic header-to-schema-field mapping. When a customer uploads a file, the system proposes which uploaded columns map to which schema fields. The end user reviews, corrects, and confirms the mapping before validation proceeds.

The mapping engine must be:
- **Deterministic:** The same input always produces the same mapping proposal.
- **Explainable:** Every confidence score must be backed by an auditable evidence chain.
- **Conservative:** When confidence is below a configurable threshold, the field is flagged for manual assignment rather than auto-accepted.

## Intended Design (Draft)

Mapping confidence is computed by the Python data engine using a weighted combination of:

1. **Exact header match** — string equality after normalization (case folding, whitespace trimming): highest weight.
2. **Normalized fuzzy match** — edit-distance similarity (RapidFuzz) against canonical field labels and known aliases: medium weight.
3. **Type compatibility** — does the column's inferred data type match the schema field's declared type?: tiebreaker.
4. **Position heuristics** — for fields with low-entropy headers, position in the sheet relative to expected columns may provide a weak signal: lowest weight.

AI/LLM-based suggestions are explicitly excluded from the MVP. See ADR-008.

## Evidence Record

Every mapping proposal produced by the engine must emit an evidence record containing:
- Which signals were evaluated.
- The score for each signal.
- The weighted composite score.
- The threshold applied.
- The resulting decision: auto-mapped, flagged for review, or unresolvable.

This record is stored with the import session and is available for audit.

## Open Questions

- [ ] What are the exact weight coefficients for each signal?
- [ ] What is the default auto-accept confidence threshold?
- [ ] Should users be allowed to configure thresholds per schema?
- [ ] What is the alias management system — where are known aliases stored?

---

*This ADR is a placeholder pending algorithm design in Phase 1.*
