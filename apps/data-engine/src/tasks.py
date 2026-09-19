import boto3
import io
import pyarrow as pa
import pyarrow.parquet as pq
import requests
from .celery_app import celery_app
from .config import settings
from .profiler import process_csv, process_xlsx

s3_client = boto3.client(
    's3',
    aws_access_key_id=settings.AWS_ACCESS_KEY_ID,
    aws_secret_access_key=settings.AWS_SECRET_ACCESS_KEY,
    endpoint_url=settings.AWS_ENDPOINT_URL,
    region_name=settings.AWS_REGION
)

@celery_app.task(bind=True, max_retries=3)
def inspect_file(self, session_id: str, storage_key: str, content_type: str, callback_url: str):
    try:
        obj = s3_client.get_object(Bucket=settings.AWS_BUCKET, Key=storage_key)
        file_stream = io.BytesIO(obj['Body'].read())
        
        if 'spreadsheetml' in content_type:
            df, profile = process_xlsx(file_stream)
        else:
            df, profile = process_csv(file_stream, content_type)
            
        # Write Parquet intermediate artifact
        parquet_buffer = io.BytesIO()
        df.write_parquet(parquet_buffer)
        parquet_buffer.seek(0)
        
        parquet_key = f"intermediate/{session_id}/data.parquet"
        s3_client.put_object(
            Bucket=settings.AWS_BUCKET,
            Key=parquet_key,
            Body=parquet_buffer.getvalue(),
            ContentType='application/vnd.apache.parquet'
        )
        
        payload = {
            "session_id": session_id,
            "status": "COMPLETED",
            "profile": profile,
            "parquet_key": parquet_key
        }
        resp = requests.post(
            callback_url,
            json=payload,
            headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"}
        )
        resp.raise_for_status()
        
        return payload
        
    except Exception as exc:
        try:
            requests.post(
                callback_url,
                json={"session_id": session_id, "status": "FAILED", "error": str(exc)},
                headers={"Authorization": f"Bearer {settings.LARAVEL_INTERNAL_TOKEN}"}
            )
        except Exception:
            pass
        raise self.retry(exc=exc, countdown=30)
