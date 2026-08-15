# Tasks — Edge installation réparée (#3770)

## T1 — Backend : servir les assets depuis la racine du repo
- [x] `EdgeDownloadController::resolveEdgeAsset()` — `base_path('../edge/…')` + fallback `base_path('edge/…')`.
- [x] `api/Dockerfile.prod` — `COPY edge/ /edge`.

## T2 — Manifeste d'intégrité
- [x] `GET /edge/download/sha256.txt` (3 assets).
- [x] `GET /edge/download/docker-compose.yml.sha256` + `Caddyfile.edge.sha256` (compat #3529).

## T3 — install.sh fail-closed
- [x] `set -euo pipefail`.
- [x] Téléchargement du manifeste avant tout asset ; `verify_download` par fichier.
- [x] Gardes de taille + contenu Caddyfile conservés.

## T4 — Documentation & tests
- [x] `edge/README.md` : commande canonique via l'API Render, prérequis, options, intégrité.
- [x] `EdgeDownloadControllerTest` : 200 + contenu réel, manifeste cohérent, 404 inconnu.
- [x] CHANGELOG `### Fixed` (Closes #3770, complète #3591/#3529).
