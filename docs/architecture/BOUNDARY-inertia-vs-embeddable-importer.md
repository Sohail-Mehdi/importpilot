# Architecture Boundary: Inertia vs Embeddable Importer

| Document Type | Architecture Boundary Record |
|---|---|
| **Status** | Established — Phase 0 |
| **Date** | 2026-09-17 |

---

## Problem Statement

ImportPilot uses Inertia.js as the server-driven UI layer for the Laravel control-plane dashboard. Inertia provides seamless, SPA-like page transitions within a Laravel application without a dedicated API layer.

However, the ImportPilot embeddable importer — the React component that customers embed into their own web applications — must **not** depend on Inertia.

If the importer were coupled to Inertia, every customer application would need to:
- Use a Laravel backend, OR
- Host an Inertia bridge themselves.

This directly contradicts the product's design: ImportPilot should be embeddable into any web application regardless of the customer's backend language or framework.

---

## Established Boundary

### Inertia Scope (Laravel control plane — `apps/control-plane`)

Inertia is used exclusively within `apps/control-plane` for rendering the ImportPilot dashboard:
- Developer dashboard: schema management, project settings.
- Import session monitoring.
- Team and organization management.
- Billing and usage views.

Inertia pages are served by Laravel, consumed by the developer's own browser session, and are not exposed to end-customer applications.

### Embeddable Importer Scope (`apps/importer-web`)

The embeddable importer in `apps/importer-web` is a **standalone React/TypeScript surface**:
- It communicates with the ImportPilot API via direct HTTP/fetch calls.
- It authenticates using an API key embedded at render time (or via a session token issued by the customer's backend).
- It has no compile-time dependency on Laravel, Inertia, or PHP.
- It is distributed as a self-contained JavaScript bundle that any web application can load.
- It may be published as an npm package in a future phase.

### What This Means in Practice

| Concern | Inertia (control-plane) | Importer (importer-web) |
|---|---|---|
| Rendering mechanism | Laravel + Inertia + React | Standalone React bundle |
| Authentication | Laravel session / CSRF | API key / short-lived session token |
| Routing | Inertia page components | React Router or prop-driven |
| State management | Inertia props + React | Standalone React state |
| Backend dependency | Laravel required | Any HTTP server |
| Customer backend requirement | N/A | None |

---

## Enforcement

- `apps/importer-web/package.json` must never list `@inertiajs/react` as a dependency.
- `apps/importer-web` must never import from `apps/control-plane`.
- CI checks should verify this boundary as the project grows.
- Any proposal to couple the importer to Inertia must go through change control.

---

## Rationale

An embeddable importer that requires customers to use Inertia is effectively not embeddable. The entire value of the embeddable model is that customers can adopt ImportPilot regardless of their technology stack.

---

*This boundary is established in Phase 0 and is binding for all subsequent development unless explicitly revised through change control.*
