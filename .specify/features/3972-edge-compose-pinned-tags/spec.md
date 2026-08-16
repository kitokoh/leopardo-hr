# Feature Specification: docker-compose edge — tags image versionnés (issue #3972)

**Feature Branch**: `fix/3972-edge-compose-pinned-tags`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA qa-expert14 2026-08-15 — `edge/docker-compose.yml` référence `leopardo/edge-api:latest` ×2, `leopardo/edge-ui:latest`, `caddy:2-alpine` ; `install.sh` re-pull à chaque install. Une mauvaise publication Docker Hub (ou un tag `2-alpine` bougé) upgrade/casse silencieusement chaque nœud client — #3591/#3770 ont déjà prouvé que `edge-ui` peut manquer.

## Problème

- Tags flottants (`latest`, `2-alpine`) : non déterministes — un re-pull peut livrer une image différente sans changement local.
- `docker compose pull` inconditionnel dans install.sh : combiné aux tags flottants, chaque réinstallation est une loterie.

## Décision

Pinner les tags sur la version publiée par `publish.sh` (schéma `1.0.0`, vérifiable dans le repo : `publish.sh` build `$IMAGE:$VERSION` + `latest`, `Dockerfile.publish` LABEL `version="1.0.0"`) :

- `leopardo/edge-api:latest` ×2 → `leopardo/edge-api:1.0.0`
- `leopardo/edge-ui:latest` → `leopardo/edge-ui:1.0.0` (même schéma de version)
- `caddy:2-alpine` → `caddy:2.9-alpine` (mineur épinglé, pas de bump majeur silencieux)

Le pull de install.sh devient idempotent (mêmes tags → mêmes images, cache de couches). Le `docker compose pull` reste inchangé : avec des tags épinglés, il n'y a plus de « mauvaise surprise » possible.

## User Scenarios & Testing

### User Story 1 — Une réinstallation Edge est déterministe (Priority: P2)

**Independent Test**: `grep -c ':latest' edge/docker-compose.yml` → 0 ; `grep 'image:' edge/docker-compose.yml` → tags versionnés.

**Acceptance Scenarios**:

1. **Given** le compose, **When** on le lit, **Then** aucun `:latest`, tags `1.0.0`/`2.9-alpine`.
2. **Given** une réinstallation cliente, **When** `docker compose pull` s'exécute, **Then** les mêmes versions sont tirées (idempotent).
3. **Given** la publication `publish.sh`, **When** `leopardo/edge-api:1.0.0` est poussé, **Then** le compose référence exactement ce tag.

## Edge Cases

- `caddy:2.9-alpine` : tag mineur flottant (patchs de sécurité) — compromis standard Docker Hub entre déterministe et maintenance ; pas de bump majeur silencieux.
- `edge-ui:1.0.0` : si l'image n'est pas encore publiée à ce tag, `docker compose pull` échouera clairement au lieu d'avoir une surprise au runtime (fail-fast préférable au :latest cassé).
- `install.sh` inchangé : le pull reste conditionné à la présence des images au `up -d` (comportement existant, pas de régression offline).
