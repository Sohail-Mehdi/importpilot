# ADR-003 — Canonical Schema Specification

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

ImportPilot's core value proposition requires a strongly typed, versioned, language-neutral schema specification that defines:
- What fields an import target contains.
- The data type, validation rules, and constraints of each field.
- Which fields are required vs optional.
- Human-readable labels and descriptions for the importer UI.

This schema must be consistently interpretable by Laravel (PHP), Python, and the React importer without each service re-implementing its own interpretation.

## Decision

The canonical schema specification will be maintained in `packages/schema-spec/` as a language-neutral JSON/YAML definition format. A reference JSON Schema will define the meta-schema (schema of the schema).

Key design decisions (to be finalized in Phase 1):
- Schemas are versioned with an explicit `version` field.
- Schemas are immutable once published — a change requires a new version.
- Each field definition specifies: `key`, `type`, `label`, `required`, `validation_rules[]`.
- Validation rules are a closed enumeration of deterministic, interpretable rules (no arbitrary executable code in schemas).
- AI-assisted suggestions (if ever enabled) produce candidate rules that must be explicitly reviewed and accepted before a schema is published.

## Open Questions

- [ ] What is the exact field type taxonomy? (string, integer, decimal, date, email, phone, enum, boolean, ...?)
- [ ] How are locale-sensitive types (dates, numbers) parameterized?
- [ ] What is the schema versioning format — semver, monotonic integer, or content-addressed hash?
- [ ] How are schemas distributed to the embeddable importer — API fetch or bundled at render time?

---

*This ADR is a placeholder. Detailed schema format will be designed in Phase 1.*
