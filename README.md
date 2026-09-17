# ImportPilot

Deterministic, developer-focused data-import infrastructure for production SaaS applications.

> **Status:** Pre-release — under active development. Not yet suitable for production use.
> **License:** No open-source license has been selected. All rights reserved pending licensing decision.

---

## What is ImportPilot?

ImportPilot is a structured data-import platform designed to give developers and their end-users a reliable, auditable path from raw spreadsheet or CSV input to clean, validated, application-ready data.

Core design principles:

- **Deterministic first** — mapping and validation decisions are rule-based and auditable, not probabilistic black boxes.
- **Immutable source preservation** — original uploaded files are never mutated; all corrections are applied as an explicit patch layer.
- **Tenant isolation** — import sessions, schemas, and artifacts are strictly scoped to their originating organization.
- **Separation of concerns** — a Laravel control plane handles auth, projects, and lifecycle; a Python data engine handles all heavy parsing and validation; a React importer handles the end-user experience.

---

## Repository Structure

```
importpilot/
├── apps/
│   ├── control-plane/     # Laravel: auth, orgs, projects, import lifecycle, billing readiness
│   ├── data-engine/       # Python/FastAPI: parsing, profiling, validation, Parquet artifacts
│   └── importer-web/      # React/TypeScript: embeddable importer UI
│
├── packages/
│   ├── laravel-sdk/       # PHP SDK for customer application integration
│   ├── python-sdk/        # Python SDK for customer application integration
│   ├── schema-spec/       # Canonical, language-neutral schema specification
│   └── test-fixtures/     # Shared test data and fixtures (non-sensitive)
│
├── infra/
│   ├── docker/            # Dockerfile and Compose definitions
│   ├── nginx/             # Reverse-proxy configuration
│   └── scripts/           # Bootstrap and operational scripts
│
├── docs/
│   ├── architecture/      # Architecture documentation and diagrams
│   ├── api/               # API reference documentation
│   ├── bootstrap/         # Developer environment setup guides
│   ├── decisions/         # Architecture Decision Records (ADRs)
│   └── security/          # Security policies and threat model notes
│
└── .github/
    └── workflows/         # GitHub Actions CI/CD pipelines
```

---

## Technology Baseline

| Layer | Technology |
|---|---|
| Control plane | Laravel (PHP) |
| Data engine | Python, FastAPI, Polars, PyArrow |
| Importer UI | React, TypeScript, Vite |
| Database | PostgreSQL |
| Cache / queue broker | Redis + Celery |
| Object storage | S3-compatible (abstracted) |
| Local development | Docker Compose |

Specific version pins are maintained in per-service dependency lock files.

---

## Architecture Decision Records

ADRs documenting key architectural decisions are located in [`docs/decisions/`](docs/decisions/).

Current ADR index:

| ADR | Status | Topic |
|---|---|---|
| ADR-001 | Draft | Monorepo and service boundaries |
| ADR-002 | Draft | Laravel vs Python responsibilities |
| ADR-003 | Draft | Canonical schema specification |
| ADR-004 | Draft | Import state machine and idempotency |
| ADR-005 | Draft | Immutable original + patch corrections |
| ADR-006 | Draft | Object storage and artifact lifecycle |
| ADR-007 | Draft | Mapping confidence and evidence model |
| ADR-008 | Draft | AI-optional policy |

---

## Security

See [`SECURITY.md`](SECURITY.md) for vulnerability reporting instructions.

This repository is public. It must never contain credentials, tokens, customer data, or private deployment configuration. See [`.gitignore`](.gitignore) for what is permanently excluded.

---

## Contributing

Contribution guidelines will be published when the project reaches an appropriate stage. The project is not yet accepting external contributions.

---

## License

No open-source license has been selected for this project. The absence of a license means all rights are reserved by the author(s). This repository is public for transparency during development, not as an open-source release. Licensing decisions will be documented when made.

---

*ImportPilot is a working codename. The brand and domain name are subject to change.*
