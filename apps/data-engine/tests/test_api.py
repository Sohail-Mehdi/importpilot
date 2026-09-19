import pytest
from fastapi.testclient import TestClient
from src.api import app
from src.config import settings

client = TestClient(app)

def test_health():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}

def test_trigger_inspection_unauthorized():
    response = client.post("/api/v1/jobs/inspect", json={
        "session_id": "123",
        "storage_key": "some_key",
        "content_type": "text/csv",
        "callback_url": "http://localhost",
        "internal_token": "wrong"
    })
    assert response.status_code == 401

def test_trigger_inspection_authorized(mocker):
    # Mock celery delay
    mocker.patch('src.api.inspect_file.delay')
    
    response = client.post("/api/v1/jobs/inspect", json={
        "session_id": "123",
        "storage_key": "some_key",
        "content_type": "text/csv",
        "callback_url": "http://localhost",
        "internal_token": settings.LARAVEL_INTERNAL_TOKEN
    })
    
    assert response.status_code == 200
    assert response.json()["status"] == "QUEUED"
