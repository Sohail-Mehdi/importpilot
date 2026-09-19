import pytest
import polars as pl
import io
from src.profiler import process_csv

def test_process_csv():
    csv_data = "id,name,email\n1,Test,test@test.com\n2,,other@test.com"
    stream = io.BytesIO(csv_data.encode('utf-8'))
    
    df, profile = process_csv(stream, "text/csv")
    
    assert df.height == 2
    assert profile["row_count"] == 2
    
    # Check null ratio for name
    assert profile["columns"]["name"]["null_ratio"] == 0.5
    assert profile["columns"]["id"]["null_ratio"] == 0.0
    
    # Check inferred types
    assert profile["columns"]["id"]["inferred_type"] == "integer"
    assert profile["columns"]["email"]["inferred_type"] == "email"
