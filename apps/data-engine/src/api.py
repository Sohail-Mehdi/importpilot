from __future__ import annotations

import logging
from datetime import datetime, timezone
from uuid import uuid4

from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field, model_validator

from .config import settings
from .tasks import inspect_file
from .tenant import TenantScopeError, assert_tenant_scoped_key

logger = logging.getLogger("data_engine.api")

app = FastAPI(title="ImportPilot Data Engine")


class InspectJob(BaseModel):
    storage_key: str
    content_type: str
    callback_url: str
    session_id: str | None = None
    import_id: str | None = None
    internal_token: str | None = None
    contract_version: str = "1.0"
    job_id: str | None = None
    organization_id: str | None = None
    project_id: str | None = None
    operation: str = "inspect"
    attempt: int = Field(default=1, ge=1)
    idempotency_key: str | None = None
    requested_at: str | None = None

    @model_validator(mode="after")
    def resolve_session(self):
        if not self.session_id and not self.import_id:
            raise ValueError("session_id or import_id is required")
        if not self.session_id:
            self.session_id = self.import_id
        if not self.import_id:
            self.import_id = self.session_id
        if self.operation != "inspect":
            raise ValueError("operation must be inspect")
        return self


def _extract_bearer(authorization: str | None) -> str | None:
    if not authorization:
        return None
    prefix = "Bearer "
    if authorization.startswith(prefix):
        return authorization[len(prefix) :]
    return None


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/api/v1/jobs/inspect")
def trigger_inspection(
    job: InspectJob,
    authorization: str | None = Header(default=None),
):
    presented = _extract_bearer(authorization) or job.internal_token
    if presented != settings.LARAVEL_INTERNAL_TOKEN:
        raise HTTPException(status_code=401, detail="Unauthorized")

    try:
        assert_tenant_scoped_key(job.storage_key, job.project_id)
    except TenantScopeError as exc:
        raise HTTPException(status_code=403, detail=str(exc)) from exc

    job_id = job.job_id or str(uuid4())
    idempotency_key = job.idempotency_key or f"inspect:{job.session_id}"
    requested_at = job.requested_at or datetime.now(timezone.utc).isoformat()

    logger.info(
        "queued inspect job",
        extra={
            "job_id": job_id,
            "session_id": job.session_id,
            "project_id": job.project_id,
            "operation": job.operation,
            "attempt": job.attempt,
        },
    )

    task = inspect_file.delay(
        job.session_id,
        job.storage_key,
        job.content_type,
        job.callback_url,
        job.project_id,
        job_id,
        idempotency_key,
        job.attempt,
        requested_at,
        job.contract_version,
        job.organization_id,
    )
    return {
        "task_id": task.id,
        "status": "QUEUED",
        "job_id": job_id,
        "idempotency_key": idempotency_key,
        "contract_version": job.contract_version,
    }
