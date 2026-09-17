# Technology Stack — Validated Versions

**Document Type:** Phase-0 Technology Validation
**Date:** 2026-09-17
**Status:** Current

This document records the result of validating all technology choices against
official primary documentation as of the bootstrap date.

---

## Version Decisions

| Technology | Locked Spec | Validated Current | Decision | Notes |
|---|---|---|---|---|
| **Laravel** | Latest stable | 13 (active), 12 (security-only) | **Use 13** — PROPOSED (see CHANGE-002) | Laravel 12 active support ended Aug 13 2026 |
| **PHP** | 8.x | 8.4.25 (installed) | **KEEP 8.4.25** | Laravel 13 min: 8.3 ✓ |
| **Python** | 3.11+ | 3.12.3 (installed) | **KEEP 3.12.3** | FastAPI, Polars, PyArrow all support 3.12 ✓ |
| **FastAPI** | Latest | 0.11x (current stable) | **Pin in uv.lock** | Production stable ✓ |
| **Pydantic** | v2 | v2.x (current) | **Pin v2** | Pydantic v1 is EOL ✓ |
| **Polars** | Latest stable | 1.x (current stable) | **Pin in uv.lock** | Actively developed ✓ |
| **PyArrow** | Latest stable | 17.x (current stable) | **Pin in uv.lock** | Stable ✓ |
| **openpyxl** | Latest | 3.x | **Pin in uv.lock** | XLSX read/write ✓ |
| **RapidFuzz** | Latest | 3.x | **Pin in uv.lock** | Deterministic fuzzy match ✓ |
| **Celery** | Latest stable | 5.x | **Pin in uv.lock** | ✓ |
| **Redis** | Latest stable | 7.x | **Pin in Docker** | ✓ |
| **RabbitMQ** | Not in spec | 3.13 / 4.x | **PROPOSED** (see CHANGE-004) | Proposed to replace Redis as Celery broker |
| **PostgreSQL** | 17 | 18 current, 17 fully supported | **Use 17.x in Docker** | Stable, supported through Nov 2029. 18 is very new |
| **React** | Latest stable | 19.x | **Pin in package.json** | ✓ |
| **TypeScript** | Latest stable | 5.x | **Pin in package.json** | ✓ |
| **Vite** | Latest stable | 6.x | **Pin in package.json** | ✓ |
| **Inertia.js** | Latest | 2.x | **Pin (control-plane only)** | Boundary documented ✓ |
| **TanStack Table** | v8 | v8.x | **Pin in package.json** | ✓ |
| **TanStack Virtual** | Latest | 3.x | **Pin in package.json** | ✓ |
| **Tailwind CSS** | v3/v4 | v4 current | **Pin in package.json** | v4 stable, v3 still supported |
| **Node.js** | LTS | 24 LTS (active), 26 = Current | **Use 24 LTS** — PROPOSED (see CHANGE-005) | Node 26 not yet LTS as of 2026-09-17 |
| **pnpm** | Latest stable | Not installed | **Install before Phase 1** | see CHANGE-007 |
| **Docker** | 24+ | 29.8.1 (installed) | **KEEP** | ✓ |
| **Docker Compose** | v2 | v5.5.1 (installed) | **KEEP** | ✓ |
| **Nginx** | Latest stable | 1.27.x | **Pin in Docker** | ✓ |
| **GitHub Actions** | Latest runners | ubuntu-24.04 available | **Pin runner version** | Floating ubuntu-latest is acceptable for now |
| **Playwright** | Latest | 1.x | **Pin in package.json** | ✓ |
| **Object Storage** | MinIO (local) | MinIO CE binary distribution changed | **Use LocalStack** — PROPOSED (see CHANGE-003) | MinIO CE no longer pre-compiled |

---

## Version Pinning Policy

- Composer: `composer.lock` committed — exact versions locked.
- Python: `uv.lock` committed — exact versions locked.
- Node: `pnpm-lock.yaml` committed — exact versions locked.
- Docker: image versions pinned to explicit tags (not `latest`) in committed Compose files.
- GitHub Actions: action versions pinned to `@vN` tags; SHA pinning may be added for security-sensitive steps.

---

## Notes on PostgreSQL Version Choice

PostgreSQL 17 is chosen over 18 for the following reasons:
- PostgreSQL 18 was released very recently (August 2026 based on version 18.6 dating).
- For a greenfield project on a new platform, stability and proven Docker image quality are preferred.
- PostgreSQL 17.11 has five years of support remaining (through November 2029).
- PostgreSQL 18 can be adopted after at least one minor release cycle demonstrates stability.
- This is NOT a deviation from the locked spec — the spec did not pin a PostgreSQL minor version.
