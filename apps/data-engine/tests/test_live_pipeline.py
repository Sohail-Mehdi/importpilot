"""Live inspection pipeline.

Requires: Postgres, Redis, SeaweedFS, Laravel on :8000, FastAPI on :8001,
and a real Celery worker consuming `src.tasks.inspect_file`.
"""

from __future__ import annotations

import time
from pathlib import Path

import boto3
import httpx
import polars as pl
import pyarrow.parquet as pq
import pytest

FIXTURE = Path(__file__).resolve().parents[3] / "packages" / "test-fixtures" / "customers_good.csv"
LARAVEL = "http://127.0.0.1:8000"
ENGINE = "http://127.0.0.1:8001"
S3_ENDPOINT = "http://127.0.0.1:8333"
BUCKET = "importpilot-uploads"


def _s3():
    return boto3.client(
        "s3",
        endpoint_url=S3_ENDPOINT,
        aws_access_key_id="somekey",
        aws_secret_access_key="somesecret",
        region_name="us-east-1",
    )


@pytest.mark.live
def test_live_csv_inspection_pipeline():
    client = httpx.Client(base_url=LARAVEL, timeout=20.0, follow_redirects=True)
    engine = httpx.Client(base_url=ENGINE, timeout=10.0)
    assert engine.get("/health").json()["status"] == "ok"

    s3 = _s3()
    try:
        s3.create_bucket(Bucket=BUCKET)
    except Exception:
        pass

    suffix = str(int(time.time()))
    register = client.post(
        "/api/v1/register",
        json={
            "name": "E2E User",
            "email": f"e2e-{suffix}@example.test",
            "password": "secret123",
        },
    )
    assert register.status_code == 201, register.text

    org = client.post("/api/v1/organizations", json={"name": f"E2E Org {suffix}"})
    assert org.status_code == 201, org.text
    org_id = org.json()["id"]

    project = client.post(
        "/api/v1/projects",
        json={"name": "E2E Project", "organization_id": org_id},
    )
    assert project.status_code == 201, project.text
    project_id = project.json()["id"]

    session = client.post(f"/api/v1/projects/{project_id}/sessions", json={})
    assert session.status_code == 201, session.text
    session_id = session.json()["id"]
    assert session.json()["state"] == "CREATED"

    upload = client.post(
        f"/api/v1/projects/{project_id}/sessions/{session_id}/upload",
        json={"filename": "customers_good.csv", "content_type": "text/csv"},
    )
    assert upload.status_code == 201, upload.text
    upload_id = upload.json()["upload_id"]
    url = upload.json()["url"]

    csv_bytes = FIXTURE.read_bytes()
    put = httpx.put(url, content=csv_bytes, headers={"Content-Type": "text/csv"}, timeout=20.0)
    assert put.is_success, put.text

    complete = client.post(
        f"/api/v1/projects/{project_id}/sessions/{session_id}/upload/{upload_id}/complete"
    )
    assert complete.status_code == 200, complete.text
    artifact = complete.json()
    assert artifact["storage_key"].startswith(f"sources/{project_id}/")
    assert artifact["content_type"] == "text/csv"

    state = None
    parquet_key = None
    for _ in range(40):
        shown = client.get(f"/api/v1/projects/{project_id}/sessions/{session_id}")
        assert shown.status_code == 200, shown.text
        state = shown.json()["state"]
        if state in {"AWAITING_MAPPING", "FAILED"}:
            break
        time.sleep(0.5)
    assert state == "AWAITING_MAPPING", f"final lifecycle state was {state}"

    obj = s3.list_objects_v2(Bucket=BUCKET, Prefix=f"intermediate/{project_id}/{session_id}/")
    keys = [item["Key"] for item in obj.get("Contents", [])]
    assert keys, "no parquet object under tenant intermediate prefix"
    parquet_key = keys[0]
    body = s3.get_object(Bucket=BUCKET, Key=parquet_key)["Body"].read()
    tmp = Path("/tmp/importpilot-e2e.parquet")
    tmp.write_bytes(body)
    table = pq.read_table(tmp)
    df = pl.from_arrow(table)
    assert table.num_rows == 2
    assert "Ali Khan" in df["name"].to_list()
    assert "sara@example.com" in df["email"].to_list()
    tmp.unlink(missing_ok=True)
