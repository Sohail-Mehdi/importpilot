import json
import sys

def load_state_machine():
    with open("packages/schema-spec/state-machine.json") as f:
        return json.load(f)

sm = load_state_machine()
transitions = sm['transitions']
states = sm['states']

def can_transition(current, trigger):
    for t in transitions:
        if t['from'] == current and t['trigger'] == trigger:
            return t['to']
    for t in sm['global_transitions']:
        if t['trigger'] == trigger:
            return t['to']
    return None

tests = [
    ("CREATED", "signed_url_requested", "UPLOADING"),
    ("CREATED", "upload_success", "UPLOADED"),
    ("UPLOADING", "upload_success", "UPLOADED"),
    ("UPLOADED", "dispatch_inspection", "INSPECTING"),
    ("INSPECTING", "inspection_success", "AWAITING_MAPPING"),
    ("INSPECTING", "upload_success", None), # Forbidden
    ("VALIDATING", "validation_issues_found", "NEEDS_CORRECTION"),
    ("NEEDS_CORRECTION", "corrections_applied", "VALIDATING"), # Revalidation
    ("VALIDATING", "validation_clean", "READY_FOR_DRY_RUN"),
    ("READY_FOR_DRY_RUN", "mapping_confirmed", None), # Forbidden
    ("DRY_RUN_READY", "user_finalizes", "FINALIZING"),
    ("FINALIZING", "finalization_success", "COMPLETED"),
    ("VALIDATING", "system_error", "FAILED") # Global transition
]

failed = 0
for current, trigger, expected_to in tests:
    result = can_transition(current, trigger)
    if result == expected_to:
        print(f"[OK] {current} + {trigger} -> {expected_to}")
    else:
        print(f"[FAIL] {current} + {trigger} -> Expected {expected_to}, got {result}")
        failed += 1

sys.exit(failed)
