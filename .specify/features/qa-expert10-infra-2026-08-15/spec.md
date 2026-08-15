# Feature Specification: Infra — cohérence cache/mail entre services (qa-expert10)

**Created**: 2026-08-15

**Status**: Draft

**Wave**: qa-expert10-2026-08-15 (audit 360° — kiosk, edge, infra, API, mobile, surfaces live)


**Input**: Audit render.yaml / docker-compose.yml du 2026-08-15.

## User Scenarios & Testing

### US1 — Cache unifié (Priority: P2) — Issue #3593
Tous les services (dev compose + prod Render) utilisent Redis : `CACHE_STORE` partout, fin du `CACHE_DRIVER` mort.

**Acceptance Scenarios**:
1. **Given** `docker compose up`, **When** un rate-limit/lock est posé par l'API, **Then** le queue worker le voit (même backend cache).
2. **Given** la prod Render, **When** un `Cache::lock` est pris par le web, **Then** le scheduler/worker le respecte.

### US2 — Mails du scheduler (Priority: P3) — Issue #3594
Le scheduler Render a la même config MAIL_* que le web.

## Requirements
- FR-1: docker-compose.yml : `CACHE_DRIVER` → `CACHE_STORE` (3 services)
- FR-2: render.yaml : `CACHE_STORE=redis` + `REDIS_CLIENT`/`REDIS_PASSWORD` sur worker + scheduler
- FR-3: render.yaml : MAIL_* sur scheduler (ou envGroup mutualisé)
