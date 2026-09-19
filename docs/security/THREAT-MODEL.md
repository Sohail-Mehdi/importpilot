# ImportPilot Threat Model

**Status:** Current  
**Date:** 2026-09-17

This document outlines the threat model for the ImportPilot core architecture, following a STRIDE-based approach tailored for data ingestion infrastructure.

## 1. Upload Pipeline Threats

| Threat | Entry Point | Mitigation | Residual Risk |
|---|---|---|---|
| **Malicious File (Zip Bomb, Oversized)** | Signed S3 upload URL | Hard size limits on presigned URL; python inspector streams decompression and limits chunk size. | High-compression files may still consume worker memory. |
| **Spoofed MIME/Extension** | Browser upload | Extension vs Signature cross-check during initial inspection. MIME on S3 object is untrusted until verified by Python worker. | Low. |
| **Formula / Macro Abuse** | XLSX parsing | `openpyxl` used in data_only/read-only mode. Macros explicitly unsupported and ignored. No formula evaluation. | Low. |

## 2. Tenancy & Isolation Threats

| Threat | Entry Point | Mitigation | Residual Risk |
|---|---|---|---|
| **IDOR / Cross-Tenant Query** | API / Dashboard | Tenant-scoping matrix strictly enforced. `project_id` must match authenticated context. | Developer error in future controllers. |
| **Artifact Key Guessing** | Direct S3 link | Opaque UUIDs used for all artifacts. Presigned URLs are short-lived. | URL leakage by user. |

## 3. Credential & Secret Threats

| Threat | Entry Point | Mitigation | Residual Risk |
|---|---|---|---|
| **Leaked API Keys** | Code / Logs | API keys stored as strong hashes (SHA-256+). Raw key displayed only once at creation. | User commits key to public repo. |
| **Raw Data Logging** | Application logs | Explicit logging policy: Never log row contents or sensitive cell values. Use `correlation_id` and `row_id`. | Accidental debug log left in production. |

## 4. Asynchronous Queue Threats

| Threat | Entry Point | Mitigation | Residual Risk |
|---|---|---|---|
| **Duplicated / Stale Jobs** | Celery/RabbitMQ | Idempotency keys required on finalization. Job states separated from Import states. Stale workers cannot overwrite a newer job attempt. | Time-of-check to time-of-use (TOCTOU) race conditions in state transition. |
| **Poisoned Payload** | Message Broker | Broker messages only contain metadata and artifact UUIDs, never full customer payload. | Broker manipulation if internal network is compromised. |

## 5. Webhook Threats

| Threat | Entry Point | Mitigation | Residual Risk |
|---|---|---|---|
| **Spoofed Callback** | Webhook delivery | All payloads signed via HMAC-SHA256 using rotating project webhook secret. | Secret compromise. |
| **Replay Attacks** | Webhook endpoint | Timestamp included in signed envelope. Consumer guided to implement 5-minute tolerance and use `event_id` idempotency. | Consumer fails to implement idempotency. |
