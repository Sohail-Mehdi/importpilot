# ADR-008 — AI-Optional Policy

| Field | Value |
|---|---|
| **Status** | Draft — Policy Direction Set |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

ImportPilot is designed as a deterministic-first system. Every mapping decision, validation outcome, and correction applied must be auditable and explainable without requiring access to an external AI service.

The question of whether and how AI/LLM capabilities might be added as optional enhancements requires an explicit policy decision to prevent accidental coupling.

## Decision

**The MVP of ImportPilot must be fully functional without any AI/LLM service.**

Specifically:

1. **No AI in MVP:** OpenAI, Anthropic, Gemini, Cohere, or any other LLM API must not be required by the MVP. No embeddings, vector databases, semantic search, or AI-generated content are included in Phase 0, Phase 1, or the MVP release.

2. **Deterministic baseline is not optional:** All mapping, validation, and transformation logic must work correctly using deterministic algorithms alone. AI is not a crutch for algorithm design.

3. **AI as future optional enhancement:** If AI capabilities are added in a future phase, they must:
   - Be gated behind an explicit, opt-in configuration flag.
   - Never replace the deterministic baseline — only supplement it with additional candidate suggestions.
   - Always make their AI-generated suggestions reviewable before any consequence.
   - Never require AI access to complete an import that has already been confirmed by a user.
   - Be clearly disclosed to customers when active.

4. **No vendor coupling:** If AI is added, the abstraction must allow provider replacement without rewriting business logic.

## Consequences

**Positive:**
- System works reliably in air-gapped environments.
- No dependency on third-party API availability for core operations.
- Full auditability without LLM explainability challenges.
- No data-privacy concerns from sending customer data to external AI APIs.

**Negative / Accepted Trade-offs:**
- Initial mapping quality depends entirely on deterministic algorithm quality.
- Complex or ambiguous header names may require more manual user intervention in the MVP.

## Open Questions

- [ ] At which phase, if any, will AI-assisted mapping suggestions be formally evaluated?
- [ ] What data-privacy review is required before customer data can be sent to an external AI service?

---

*AI-optional policy is set. This ADR will be revisited when an AI-enhancement phase is formally proposed.*
