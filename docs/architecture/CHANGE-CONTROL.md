# Architecture Change Control

This document records all proposed or approved deviations from the locked
ImportPilot Master Project Specification (LOCKED v1.0, 17 September 2026).

No unapproved change may enter production code.

Changes are recorded in the order they are identified.

---

## CHANGE-001 — Repository Visibility: Private → Public

```
Current locked decision:  Repository remains private during development / pre-beta.
Problem:                  User has explicitly requested the repository to be created
                          as public during the bootstrap phase to enable open
                          development visibility.
Evidence:                 User explicit approval — Prompt 01, Section 1.
Alternatives:
  A. Keep private (locked default) — rejected by user.
  B. Create as public immediately — approved by user.
Recommended decision:     Create as public (B).
Security impact:          LOW if .gitignore and hygiene are correct. The repository
                          must NEVER contain credentials, secrets, customer data,
                          private keys, deployment credentials, or the full private
                          Master Project Specification. Git history is permanently
                          public. Making the repository private later does NOT erase
                          data that has already been cloned, cached, or mirrored.
Compatibility impact:     None.
Performance impact:       None.
Operations impact:        Public visibility increases scrutiny of committed code.
                          This is a net positive for quality.
Migration impact:         None — this is the initial creation.
User approval required:   Already approved.
Status:                   APPROVED — 2026-09-17
```

---

## CHANGE-002 — Laravel Version: 12 → 13

```
Current locked decision:  Specification references Laravel (implied latest stable
                          at time of spec writing). Assumed Laravel 12 at spec date
                          of September 2026 if not explicitly stated.
Problem:                  Laravel 12's active support (bug fixes) ended August 13,
                          2026. Only security fixes remain until February 2027.
                          Laravel 13 was released March 17, 2026 and is the current
                          actively supported major version.
Evidence:
  - laravel.com release schedule (September 2026)
  - Laravel 12 end-of-active-support: August 13, 2026
  - Laravel 13 minimum PHP: 8.3 (machine has PHP 8.4.25 — compatible)
Alternatives:
  A. Use Laravel 12 (locked baseline) — receives security fixes only through
     Feb 2027. Acceptable but accumulates technical debt from day one.
  B. Use Laravel 13 (current actively supported) — full bug fix support
     through Q3 2027, security through Q1 2028.
Recommended decision:     Laravel 13 (B).
Security impact:          POSITIVE — Laravel 13 receives active security patches.
                          Laravel 12 security-only window starts a long-lived
                          project on a declining support curve.
Compatibility impact:     PHP 8.3 minimum (met: machine has PHP 8.4.25).
                          Laravel 13 breaking changes vs 12 must be reviewed
                          during app scaffolding.
Performance impact:       Laravel 13 includes performance improvements.
Operations impact:        None.
Migration impact:         N/A — no application code exists yet.
User approval required:   YES — requires user sign-off before app scaffolding.
Status:                   PROPOSED — awaiting user approval
```

---

## CHANGE-003 — Object Storage: MinIO → LocalStack (local development only)

```
Current locked decision:  Specification uses MinIO as the local-development
                          S3-compatible object storage server.
Problem:                  MinIO community edition has moved to a source-only
                          distribution model (2025-2026), removing pre-compiled
                          binaries and changing Docker image availability for the
                          community edition. This makes reliable Docker-based
                          local development with MinIO significantly more complex.
Evidence:
  - Multiple community reports (GitHub, HN, 2025-2026) documenting MinIO's
    community edition distribution change.
  - MinIO now requires building from source or using a commercial image
    for the full feature set in the community tier.
Alternatives:
  A. Continue with MinIO — requires building from source or navigating
     changed image distribution. Acceptable but adds friction.
  B. LocalStack community edition — actively maintained, Docker-native,
     industry-standard AWS service emulation including S3, presigned URLs.
     Well-documented. Does not require external accounts for S3-only use.
  C. Garage — Rust-based, lightweight S3 server. Excellent for self-hosted
     but less documentation for Docker-Compose dev workflows.
Recommended decision:     LocalStack community edition (B) for local development.
                          Production object storage remains vendor-agnostic
                          (S3-compatible managed service). The application
                          abstraction layer (Flysystem / boto3) is unchanged.
Security impact:          No change — credentials remain in .env, never committed.
Compatibility impact:     LocalStack implements the S3 API surface required
                          (PutObject, GetObject, DeleteObject, presigned URLs,
                          bucket operations). Must verify specific features
                          needed (multipart upload, if required) before Phase 1.
Performance impact:       None for local development.
Operations impact:        LocalStack requires a Docker container in Compose.
                          Simpler than building MinIO from source.
Migration impact:         None — no storage code exists yet. Environment variable
                          pointing to S3 endpoint URL changes from MinIO port
                          to LocalStack port. Application code is unaffected.
User approval required:   YES — requires user sign-off before Compose definition.
Status:                   PROPOSED — awaiting user approval
```

---

## CHANGE-004 — Message Broker: Redis-only → RabbitMQ as primary broker (Celery)

```
Current locked decision:  Specification uses Redis as the Celery broker.
Problem:                  ImportPilot will execute long-running Celery tasks:
                          XLSX parsing, large CSV processing, profiling,
                          validation, Parquet generation, and duplicate analysis.
                          Redis as a Celery broker uses a visibility_timeout
                          mechanism. If a task exceeds the configured
                          visibility_timeout, Redis assumes the worker crashed
                          and redelivers the task to another worker. This causes
                          duplicate task execution for long-running jobs unless
                          visibility_timeout is tuned to exceed the maximum
                          possible task duration — which in turn delays recovery
                          from genuine worker crashes.
Evidence:
  - Celery documentation: visibility_timeout risk for long-running tasks.
  - Research comparison (September 2026): RabbitMQ provides native AMQP
    acknowledgement semantics — a message stays in flight until the worker
    explicitly ACKs. No visibility timeout problem.
  - Industry consensus: RabbitMQ is the recommended broker for
    business-critical, long-running Celery workloads.
Alternatives:
  A. Redis only (locked baseline) — requires careful visibility_timeout
     tuning. Risk of duplicate task execution if a task exceeds timeout.
     Simpler operationally (one fewer service).
  B. RabbitMQ as primary Celery broker — native AMQP delivery guarantees,
     no visibility timeout problem, better suited for long-running tasks,
     supports task routing to priority queues. Adds RabbitMQ to the
     infrastructure stack.
  C. Redis + explicit idempotency guards — keep Redis but implement
     task-level idempotency so duplicates are safe. Still requires careful
     visibility_timeout. Adds development burden.
Recommended decision:     RabbitMQ as primary Celery broker (B).
                          Redis is retained for Laravel cache, Laravel sessions,
                          and potentially Celery result backend.
Security impact:          RabbitMQ requires credentials (user/password).
                          These must be environment-variable-driven. Never
                          hardcoded in committed Compose files.
Compatibility impact:     Celery supports RabbitMQ natively via amqp/kombu.
                          No Python code change required at the broker-
                          configuration level.
Performance impact:       RabbitMQ adds minor latency vs Redis for task dispatch,
                          negligible for long-running tasks where dispatch
                          latency is irrelevant.
Operations impact:        RabbitMQ adds one additional service to Compose.
                          RabbitMQ Management UI (port 15672) provides
                          built-in queue observability — a significant
                          operational advantage over Redis for debugging.
Migration impact:         N/A — no worker code exists yet.
User approval required:   YES — requires user sign-off before Compose definition.
Status:                   PROPOSED — awaiting user approval
```

---

## CHANGE-005 — Node.js Version: Unspecified → Node.js 24 LTS

```
Current locked decision:  Specification requires an appropriate Node LTS.
                          Machine has Node.js 26.8.1 (non-LTS Current).
Problem:                  Node.js 26 is currently the "Current" release channel,
                          not yet LTS (scheduled for October 2026 LTS transition).
                          Node.js 24 is the current Active LTS.
                          Node.js 22 is also Active LTS but older.
Evidence:
  - nodejs.org release schedule (September 2026): Node 24 = Active LTS,
    Node 26 = Current (not yet LTS as of 2026-09-17).
Alternatives:
  A. Use system Node 26.8.1 (installed) — not yet LTS as of this date.
     Will become LTS in October 2026. Acceptable risk for dev-only,
     but not the recommended production baseline.
  B. Use Node 24 LTS via nvm/volta — the stable Active LTS choice.
  C. Use Node 22 LTS — older Active LTS, also valid but nearing end of
     its active window relative to 24.
Recommended decision:     Pin to Node.js 24 LTS (B) for the importer-web
                          package.json engines field and Docker image.
                          The developer may use nvm to switch locally.
                          Node 26 may be reconsidered after it reaches LTS
                          in October 2026.
Security impact:          Node 24 LTS receives security patches. Using a
                          non-LTS Current build in production is inadvisable.
Compatibility impact:     All frontend tooling (Vite, TypeScript, pnpm)
                          is compatible with Node 24.
Performance impact:       Negligible difference for build tooling.
Operations impact:        Docker images will pin to Node 24. nvm/.nvmrc
                          will specify 24.x for developers.
Migration impact:         N/A — no frontend code exists yet.
User approval required:   YES — requires user sign-off before node version pinning.
Status:                   PROPOSED — awaiting user approval
```

---

## CHANGE-006 — PHP Extension Gap: pdo_pgsql Not Installed

```
Current locked decision:  PostgreSQL is the primary database. Laravel's PDO
                          driver for PostgreSQL (pdo_pgsql) is required.
Problem:                  Machine has PHP 8.4.25 but pdo_pgsql / pgsql extensions
                          are NOT installed. apt-cache confirms the package
                          php8.4-pgsql is available but not installed.
Evidence:
  - php -m output: pdo_mysql and pdo_sqlite present; pdo_pgsql absent.
  - apt-cache search php8.4-pgsql: package exists in repository.
Action required:          Install php8.4-pgsql before Laravel scaffolding.
                          Command: sudo apt-get install -y php8.4-pgsql
                          This is a SAFE, non-destructive installation.
                          No existing PHP configuration is altered.
Alternatives:
  A. Install php8.4-pgsql (recommended).
  B. Use only Docker-based PHP for database work (avoids host install but
     complicates artisan commands run on host).
Recommended decision:     Install php8.4-pgsql on host (A) for full local
                          artisan/migration workflow.
Security impact:          None.
Compatibility impact:     Required for Laravel PostgreSQL connection.
Performance impact:       None.
Operations impact:        Must be installed before Phase 1 app scaffolding.
Migration impact:         N/A.
User approval required:   INFORMATIONAL — this is a missing prerequisite,
                          not an architectural change. Flagged for user awareness.
Status:                   IDENTIFIED — to be installed before Phase 1
```

---

## CHANGE-007 — pnpm Not Installed

```
Current locked decision:  Specification implies pnpm for the Node.js workspace.
Problem:                  pnpm is not installed on the machine.
                          Node.js 26.8.1 is installed. corepack is not available.
Evidence:
  - pnpm --version: command not found.
  - corepack --version: command not found.
Action required:          Install pnpm before importer-web scaffolding.
                          Recommended: npm install -g pnpm
                          Or: corepack enable && corepack prepare pnpm@latest --activate
Recommended decision:     Install pnpm via npm install -g pnpm before Phase 1.
                          This is a SAFE, non-destructive operation.
User approval required:   INFORMATIONAL — missing prerequisite.
Status:                   IDENTIFIED — to be installed before Phase 1
```
