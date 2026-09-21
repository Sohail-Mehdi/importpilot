import io
from pathlib import Path

import polars as pl
import pyarrow.parquet as pq
from src.profiler import process_csv, process_csv_detailed, process_xlsx_detailed, write_parquet
from src.xlsx_validate import InvalidXlsxError, validate_xlsx_structure

FIXTURES = Path(__file__).resolve().parents[3] / "packages" / "test-fixtures"


def test_process_csv():
    csv_data = "id,name,email\n1,Test,test@test.com\n2,,other@test.com"
    stream = io.BytesIO(csv_data.encode("utf-8"))

    df, profile = process_csv(stream, "text/csv")

    assert df.height == 2
    assert profile["row_count"] == 2
    assert profile["columns"]["name"]["null_ratio"] == 0.5
    assert profile["columns"]["id"]["null_ratio"] == 0.0
    assert profile["columns"]["id"]["inferred_type"] == "integer"
    assert profile["columns"]["email"]["inferred_type"] == "email"
    assert isinstance(df, pl.DataFrame)


def test_customers_good_csv_unicode_and_parquet(tmp_path):
    df, profile, inspect = process_csv_detailed(FIXTURES / "customers_good.csv", "text/csv")
    assert inspect["file_type"] == "csv"
    assert inspect["has_headers"] is True
    assert inspect["headers"] == ["name", "email", "phone", "dob"]
    assert inspect["row_count"] == 2
    assert "Ali Khan" in df["name"].to_list()
    parquet_path = tmp_path / "customers.parquet"
    write_parquet(df, parquet_path)
    table = pq.read_table(parquet_path)
    roundtrip = pl.from_arrow(table)
    assert roundtrip["name"].to_list() == df["name"].to_list()
    assert profile["columns"]["email"]["inferred_type"] == "email"


def test_edge_case_csv_unicode_and_duplicate_headers():
    df, profile, inspect = process_csv_detailed(FIXTURES / "edge_case.csv", "text/csv")
    assert inspect["row_count"] == 6
    assert "duplicate_header" in inspect["warnings"]
    names = " ".join(df.columns)
    assert "Name" in names
    assert any("محمد علی" in str(value) for value in df.to_series(1).to_list())


def test_edge_case_tsv():
    df, _profile, inspect = process_csv_detailed(
        FIXTURES / "edge_case.tsv", "text/tab-separated-values"
    )
    assert inspect["file_type"] == "tsv"
    assert inspect["delimiter"] == "\t"
    assert inspect["row_count"] == 3
    assert "DateOfBirth" in df.columns


def test_edge_case_xlsx_formulas_and_structure():
    df, _profile, inspect = process_xlsx_detailed(FIXTURES / "edge_case.xlsx")
    assert inspect["file_type"] == "xlsx"
    assert inspect["row_count"] == 2
    assert "formula_ignored" in inspect["warnings"]
    assert df.height == 2
    assert "User1" in df["Name"].to_list()


def test_xlsx_zip_signature_alone_is_rejected(tmp_path):
    zip_only = tmp_path / "fake.xlsx"
    import zipfile

    with zipfile.ZipFile(zip_only, "w") as archive:
        archive.writestr("hello.txt", "not a workbook")
    try:
        validate_xlsx_structure(zip_only)
        assert False, "expected InvalidXlsxError"
    except InvalidXlsxError as exc:
        assert "missing structural parts" in str(exc)


def test_large_benchmark_csv_and_parquet_roundtrip(tmp_path):
    path = FIXTURES / "large_benchmark.csv"
    df, profile, inspect = process_csv_detailed(path, "text/csv")
    assert inspect["row_count"] == 1000
    assert profile["row_count"] == 1000
    parquet_path = tmp_path / "large.parquet"
    write_parquet(df, parquet_path)
    assert pq.read_table(parquet_path).num_rows == 1000
