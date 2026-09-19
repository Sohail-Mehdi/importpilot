import boto3
import urllib.request
from botocore.client import Config
import hashlib

def test_seaweedfs():
    s3 = boto3.client(
        's3',
        endpoint_url='http://127.0.0.1:8333',
        aws_access_key_id='any',
        aws_secret_access_key='any',
        config=Config(signature_version='s3v4'),
        region_name='us-east-1'
    )
    
    bucket_name = 'importpilot-test-bucket'
    
    # 1. Create Bucket
    try:
        s3.create_bucket(Bucket=bucket_name)
        print("[OK] Bucket created")
    except Exception as e:
        print(f"[FAIL] Bucket creation: {e}")
        return

    # 2. Upload Object
    object_key = 'test-artifact.txt'
    content = b"ImportPilot SeaweedFS Test"
    expected_hash = hashlib.md5(content).hexdigest()
    try:
        s3.put_object(Bucket=bucket_name, Key=object_key, Body=content)
        print("[OK] Object uploaded")
    except Exception as e:
        print(f"[FAIL] Object upload: {e}")

    # 3. Presigned URL
    try:
        url = s3.generate_presigned_url(
            'get_object',
            Params={'Bucket': bucket_name, 'Key': object_key},
            ExpiresIn=3600
        )
        print("[OK] Presigned URL generated")
        
        # Test fetching via presigned URL
        req = urllib.request.urlopen(url)
        fetched_content = req.read()
        if fetched_content == content:
            print("[OK] Presigned URL fetch & Integrity match")
        else:
            print("[FAIL] Presigned URL fetch content mismatch")
    except Exception as e:
        print(f"[FAIL] Presigned URL test: {e}")

    # 4. Delete Object and Bucket
    try:
        s3.delete_object(Bucket=bucket_name, Key=object_key)
        s3.delete_bucket(Bucket=bucket_name)
        print("[OK] Object and Bucket deleted")
    except Exception as e:
        print(f"[FAIL] Cleanup: {e}")

if __name__ == '__main__':
    test_seaweedfs()
