# ADR-009: RabbitMQ vs Redis as Celery Broker

**Status:** PROPOSED  
**Date:** 2026-09-17

## Context
The ImportPilot Master Specification initially locked Redis as the Celery broker (along with its role as a Laravel cache/queue). However, Python worker tasks for data importing (e.g., parsing 100k-row CSVs, generating Parquet artifacts, applying complex validation rules, and fuzzy duplicate detection) can be extremely long-running.

Redis as a Celery broker operates using a `visibility_timeout`. If a task executes longer than this timeout, Redis assumes the worker died and redelivers the task to another worker. This requires setting artificially high `visibility_timeout` values, which in turn delays genuine crash recovery.

## Decision
We propose replacing Redis with **RabbitMQ** as the primary message broker for Python Celery workers. 
- RabbitMQ uses AMQP, which maintains an active channel for in-flight messages and relies on explicit acknowledgments (ACK) rather than a visibility timeout. 
- A worker crash immediately closes the TCP socket, prompting RabbitMQ to safely redeliver the message without waiting for an arbitrary timeout.
- Redis (v8.2.x) will be retained strictly for Laravel Horizon queue management, session state, and caching.

## Alternatives Considered
1. **Redis Only:** Requires setting `visibility_timeout` higher than the longest possible task (e.g., hours). Genuine worker crashes are not detected until the timeout expires, leaving jobs in a false "running" state. (Rejected)
2. **RabbitMQ for Celery (Recommended):** Native AMQP semantics guarantee **at-least-once** delivery attempts and immediate crash redelivery without timeouts. To ensure safety, application-level idempotency is strictly required (via `idempotency_key` and unique database constraints on `(import_id, attempt)`) to handle duplicate deliveries gracefully.
3. **SQS / Cloud Providers:** Introduces cloud lock-in for the core open-core/self-hosted deployment model. (Rejected)

## Consequences
- **Positive:** Robust handling of long-running tasks without duplicate execution risks. Immediate recovery on worker crash.
- **Negative:** Adds a new infrastructure dependency (RabbitMQ container) for local development and production.

## Migration Impact
None currently, as no worker code or Celery configuration has been written yet.

## Approval
Awaiting user approval before becoming the locked architecture. Included currently only as an optional Docker Compose profile (`evaluation`).
