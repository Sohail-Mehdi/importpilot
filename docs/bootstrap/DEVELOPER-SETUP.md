# Phase 0 — Bootstrap Developer Guide

This guide describes how to set up the ImportPilot development environment
from scratch on a fresh machine.

## Prerequisites

The following must be installed before proceeding:

| Tool | Version | Notes |
|---|---|---|
| Git | 2.x | `git --version` |
| Docker Desktop | 29+ | Must be running |
| Docker Compose | v2+ | Included with Docker Desktop |
| PHP | 8.3+ | 8.4 recommended |
| Composer | 2.x | PHP dependency manager |
| php8.x-pgsql | Required | PostgreSQL PDO extension |
| Python | 3.12+ | Do not use system Python directly |
| uv | 0.12+ | Python package manager — `uv --version` |
| Node.js | 24 LTS | `node --version` |
| pnpm | 9+ | `pnpm --version` |
| GitHub CLI | 2.x | `gh --version` |

## Installing Missing Prerequisites

```bash
# PHP PostgreSQL extension (required before artisan db commands)
sudo apt-get install -y php8.4-pgsql

# pnpm (Node.js package manager)
npm install -g pnpm

# uv (Python package manager)
curl -LsSf https://astral.sh/uv/install.sh | sh
```

## Docker Desktop Memory

The full development stack requires at least 4 GiB of memory allocated to the
Docker Desktop VM. Increase this in:

Docker Desktop → Settings → Resources → Memory

## Environment Configuration

Each service has a `.env.example` file. Copy and configure:

```bash
# Control plane
cp apps/control-plane/.env.example apps/control-plane/.env

# Data engine
cp apps/data-engine/.env.example apps/data-engine/.env
```

**Never commit a `.env` file. Never put real credentials in `.env.example`.**

## Starting the Development Stack

```bash
# Start infrastructure only (database, cache, broker, storage)
docker compose -f infra/docker/docker-compose.yml up -d

# See individual service READMEs for service-specific startup
```

## Git Branch Strategy

| Branch | Purpose |
|---|---|
| `main` | Stable trunk — must always build |
| `phase/0-architecture-contracts` | Phase 0 work |
| `phase/1-*` | Phase 1 features |
| `feat/*` | Feature branches |
| `fix/*` | Bug fix branches |

---

*This document will be expanded as the project progresses.*
