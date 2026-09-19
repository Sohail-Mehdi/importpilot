import glob
import json
import jsonschema
import sys

files = glob.glob("packages/schema-spec/*.json")
failed = 0

for file in files:
    with open(file) as f:
        try:
            schema = json.load(f)
            jsonschema.Draft202012Validator.check_schema(schema)
            print(f"[OK] {file} is a valid Draft2020-12 JSON Schema")
        except Exception as e:
            print(f"[FAIL] {file} has issues: {e}")
            failed += 1

sys.exit(failed)
