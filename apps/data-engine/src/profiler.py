from __future__ import annotations

import csv
import os
import tempfile
from pathlib import Path
from typing import Any

import openpyxl
import polars as pl
import pyarrow as pa

from .xlsx_validate import validate_xlsx_structure

EMAIL_PATTERN = r"^[\w\.-]+@[\w\.-]+\.\w+$"
XLSX_BATCH_ROWS = 2000


def _unique_headers(headers: list[str]) -> tuple[list[str], list[str]]:
    warnings: list[str] = []
    seen: dict[str, int] = {}
    unique: list[str] = []
    if len(headers) != len(set(headers)):
        warnings.append("duplicate_header")
    for header in headers:
        count = seen.get(header, 0)
        seen[header] = count + 1
        unique.append(header if count == 0 else f"{header}_{count}")
    return unique, warnings


def _as_path(source) -> tuple[str, bool]:
    if isinstance(source, (str, Path)):
        return str(source), False
    data = source.read() if hasattr(source, "read") else source
    if isinstance(data, str):
        data = data.encode("utf-8")
    fd, path = tempfile.mkstemp(prefix="importpilot-src-")
    os.close(fd)
    Path(path).write_bytes(data)
    return path, True


def _read_csv_headers(path: str, separator: str) -> tuple[list[str], list[str]]:
    warnings: list[str] = []
    with open(path, "r", encoding="utf-8-sig", newline="") as handle:
        first = handle.readline()
    if not first.strip():
        return [], warnings
    headers = next(csv.reader([first], delimiter=separator))
    headers = [h.strip("\ufeff") for h in headers]
    unique, header_warnings = _unique_headers(headers)
    warnings.extend(header_warnings)
    return unique, warnings


def _ambiguity_warnings(df: pl.DataFrame) -> list[str]:
    warnings: list[str] = []
    iso = r"^\d{4}-\d{2}-\d{2}$"
    slash = r"^\d{2}/\d{2}/\d{4}$"
    dash = r"^\d{2}-\d{2}-\d{4}$"
    decimal_eu = r"^\d{1,3}(?:\.\d{3})*,\d+$"
    decimal_us = r"^\d{1,3}(?:,\d{3})*\.\d+$"
    for name in df.columns:
        series = df[name].drop_nulls().cast(pl.Utf8, strict=False)
        if series.len() == 0:
            continue
        patterns = sum(
            1
            for pattern in (iso, slash, dash)
            if series.str.contains(pattern).any()
        )
        if patterns >= 2:
            warnings.append("ambiguous_date")
        if series.str.contains(decimal_eu).any() and series.str.contains(decimal_us).any():
            warnings.append("ambiguous_decimal")
    return warnings
    profile = {"row_count": df.height, "columns": {}}
    if df.height == 0:
        for col_name in df.columns:
            profile["columns"][col_name] = {
                "null_ratio": 0,
                "unique_count": 0,
                "inferred_type": "string",
                "sample": [],
            }
        return profile

    for col_name in df.columns:
        series = df[col_name]
        null_count = series.null_count()
        unique_count = series.n_unique()
        sample_list = series.drop_nulls().head(5).to_list()

        inferred_type = "string"
        dtype = series.dtype
        if dtype.is_integer():
            inferred_type = "integer"
        elif dtype.is_float():
            inferred_type = "decimal"
        elif dtype.is_temporal():
            inferred_type = "date"
        else:
            str_series = series.drop_nulls().cast(pl.Utf8)
            if str_series.len() > 0 and str_series.str.contains(EMAIL_PATTERN).all():
                inferred_type = "email"

        profile["columns"][col_name] = {
            "null_ratio": float(null_count / df.height),
            "unique_count": int(unique_count),
            "inferred_type": inferred_type,
            "sample": sample_list,
        }
    return profile


def _inspect_meta(
    file_type: str,
    headers: list[str],
    row_count: int,
    size_bytes: int,
    warnings: list[str],
    delimiter: str | None = None,
) -> dict[str, Any]:
    meta = {
        "file_type": file_type,
        "encoding": "utf-8",
        "has_headers": True,
        "headers": headers,
        "row_count": row_count,
        "size_bytes": size_bytes,
        "warnings": warnings,
    }
    if delimiter is not None:
        meta["delimiter"] = delimiter
    return meta


def process_csv(file_stream, content_type) -> tuple[pl.DataFrame, dict]:
    df, profile, _inspect = process_csv_detailed(file_stream, content_type)
    return df, profile


def process_csv_detailed(source, content_type: str) -> tuple[pl.DataFrame, dict, dict]:
    separator = "\t" if content_type == "text/tab-separated-values" else ","
    file_type = "tsv" if separator == "\t" else "csv"
    path, cleanup = _as_path(source)
    try:
        size_bytes = os.path.getsize(path)
        _headers, warnings = _read_csv_headers(path, separator)
        df = pl.read_csv(
            path,
            separator=separator,
            encoding="utf8",
            infer_schema_length=1000,
            try_parse_dates=True,
            ignore_errors=True,
        )
        warnings.extend(_ambiguity_warnings(df))
        profile = profile_dataframe(df)
        inspect = _inspect_meta(
            file_type, df.columns, df.height, size_bytes, warnings, separator
        )
        return df, profile, inspect
    finally:
        if cleanup:
            Path(path).unlink(missing_ok=True)


def process_xlsx(file_stream) -> tuple[pl.DataFrame, dict]:
    df, profile, _inspect = process_xlsx_detailed(file_stream)
    return df, profile


def process_xlsx_detailed(source) -> tuple[pl.DataFrame, dict, dict]:
    path, cleanup = _as_path(source)
    try:
        validate_xlsx_structure(path)
        size_bytes = os.path.getsize(path)
        warnings = ["formula_ignored"]
        workbook = openpyxl.load_workbook(path, read_only=True, data_only=True)
        try:
            sheet = workbook.active
            rows = sheet.iter_rows(values_only=True)
            header_row = next(rows, None)
            if header_row is None:
                df = pl.DataFrame()
                return df, profile_dataframe(df), _inspect_meta("xlsx", [], 0, size_bytes, warnings)

            headers, header_warnings = _unique_headers(
                [str(h) if h is not None else f"Column_{i}" for i, h in enumerate(header_row)]
            )
            warnings.extend(header_warnings)

            batches: list[pa.Table] = []
            current: list[dict[str, Any]] = []
            for row in rows:
                padded = list(row) + [None] * (len(headers) - len(row))
                current.append(dict(zip(headers, padded[: len(headers)])))
                if len(current) >= XLSX_BATCH_ROWS:
                    batches.append(pa.Table.from_pylist(current))
                    current = []
            if current:
                batches.append(pa.Table.from_pylist(current))

            if batches:
                table = pa.concat_tables(batches, promote_options="default")
                df = pl.from_arrow(table)
            else:
                df = pl.DataFrame({name: [] for name in headers})
        finally:
            workbook.close()

        profile = profile_dataframe(df)
        inspect = _inspect_meta("xlsx", df.columns, df.height, size_bytes, warnings)
        return df, profile, inspect
    finally:
        if cleanup:
            Path(path).unlink(missing_ok=True)


def write_parquet(df: pl.DataFrame, destination) -> None:
    """Write via Polars; PyArrow is the parquet engine."""
    if isinstance(destination, (str, Path)):
        df.write_parquet(destination)
        return
    buffer = destination
    df.write_parquet(buffer)
    if hasattr(buffer, "seek"):
        buffer.seek(0)


def sink_csv_to_parquet(csv_path: str, parquet_path: str, separator: str = ",") -> None:
    pl.scan_csv(
        csv_path,
        separator=separator,
        encoding="utf8",
        infer_schema_length=1000,
        try_parse_dates=True,
        ignore_errors=True,
    ).sink_parquet(parquet_path)
