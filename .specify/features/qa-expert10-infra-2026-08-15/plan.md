# Implementation Plan: Infra cache/mail

1. docker-compose.yml : remplacer les 3 `CACHE_DRIVER: redis` par `CACHE_STORE: redis`.
2. render.yaml : ajouter `CACHE_STORE=redis`, `REDIS_CLIENT=phpredis`, `REDIS_PASSWORD` (sync) aux envVars de `leopardo-queue-worker` et `leopardo-scheduler`.
3. render.yaml : recopier le bloc `MAIL_*` du web service vers `leopardo-scheduler` (ou créer un `envVarGroups` partagé).
4. Vérification : grep CI qui échoue si `CACHE_DRIVER` réapparaît ; revue manuelle du plan Render (`render blueprint` diff).
