# Architecture Change Control

This document records all proposed or approved deviations from the locked
ImportPilot Master Project Specification (LOCKED v1.0, 17 September 2026).

**Policy:** No unapproved change may enter production code or committed
architecture. Changes are recorded in discovery order with full evidence.

---

## CHANGE-001 — Repository Visibility: Private → Public

```
Current locked decision:  Repository remains private during development/pre-beta.
Problem:                  User explicitly requested public visibility during bootstrap.
Evidence:                 User explicit approval — Prompt 01, Section 1.
Alternatives:
  A. Keep private (locked default) — rejected by user.
  B. Create as public immediately — approved.
Recommended decision:     Public (B).
Security impact:          LOW if .gitignore hygiene is correct. Git history is
                          permanently public. Private-later does NOT erase cached
                          clones/forks.
Compatibility impact:     None.
Operations impact:        Public scrutiny improves quality.
Migration impact:         None — initial creation.
User approval required:   ALREADY APPROVED.
Status:                   APPROVED — 2026-09-17
```

---

## CHANGE-002 — SUPERSEDED / CORRECTED (Prompt 02)

> **This change proposal was incorrect and is superseded.**
> The locked specification ALREADY requires Laravel 13.x.
> There is no baseline Laravel 12 for this project.
> Laravel 13 is the locked version — not a proposed upgrade.
> This change entry is retained for audit continuity.

```
Original (incorrect) description: Laravel 12 → Laravel 13 migration
Correct statement:               Laravel 13.x is the locked specification baseline.
                                 Released March 17 2026. Active support through
                                 Q3 2027. Security support through Q1 2028.
                                 PHP 8.4.25 (installed) meets minimum PHP 8.3.
Status:                          SUPERSEDED — Laravel 13 is LOCKED, not proposed.
```

---

## CHANGE-003 — Local S3 Storage: MinIO → SeaweedFS

```
Current locked decision:  Original spec referenced MinIO as local-dev S3 storage.
                          Prompt 01 proposed LocalStack as replacement.
Problem:
  MinIO CE: No longer provides pre-compiled binaries (2025-2026 distribution change).
  LocalStack: Current releases require an authenticated account/token to run,
              creating an external dependency and account concern for a commercial
              project that must not depend on external services for local development.
Evidence:
  - MinIO community binary distribution changes: multiple community reports (2025-2026).
  - LocalStack auth requirement: verified from localstack.cloud documentation.
  - SeaweedFS: actively maintained, MIT licensed, Docker-native, full S3 API support
    including presigned URLs, multipart upload, CORS, bucket operations.
    No external authentication required for local development.
Alternatives:
  A. MinIO (original) — distribution model changed, complex to set up.
  B. LocalStack — requires external account/token (unacceptable dependency).
  C. SeaweedFS (recommended) — maintained, self-contained, MIT, full S3 surface.
  D. Garage — Rust-based, lightweight, actively maintained. Good alternative but
     less ecosystem documentation for Docker Compose dev workflows than SeaweedFS.
Recommended decision:     SeaweedFS (C) for local development S3.
                          Production storage remains abstract S3-compatible.
Security impact:          SeaweedFS runs locally, no external accounts needed.
                          Credentials remain in .env, never committed.
Compatibility impact:     boto3 and Flysystem S3 work with SeaweedFS S3 endpoint.
                          Path-style addressing required (not virtual-hosted).
Operations impact:        One Docker container, one config file. Simple.
Migration impact:         None — no storage code written yet.
User approval required:   YES — PROPOSED, awaiting user approval.
Status:                   PROPOSED — 2026-09-17
```

---

## CHANGE-004 — Celery Broker: Redis → RabbitMQ (as primary)

```
Current locked decision:  Celery + Redis broker.
Problem:                  Long-running ImportPilot jobs (XLSX parsing, Parquet
                          generation, validation, duplicate analysis) exceed Redis
                          broker's visibility_timeout window, causing duplicate
                          task execution when a worker is slow but not crashed.
Evidence:
  - Celery docs: visibility_timeout must exceed longest task duration.
  - Research comparison: RabbitMQ AMQP provides true per-message ACK; message
    stays in-flight until worker explicitly ACKs. No timeout-based redelivery.
  - Redis 8.2 retained for: Laravel cache, Horizon queue monitoring, session
    backend where applicable.
Alternatives:
  A. Redis only — requires visibility_timeout >> max task duration. If a
     worker genuinely crashes, recovery is delayed by the inflated timeout.
     Risk of duplicate execution for long-running tasks.
  B. RabbitMQ as Celery broker — native AMQP ACK semantics, no visibility
     timeout problem. Adds one service. Queue routing for priority lanes.
  C. Redis + mandatory idempotency guards everywhere — acceptable but adds
     significant development burden without solving the fundamental timeout
     problem.
Recommended decision:     RabbitMQ as Celery broker (B). Redis retained for
                          Laravel infrastructure. See ADR-009 for full analysis.
Security impact:          RabbitMQ requires credentials. Environment-variable
                          driven. Non-default vhost and credentials required.
Compatibility impact:     Celery supports RabbitMQ natively. No Python changes.
Operations impact:        +1 Docker service. Management UI provides observability.
Migration impact:         None — no worker code exists yet.
User approval required:   YES — PROPOSED, awaiting user approval.
Status:                   PROPOSED — 2026-09-17
```

---

## CHANGE-005 — Node.js: System Node 26 → Project Node 24 LTS

```
Current locked decision:  Spec requires appropriate LTS. Machine has Node 26.8.1.
Problem:                  Node 26 is Current release channel — not yet LTS
                          as of 2026-09-17. Node 24 is Active LTS.
Evidence:
  - nodejs.org release schedule: Node 24 = Active LTS (Sep 2026).
  - Node 26 LTS transition scheduled October 2026.
Alternatives:
  A. System Node 26.8.1 — not LTS yet. Becomes LTS Oct 2026.
  B. Node 24 LTS — active LTS, recommended for production.
  C. Node 22 LTS — older active LTS.
Recommended decision:     Pin project to Node 24 LTS (B) via .nvmrc.
                          DO NOT remove user's system Node 26.
Security impact:          Node 24 receives active security patches.
Compatibility impact:     All frontend tooling (Vite, TypeScript, pnpm) compat.
Migration impact:         None — no frontend code written yet.
User approval required:   YES — PROPOSED, awaiting user approval.
Status:                   PROPOSED — 2026-09-17
```

---

## CHANGE-006 — PHP Extension: pdo_pgsql Not Installed

```
Current locked decision:  PostgreSQL is primary database. pdo_pgsql required.
Problem:                  php8.4-pgsql package is NOT installed on host.
Evidence:
  - php -m: pdo_pgsql absent (verified Prompt 01 and Prompt 02).
  - apt-cache search php8.4-pgsql: package available.
Action:                   sudo apt-get install -y php8.4-pgsql
                          SAFE — additive, no existing config altered.
Note:                     Agent cannot execute sudo commands. User must run.
User approval required:   INFORMATIONAL — missing prerequisite.
Status:                   IDENTIFIED — USER ACTION REQUIRED before Phase 1.
```

---

## CHANGE-007 — pnpm Installation

```
Current locked decision:  pnpm required for frontend workspace.
Problem:                  pnpm was not installed.
Action taken:             npm install -g pnpm → pnpm 12.4.2 installed.
Status:                   RESOLVED — 2026-09-17 (pnpm 12.4.2 active)
```

---

## CHANGE-008 — PHP Version: 8.4 vs 8.5 Evaluation

```
Current locked decision:  PHP 8.4.x (machine has 8.4.25).
Problem:                  PHP 8.5 may offer a longer support horizon.
                          Laravel 13 supports PHP 8.3+.
Evidence:
  - PHP 8.4 EOL: December 2028.
  - PHP 8.5 (if released in late 2026 per PHP release cadence): would be EOL ~2029.
  - Not enough active-production evidence for 8.5 at project start.
Recommended decision:     RETAIN PHP 8.4.x — stable, installed, meets Laravel 13
                          requirements. Revisit when PHP 8.5 has 6+ months of
                          production track record and clear ecosystem support.
User approval required:   INFORMATIONAL — no change proposed now.
Status:                   DEFERRED — reviewed and rejected for now. Retain 8.4.x.
```

---

## CHANGE-009 — Redis: 8.0 → 8.2 (EOL Risk)

```
Current locked decision:  Redis 8.x.
Problem:                  Redis 8.0 reaches End of Support December 1 2026 —
                          approximately 11 weeks from project bootstrap. Starting
                          a production system on an imminently expiring release
                          violates the project's long-term maintainability policy.
Evidence:
  - redis.io release schedule: Redis 8.0 EOS = Dec 1 2026.
  - Redis 8.2 = Extended release, EOS = September 2030.
  - Laravel Horizon: fully compatible with Redis 8.2.
Alternatives:
  A. Redis 8.0 — expires Dec 2026. Immediate forced upgrade within months.
  B. Redis 8.2 — Extended release, Sep 2030 EOS. Laravel Horizon compatible.
Recommended decision:     Redis 8.2 (B). The spec says "Redis 8.x" and 8.2
                          is correct per the spec's intent. This is a minor
                          clarification, not a major version change.
Security impact:          Redis 8.2 receives security patches through 2030.
Compatibility impact:     8.2 is backward compatible with 8.0 for the
                          standard data structures ImportPilot uses.
Operations impact:        None.
User approval required:   INFORMATIONAL — within the locked "8.x" spec.
Status:                   ACCEPTED — 2026-09-17 (within locked 8.x range)
```

---

## CHANGE-010 — Python Project Runtime: 3.12 → 3.13.15

```
Current locked decision:  Master Specification requires Python 3.13.x.
System state:             System Python is 3.12.3.
Resolution:               uv provisions project-isolated Python 3.13.15.
                          System Python 3.12.3 is NEVER modified.
Status:                   RESOLVED — Python 3.13.15 installed via uv.
```
