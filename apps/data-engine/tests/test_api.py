import pytest
from fastapi.testclient import TestClient
from src.api import app
from src.config import settings

client = TestClient(app)

PROJECT_ID = "11111111-1111-1111-1111-111111111111"
SESSION_ID = "22222222-2222-2222-2222-222222222222"
TENANT_KEY = f"sources/{PROJECT_ID}/{SESSION_ID}/file.csv"


def test_health():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_trigger_inspection_unauthorized():
    response = client.post(
        "/api/v1/jobs/inspect",
        json={
            "session_id": SESSION_ID,
            "storage_key": TENANT_KEY,
            "content_type": "text/csv",
            "callback_url": "http://localhost",
            "internal_token": "wrong",
            "project_id": PROJECT_ID,
        },
    )
    assert response.status_code == 401


def test_trigger_inspection_authorized(mocker):
    mocker.patch("src.api.inspect_file.delay")

    response = client.post(
        "/api/v1/jobs/inspect",
        json={
            "session_id": SESSION_ID,
            "storage_key": TENANT_KEY,
            "content_type": "text/csv",
            "callback_url": "http://localhost",
            "internal_token": settings.LARAVEL_INTERNAL_TOKEN,
            "project_id": PROJECT_ID,
            "organization_id": "33333333-3333-3333-3333-333333333333",
            "operation": "inspect",
            "contract_version": "1.0",
            "attempt": 1,
            "idempotency_key": f"inspect:{SESSION_ID}",
            "requested_at": "2026-09-21T00:00:00Z",
            "job_id": "44444444-4444-4444-4444-444444444444",
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "QUEUED"
    assert body["contract_version"] == "1.0"


def test_trigger_inspection_bearer_header(mocker):
    delayed = mocker.patch("src.api.inspect_file.delay")
    response = client.post(
        "/api/v1/jobs/inspect",
        headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"},
        json={
            "import_id": SESSION_ID,
            "storage_key": TENANT_KEY,
            "content_type": "text/csv",
            "callback_url": "http://127.0.0.1:8000/api/v1/internal/jobs/callback",
            "project_id": PROJECT_ID,
        },
    )
    assert response.status_code == 200
    delayed.assert_called_once()


def test_reject_cross_tenant_storage_key(mocker):
    mocker.patch("src.api.inspect_file.delay")
    response = client.post(
        "/api/v1/jobs/inspect",
        headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"},
        json={
            "session_id": SESSION_ID,
            "storage_key": "sources/other-project/file.csv",
            "content_type": "text/csv",
            "callback_url": "http://localhost",
            "project_id": PROJECT_ID,
        },
    )
    assert response.status_code == 403


def test_reject_unscoped_storage_key(mocker):
    mocker.patch("src.api.inspect_file.delay")
    response = client.post(
        "/api/v1/jobs/inspect",
        headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"},
        json={
            "session_id": SESSION_ID,
            "storage_key": "temp/not-a-source",
            "content_type": "text/csv",
            "callback_url": "http://localhost",
        },
    )
    assert response.status_code == 403
