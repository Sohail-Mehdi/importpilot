from fastapi import FastAPI

app = FastAPI(title="ImportPilot Data Engine")

@app.get("/health")
def health_check():
    return {"status": "ok", "service": "data-engine"}
