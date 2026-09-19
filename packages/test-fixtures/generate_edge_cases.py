import pandas as pd
import json

# 1. Edge Case CSV: Unicode, quoted/multiline, multiple delimiters (used within cells), blank/duplicate headers
data_csv = [
    ["1", "Ali Khan", "ali@example.com", "Active"],
    ["2", "محمد علی", "invalid-email", "Inactive\nMulti-line"], # Urdu, multiline, invalid email
    ["3", "Jane, Doe", "jane@test.com", "Pending"], # comma in quoted field
    ["4", "NullMarker", "NULL", "N/A"], # Null markers
    ["5", "DupKey", "dup@example.com", "Active"], # duplicate key 1
    ["5", "DupKey", "dup2@example.com", "Active"], # duplicate key 2
]

df_csv = pd.DataFrame(data_csv, columns=["ID", "Name", "Email", "Status"])
# Introduce duplicate header for testing
df_csv.rename(columns={"Status": "Name"}, inplace=True) 

df_csv.to_csv("packages/test-fixtures/edge_case.csv", index=False, encoding="utf-8-sig")

# 2. Edge Case TSV: Ambiguous dates, Decimal-locale
data_tsv = {
    "ID": [1, 2, 3],
    "DateOfBirth": ["01/02/2000", "2000-02-01", "31-12-1999"], # Ambiguous
    "Balance": ["1.000,50", "1,000.50", "1000"] # Decimal ambiguity
}
df_tsv = pd.DataFrame(data_tsv)
df_tsv.to_csv("packages/test-fixtures/edge_case.tsv", sep="\t", index=False)

# 3. Edge Case XLSX: Formulas
import openpyxl
wb = openpyxl.Workbook()
ws = wb.active
ws.append(["ID", "Name", "Score", "ComputedScore"])
ws.append([1, "User1", 50, "=C2*2"]) # Formula
ws.append([2, "User2", 100, "=C3*2"])
wb.save("packages/test-fixtures/edge_case.xlsx")

# 4. Expectations file
expectations = {
    "edge_case.csv": {
        "expected_issues": [
            {"type": "duplicate_header", "columns": ["Name", "Name"]},
            {"type": "invalid_email", "row": 1, "value": "invalid-email"},
            {"type": "duplicate_key", "row": [4, 5], "key": "5"}
        ],
        "expected_rows": 6
    },
    "edge_case.tsv": {
        "expected_issues": [
            {"type": "ambiguous_date", "column": "DateOfBirth"},
            {"type": "ambiguous_decimal", "column": "Balance"}
        ],
        "expected_rows": 3
    },
    "edge_case.xlsx": {
        "expected_issues": [
            {"type": "formula_ignored", "column": "ComputedScore"}
        ],
        "expected_rows": 2
    }
}
with open("packages/test-fixtures/expectations.json", "w") as f:
    json.dump(expectations, f, indent=2)

