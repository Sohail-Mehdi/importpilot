import polars as pl
import openpyxl
import io

def profile_dataframe(df: pl.DataFrame) -> dict:
    profile = {
        "row_count": df.height,
        "columns": {}
    }
    
    for col_name in df.columns:
        series = df[col_name]
        null_count = series.null_count()
        unique_count = series.n_unique()
        sample_list = series.drop_nulls().head(5).to_list()
        
        # Simple logical type inference
        inferred_type = "string"
        dtype = series.dtype
        if dtype in pl.INTEGER_DTYPES:
            inferred_type = "integer"
        elif dtype in pl.FLOAT_DTYPES:
            inferred_type = "decimal"
        elif dtype in pl.TEMPORAL_DTYPES:
            inferred_type = "date"
        else:
            # Check if all strings look like email
            str_series = series.drop_nulls().cast(pl.Utf8)
            if str_series.len() > 0 and str_series.str.contains(r'^[\w\.-]+@[\w\.-]+\.\w+$').all():
                inferred_type = "email"
                
        profile["columns"][col_name] = {
            "null_ratio": float(null_count / df.height) if df.height > 0 else 0,
            "unique_count": int(unique_count),
            "inferred_type": inferred_type,
            "sample": sample_list
        }
    return profile

def process_csv(file_stream, content_type) -> (pl.DataFrame, dict):
    sep = '\t' if content_type == 'text/tab-separated-values' else ','
    
    # Read using polars with low memory scan/read
    df = pl.read_csv(file_stream, separator=sep, ignore_errors=True, infer_schema_length=1000)
    profile = profile_dataframe(df)
    return df, profile

def process_xlsx(file_stream) -> (pl.DataFrame, dict):
    # Safe openpyxl read_only to avoid ZIP bombs
    wb = openpyxl.load_workbook(file_stream, read_only=True, data_only=True)
    sheet = wb.active
    
    data = []
    for i, row in enumerate(sheet.iter_rows(values_only=True)):
        data.append(row)
        
    if not data or len(data) < 2:
        return pl.DataFrame(), profile_dataframe(pl.DataFrame())
        
    # Build polars dataframe
    headers = [str(h) if h is not None else f"Column_{i}" for i, h in enumerate(data[0])]
    df = pl.DataFrame(data[1:], schema=headers, orient="row")
    
    profile = profile_dataframe(df)
    return df, profile
