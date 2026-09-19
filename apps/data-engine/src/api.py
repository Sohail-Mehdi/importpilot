from fastapi import FastAPI, HTTPException, Depends
from pydantic import BaseModel
from .tasks import inspect_file
from .config import settings

app = FastAPI(title="ImportPilot Data Engine")

class InspectJob(BaseModel):
    session_id: str
    storage_key: str
    content_type: str
    callback_url: str
    internal_token: str

@app.get("/health")
def health():
    return {"status": "ok"}

@app.post("/api/v1/jobs/inspect")
def trigger_inspection(job: InspectJob):
    if job.internal_token != settings.LARAVEL_INTERNAL_TOKEN:
        raise HTTPException(status_code=401, detail="Unauthorized")
        
    task = inspect_file.delay(job.session_id, job.storage_key, job.content_type, job.callback_url)
    return {"task_id": task.id, "status": "QUEUED"}
