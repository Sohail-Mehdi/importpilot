# Tenant Scoping Matrix

**Status:** Current  
**Date:** 2026-09-17

This matrix defines the strict ownership and authorization requirements for all persistent entities in the ImportPilot data model.

> **CRITICAL RULE:** No entity may be accessed via an ID alone. The request context MUST prove authorization through the entity's owning `organization_id` or `project_id`.

| Entity | Scoped To | Authorization Root | Access Path | Deletion Implications |
|---|---|---|---|---|
| `organizations` | Global | Organization members | Direct (if member) | Hard delete removes ALL tenant data. |
| `organization_members`| `organization_id` | Organization | `org -> members` | Removes user access, keeps audit logs. |
| `projects` | `organization_id` | Organization | `org -> project` | Soft delete; cascades to API keys and imports. |
| `api_keys` | `project_id` | Project | `org -> project -> keys` | Hard delete; instantly invalidates integration. |
| `schemas` | `project_id` | Project | `org -> project -> schemas` | Soft delete; preserves historical import contracts. |
| `import_sessions` | `project_id` | Project | `org -> project -> imports` | Cascades to jobs, artifacts, mappings. |
| `import_jobs` | `import_id` | Project via Import | `import -> jobs` | Lifecycle managed by control plane. |
| `import_artifacts` | `import_id` | Project via Import | `import -> artifacts` | Subject to retention policy; S3 objects deleted async. |
| `import_corrections`| `import_id` | Project via Import | `import -> corrections` | Immutable patch log; never hard deleted if import exists. |
| `webhook_endpoints` | `project_id` | Project | `org -> project -> webhooks`| Hard delete; stops active deliveries. |
| `retention_policies`| `project_id` | Project | `org -> project -> policies`| Single active policy per project. |

## Opaque Identifiers
Storage keys for `import_artifacts` must use opaque UUIDs and must not embed the original filename. The S3 path structure is:
`{environment}/{organization_id}/{project_id}/{import_id}/{artifact_type}/{opaque_artifact_id}`

## Tenant Isolation Tests
Before any release, the CI pipeline must pass automated tests attempting IDOR (Insecure Direct Object Reference) against every entity above.
