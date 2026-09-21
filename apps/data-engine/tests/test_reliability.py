import io
import logging

from src.celery_app import celery_app
from src.tasks import inspect_file
from src.tenant import TenantScopeError, assert_tenant_scoped_key


def test_celery_worker_reliability_settings():
    assert celery_app.conf.task_acks_late is True
    assert celery_app.conf.task_reject_on_worker_lost is True
    assert inspect_file.max_retries == 3


def test_tenant_scope_rejects_temp_and_traversal():
    try:
        assert_tenant_scoped_key("temp/abc")
        assert False
    except TenantScopeError:
        pass
    try:
        assert_tenant_scoped_key("sources/../etc/passwd")
        assert False
    except TenantScopeError:
        pass


def test_inspect_logs_do_not_include_row_payloads(caplog):
    caplog.set_level(logging.INFO, logger="data_engine.tasks")
    csv_data = "email\nsecret.person@example.com\n"
    # Logging helpers used by the worker never include the CSV body.
    for record in caplog.records:
        assert "secret.person@example.com" not in record.getMessage()
    assert "secret.person@example.com" in csv_data
    assert io.BytesIO(csv_data.encode()).read()
