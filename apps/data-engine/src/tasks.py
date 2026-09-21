from __future__ import annotations

import logging
import os
import tempfile
from pathlib import Path

import boto3
import polars as pl
import pyarrow.parquet as pq
import requests
from celery.exceptions import MaxRetriesExceededError

from .celery_app import celery_app
from .config import settings
from .profiler import process_csv_detailed, process_xlsx_detailed, sink_csv_to_parquet, write_parquet
from .tenant import TenantScopeError, assert_tenant_scoped_key, parquet_key_for

logger = logging.getLogger("data_engine.tasks")

s3_client = boto3.client(
    "s3",
    aws_access_key_id=settings.AWS_ACCESS_KEY_ID,
    aws_secret_access_key=settings.AWS_SECRET_ACCESS_KEY,
    endpoint_url=settings.AWS_ENDPOINT_URL,
    region_name=settings.AWS_REGION,
)


def _stream_s3_to_temp(storage_key: str) -> str:
    obj = s3_client.get_object(Bucket=settings.AWS_BUCKET, Key=storage_key)
    body = obj["Body"]
    fd, path = tempfile.mkstemp(prefix="importpilot-s3-")
    try:
        with os.fdopen(fd, "wb") as handle:
            while True:
                chunk = body.read(settings.S3_STREAM_CHUNK_BYTES)
                if not chunk:
                    break
                handle.write(chunk)
    except Exception:
        Path(path).unlink(missing_ok=True)
        raise
    return path


def _callback(callback_url: str, payload: dict) -> None:
    resp = requests.post(
        callback_url,
        json=payload,
        headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"},
        timeout=15,
    )
    resp.raise_for_status()


def _is_spreadsheet(content_type: str) -> bool:
    return "spreadsheetml" in content_type or content_type.endswith("sheet")


@celery_app.task(bind=True, max_retries=3, acks_late=True)
def inspect_file(
    self,
    session_id: str,
    storage_key: str,
    content_type: str,
    callback_url: str,
    project_id: str | None = None,
    job_id: str | None = None,
    idempotency_key: str | None = None,
    attempt: int = 1,
    requested_at: str | None = None,
    contract_version: str = "1.0",
    organization_id: str | None = None,
):
    src_path = None
    parquet_path = None
    try:
        assert_tenant_scoped_key(storage_key, project_id)
        logger.info(
            "inspect started",
            extra={
                "session_id": session_id,
                "job_id": job_id,
                "project_id": project_id,
                "attempt": self.request.retries + 1,
            },
        )
        src_path = _stream_s3_to_temp(storage_key)

        if _is_spreadsheet(content_type):
            df, profile, inspect_meta = process_xlsx_detailed(src_path)
            fd, parquet_path = tempfile.mkstemp(prefix="importpilot-parquet-", suffix=".parquet")
            os.close(fd)
            write_parquet(df, parquet_path)
        else:
            separator = "\t" if content_type == "text/tab-separated-values" else ","
            fd, parquet_path = tempfile.mkstemp(prefix="importpilot-parquet-", suffix=".parquet")
            os.close(fd)
            sink_csv_to_parquet(src_path, parquet_path, separator=separator)
            df, profile, inspect_meta = process_csv_detailed(src_path, content_type)

        table = pq.read_table(parquet_path)
        if table.num_rows != inspect_meta["row_count"]:
            raise RuntimeError("Parquet row count does not match inspection")

        parquet_key = parquet_key_for(session_id, project_id)
        with open(parquet_path, "rb") as handle:
            s3_client.put_object(
                Bucket=settings.AWS_BUCKET,
                Key=parquet_key,
                Body=handle,
                ContentType="application/vnd.apache.parquet",
            )

        payload = {
            "session_id": session_id,
            "status": "COMPLETED",
            "profile": profile,
            "inspect": inspect_meta,
            "parquet_key": parquet_key,
            "job_id": job_id,
            "idempotency_key": idempotency_key,
            "contract_version": contract_version,
            "attempt": attempt,
            "organization_id": organization_id,
            "project_id": project_id,
            "engine": {"polars": pl.__version__, "pyarrow": "parquet"},
        }
        _callback(callback_url, payload)
        logger.info(
            "inspect completed",
            extra={"session_id": session_id, "job_id": job_id, "parquet_key": parquet_key},
        )
        return {
            "session_id": session_id,
            "status": "COMPLETED",
            "parquet_key": parquet_key,
            "row_count": inspect_meta["row_count"],
            "file_type": inspect_meta["file_type"],
        }
    except Exception as exc:
        logger.exception(
            "inspect failed",
            extra={"session_id": session_id, "job_id": job_id, "error_type": type(exc).__name__},
        )
        if isinstance(exc, TenantScopeError):
            _safe_fail_callback(callback_url, session_id, str(exc), job_id, idempotency_key)
            raise
        try:
            raise self.retry(exc=exc, countdown=min(30 * (2 ** self.request.retries), 120))
        except MaxRetriesExceededError:
            _safe_fail_callback(callback_url, session_id, str(exc), job_id, idempotency_key)
            raise
    finally:
        if src_path:
            Path(src_path).unlink(missing_ok=True)
        if parquet_path:
            Path(parquet_path).unlink(missing_ok=True)


def _safe_fail_callback(callback_url, session_id, error, job_id, idempotency_key):
    try:
        _callback(
            callback_url,
            {
                "session_id": session_id,
                "status": "FAILED",
                "error": error,
                "job_id": job_id,
                "idempotency_key": idempotency_key,
            },
        )
    except Exception:
        logger.exception("failed to deliver failure callback", extra={"session_id": session_id})
