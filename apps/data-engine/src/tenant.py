from __future__ import annotations


class TenantScopeError(ValueError):
    pass


def assert_tenant_scoped_key(storage_key: str, project_id: str | None = None) -> None:
    if not storage_key or storage_key.startswith("/") or ".." in storage_key.split("/"):
        raise TenantScopeError("Invalid storage key")
    if not storage_key.startswith("sources/"):
        raise TenantScopeError("Artifact key is not tenant-scoped")
    if project_id and not storage_key.startswith(f"sources/{project_id}/"):
        raise TenantScopeError("Artifact key does not match project")


def parquet_key_for(session_id: str, project_id: str | None = None) -> str:
    if project_id:
        return f"intermediate/{project_id}/{session_id}/data.parquet"
    return f"intermediate/{session_id}/data.parquet"
