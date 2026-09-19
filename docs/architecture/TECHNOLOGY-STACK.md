# Technology Stack — Validated Versions

**Document Type:** Phase-0 Technology Validation
**Date (initial):** 2026-09-17
**Date (revised):** 2026-09-17 (Prompt 02 corrections)
**Status:** Current

> **Prompt-02 Corrections Applied:**
> - Laravel 13 was ALREADY the locked specification baseline — no migration from 12.
> - PostgreSQL corrected to 18.6 (locked specification requires 18.x).
> - Redis corrected to 8.2.x (Redis 8.0 reaches EOL December 1 2026).
> - Python runtime corrected to 3.13.15 (project target is 3.13.x).
> - pnpm 12.4.2 installed. Node LTS strategy documented.
> - Local S3: SeaweedFS selected (see ADR-011, CHANGE-003 updated).

---

## Technology Decisions

| Technology | Locked Spec | Verified Version | Decision | Notes |
|---|---|---|---|---|
| **Laravel** | 13.x | 13.x (released Mar 2026, active support) | **LOCKED: 13.x** | Not a migration from 12 — 13 was always the baseline |
| **PHP** | 8.4.x | 8.4.25 (installed) | **LOCKED: 8.4.x** | Laravel 13 min is 8.3 ✓. PHP 8.5 proposal pending (see CHANGE-008) |
| **Composer** | 2.x | 2.10.3 (installed) | **KEEP** | ✓ |
| **php-pdo_pgsql** | Required | NOT YET INSTALLED | **INSTALL — needs user sudo** | `sudo apt-get install -y php8.4-pgsql` |
| **Python (project)** | 3.13.x | 3.13.15 (via uv) | **LOCKED: 3.13.15** | System Python 3.12.3 untouched |
| **uv** | Latest | 0.12.10 | **KEEP** | ✓ |
| **FastAPI** | Latest stable | 0.11x (current stable) | **Pin in uv.lock** | ✓ |
| **Pydantic** | v2.x | v2.x | **Pin v2** | v1 is EOL ✓ |
| **Polars** | Latest stable | 1.x | **Pin in uv.lock** | ✓ |
| **PyArrow** | Latest stable | 17.x | **Pin in uv.lock** | ✓ |
| **openpyxl** | Latest | 3.x | **Pin in uv.lock** | XLSX parsing ✓ |
| **RapidFuzz** | Latest | 3.x | **Pin in uv.lock** | Deterministic fuzzy matching ✓ |
| **Celery** | Latest stable | 5.x | **Pin in uv.lock** | ✓ |
| **PostgreSQL** | 18.x | **18.6** (verified postgresql.org Aug 2026) | **LOCKED: `postgres:18.6-bookworm`** | EOL: Nov 2030. 17 was incorrect in Prompt 01 |
| **Redis** | 8.x | **8.2.x** (8.0 EOL Dec 1 2026; 8.2 extended until Sep 2030) | **LOCKED: `redis:8.2-bookworm`** | Redis 8.0 would expire in months. 8.2 is correct. See CHANGE-009 |
| **RabbitMQ** | Proposed | 4.3.6 (Sep 2026) | **PROPOSED** — awaiting user approval (ADR-009) | Included as optional Compose profile |
| **React** | 19.x | 19.2 | **LOCKED: 19.2** | ✓ |
| **TypeScript** | 5.x | 5.x | **Pin in package.json** | ✓ |
| **Vite** | Latest stable | 6.x | **Pin in package.json** | ✓ |
| **Inertia.js** | 2.x | 2.x | **Pin (control-plane only)** | Boundary enforced per architecture doc ✓ |
| **TanStack Table** | v8 | v8.x | **Pin** | ✓ |
| **TanStack Virtual** | 3.x | 3.x | **Pin** | ✓ |
| **Tailwind CSS** | v4 | v4.x | **Pin** | ✓ |
| **Node.js (project)** | LTS | **24 LTS** (Active LTS Sep 2026) | **LOCKED: 24 LTS** | Node 26 is Current, not yet LTS. `.nvmrc` pins 24 |
| **pnpm** | Latest | **12.4.2** (installed Sep 2026) | **LOCKED: 12.4.2** | ✓ |
| **Docker** | 29+ | 29.8.1 (installed) | **KEEP** | ✓ |
| **Docker Compose** | v2+ | v5.5.1 (installed) | **KEEP** | ✓ |
| **Nginx** | Latest stable | 1.27.x | **Pin in Docker** | ✓ |
| **GitHub Actions** | ubuntu-24.04 | ubuntu-24.04 available | **Pin runner** | ✓ |
| **Playwright** | Latest | 1.x | **Pin in package.json** | ✓ |
| **Local S3 Storage** | MinIO (original) → SeaweedFS | SeaweedFS (current, maintained) | **LOCKED: SeaweedFS** | LocalStack requires auth token; MinIO CE changed distro. See ADR-011, CHANGE-003 updated |

---

## Node.js Version Management

The machine currently has Node 26.8.1 (Current release). The project pins to **Node 24 LTS**.

Node version management strategy: **nvm** (if installed) or version pinning via `.nvmrc`.

```
# .nvmrc content
24
```

Developers should run `nvm use` in the repository root if nvm is available.
Docker-based builds use explicitly pinned `node:24-bookworm-slim` images.

**DO NOT uninstall system Node 26** — use `.nvmrc` for project isolation.

---

## Version Pinning Policy

- **Composer:** `composer.lock` committed — exact transitive versions locked
- **Python:** `uv.lock` committed — exact transitive versions locked
- **Node/pnpm:** `pnpm-lock.yaml` committed — exact versions locked
- **Docker:** Explicit version tags — never `latest` — pinned to `image:major.minor-distro`
- **GitHub Actions:** `@vN` tag minimum; SHA pinning for security-critical steps (Dependabot manages updates)

---

## Redis Version Rationale

Redis 8.0 reaches End of Support on **December 1, 2026** — approximately 11 weeks from the project bootstrap date. Starting a new project on a release that expires in weeks would require an immediate forced upgrade before meaningful production use.

Redis 8.2 is the **Extended release** supported through **September 2030**, providing a stable five-year horizon appropriate for a new project.

Laravel Horizon is fully compatible with Redis 8.2.

---

## PostgreSQL Version Rationale

PostgreSQL 18.6 is the current stable minor of the 18.x major. It is supported through November 2030. Prompt 01's suggestion of PostgreSQL 17 for "proven stability" was incorrect — the locked specification always required 18.x, and 18.6 is the appropriate pinned version.
