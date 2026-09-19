import json
import jsonschema
import sys
import os

def load_schema(name):
    path = f"packages/schema-spec/{name}.json"
    with open(path) as f:
        return json.load(f)

schemas = {
    'job-envelope': load_schema('job-envelope'),
    'inspect-result': load_schema('inspect-result'),
    'system-error': load_schema('system-error'),
}

tests = [
    {
        'schema': 'job-envelope',
        'desc': 'Valid job envelope',
        'data': {
            "contract_version": "1.0",
            "job_id": "123e4567-e89b-12d3-a456-426614174000",
            "import_id": "123e4567-e89b-12d3-a456-426614174001",
            "organization_id": "123e4567-e89b-12d3-a456-426614174002",
            "operation": "inspect",
            "attempt": 1,
            "idempotency_key": "abc",
            "requested_at": "2026-09-17T00:00:00Z"
        },
        'expect_pass': True
    },
    {
        'schema': 'job-envelope',
        'desc': 'Invalid job envelope (missing required operation)',
        'data': {
            "contract_version": "1.0",
            "job_id": "123e4567-e89b-12d3-a456-426614174000",
            "import_id": "123e4567-e89b-12d3-a456-426614174001",
            "organization_id": "123e4567-e89b-12d3-a456-426614174002",
            "attempt": 1,
            "idempotency_key": "abc",
            "requested_at": "2026-09-17T00:00:00Z"
        },
        'expect_pass': False
    },
    {
        'schema': 'inspect-result',
        'desc': 'Valid inspect result',
        'data': {
            "file_type": "csv",
            "encoding": "utf-8",
            "has_headers": True,
            "headers": ["name", "email"],
            "row_count": 100,
            "size_bytes": 1024
        },
        'expect_pass': True
    }
]

failed_tests = 0

for t in tests:
    schema = schemas[t['schema']]
    try:
        jsonschema.validate(instance=t['data'], schema=schema)
        passed = True
    except jsonschema.exceptions.ValidationError as e:
        passed = False

    if passed == t['expect_pass']:
        print(f"[OK] {t['desc']}")
    else:
        print(f"[FAIL] {t['desc']} (Expected {t['expect_pass']}, got {passed})")
        failed_tests += 1

sys.exit(failed_tests)
