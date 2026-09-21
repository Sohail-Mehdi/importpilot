import os
from dotenv import load_dotenv

load_dotenv()


class Settings:
    REDIS_URL = os.getenv("REDIS_URL", "redis://127.0.0.1:6379/0")
    AWS_ACCESS_KEY_ID = os.getenv("AWS_ACCESS_KEY_ID", "somekey")
    AWS_SECRET_ACCESS_KEY = os.getenv("AWS_SECRET_ACCESS_KEY", "somesecret")
    AWS_ENDPOINT_URL = os.getenv("AWS_ENDPOINT_URL", "http://127.0.0.1:8333")
    AWS_REGION = os.getenv("AWS_REGION", "us-east-1")
    AWS_BUCKET = os.getenv("AWS_BUCKET", "importpilot-uploads")
    LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://127.0.0.1:8000/api/v1")
    LARAVEL_INTERNAL_TOKEN = os.getenv("LARAVEL_INTERNAL_TOKEN", "secret-token")
    S3_STREAM_CHUNK_BYTES = int(os.getenv("S3_STREAM_CHUNK_BYTES", str(1024 * 1024)))


settings = Settings()
