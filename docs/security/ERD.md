# ImportPilot Entity Relationship Diagram & Integrity Constraints

**Status:** Current  
**Date:** 2026-09-19 (Remediation)

## ERD (Mermaid)

```mermaid
erDiagram
    ORGANIZATION ||--o{ ORGANIZATION_MEMBER : has
    ORGANIZATION ||--o{ PROJECT : owns
    PROJECT ||--o{ API_KEY : authorizes
    PROJECT ||--o{ SCHEMA : defines
    PROJECT ||--o{ WEBHOOK_ENDPOINT : dispatches
    PROJECT ||--o| RETENTION_POLICY : configures
    PROJECT ||--o{ IMPORT_SESSION : processes
    IMPORT_SESSION ||--o{ IMPORT_JOB : executes
    IMPORT_SESSION ||--o{ IMPORT_ARTIFACT : stores
    IMPORT_SESSION ||--o{ IMPORT_CORRECTION : applies

    ORGANIZATION {
        uuid id PK
        string name
    }
    PROJECT {
        uuid id PK
        uuid organization_id FK
        string name
    }
    IMPORT_SESSION {
        uuid id PK
        uuid project_id FK
        uuid schema_id FK "nullable"
        string state
    }
    IMPORT_JOB {
        uuid id PK
        uuid import_id FK
        string operation
        string state
    }
    IMPORT_ARTIFACT {
        uuid id PK
        uuid import_id FK
        string type
        string s3_key
    }
    API_KEY {
        uuid id PK
        uuid project_id FK
        string key_hash
    }
```

## Database Integrity Constraints

1. **Foreign Key Cascades:**
   - `organizations` deletion cascades to `projects` and `organization_members`.
   - `projects` deletion cascades to `api_keys`, `schemas`, `webhook_endpoints`, and `import_sessions`.
   - `import_sessions` deletion cascades to `import_jobs`, `import_artifacts`, and `import_corrections`.

2. **Tenant Scoping Enforcement (Application Level):**
   - Opaque IDs (UUIDv7) are used for all primary keys.
   - Every API request targeting an `import_session`, `schema`, or `api_key` MUST implicitly filter by the authenticated `project_id` associated with the request token.

3. **Unique Constraints:**
   - `(project_id, schema_name)` must be unique.
   - `(import_id, attempt)` in `import_jobs` must be unique to prevent race condition duplicates.
