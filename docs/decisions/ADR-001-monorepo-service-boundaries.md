# ADR-001 — Monorepo and Service Boundaries

| Field | Value |
|---|---|
| **Status** | Draft |
| **Date** | 2026-09-17 |
| **Author** | Architecture Team |

---

## Context

ImportPilot operates as a platform with three distinct service responsibilities:

1. **Control plane** — Laravel application handling authentication, multi-tenancy, lifecycle management, billing readiness, and webhook orchestration.
2. **Data engine** — Python service handling all file parsing, profiling, mapping calculations, validation, transformation, and Parquet/Arrow artifact generation.
3. **Embeddable importer** — React/TypeScript UI surface that customers embed into their own applications.

A monorepo was chosen to enable atomic changes across service boundaries during early development, while keeping service deployments independently scalable.

## Decision

The repository is structured as a monorepo at `github.com/Sohail-Mehdi/importpilot` (working codename). Services are co-located under `apps/` and shared packages under `packages/`. Each service remains independently deployable — the monorepo is a development convenience, not an architectural coupling.

Service boundary rules:
- Services communicate only through defined, versioned API contracts.
- No service may directly import another service's database schema.
- No service may directly import another service's internal business logic.
- Shared types/schemas are extracted to `packages/schema-spec/`.

## Consequences

**Positive:**
- Atomic cross-service changes during rapid early development.
- Shared tooling, CI, and dependency management.
- Easier cross-service refactoring when boundaries shift early on.

**Negative / Risks:**
- Requires disciplined boundary enforcement as team grows.
- CI pipelines must be scoped per-service to avoid unnecessary full rebuilds.
- Monorepo tooling (Turborepo, Nx, or Makefile targets) may be required at scale.

## Open Questions

- [ ] Will a monorepo orchestration tool (e.g., Turborepo, Nx, or simple Makefile) be introduced? Defer to Phase 1.
- [ ] What is the versioning strategy for inter-service API contracts?

---

*This ADR will be finalized after service contract definitions are established in Phase 0.*
