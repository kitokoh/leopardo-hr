# Feature Specification: Edge — installation réparée via URLs Render + intégrité (issue #3770)

**Created**: 2026-08-15

**Status**: Ready for implementation

**Input**: Issue #3770 — l'installation du nœud Edge (`edge/install.sh`) échoue en prod : les fichiers servis (install.sh, docker-compose.yml, Caddyfile.edge) retournent 404. Complète #3591. L'installation doit utiliser les endpoints Render réels (pas les domaines NXDOMAIN) et vérifier l'intégrité des téléchargements (#3529).

## Contexte technique (root cause identifiée)

- `EdgeDownloadController` résout les fichiers via `base_path('edge/install.sh')` → résout vers `api/edge/install.sh` (base_path Laravel = `api/`). Or les fichiers vivent à la racine du monorepo (`edge/install.sh`, `edge/docker-compose.yml`, `edge/Caddyfile.edge`) → **404 systématique** (`abort_unless(file_exists(...))`).
- L'image Docker de prod (`api/Dockerfile.prod`, contexte racine, `.dockerignore` n'exclut PAS `edge/`) ne copie que `api/` et `shared/i18n` → même après correction du chemin, le fichier est absent de l'image → il faut `COPY edge/ /edge`.
- `edge/install.sh` télécharge compose + Caddyfile depuis `$CLOUD_URL/api/v1/edge/download/...` sans vérification d'intégrité autre que non-vide / grep léger (#3529 : `curl|sh` sans hash).
- `edge/docker-compose.yml` pointe déjà `CLOUD_API_URL` vers `https://gestionemployerbackend.onrender.com` (aligné #3706) ; `Caddyfile.edge` est local (`:80`) — RAS.
- `edge/README.md` ne documente pas la commande d'installation canonique.

## User Scenarios & Testing

### US1 — Les assets Edge sont servis par l'API en production (Priority: P1)
`GET /api/v1/edge/install.sh`, `/api/v1/edge/download/docker-compose.yml`, `/api/v1/edge/download/Caddyfile.edge` retournent 200 avec le contenu réel du repo, y compris depuis l'image Docker prod.

**Acceptance Scenarios**:
1. **Given** l'API en local (développement), **When** `GET /api/v1/edge/install.sh`, **Then** 200 et le contenu == `edge/install.sh` du repo.
2. **Given** l'image Docker prod construite depuis `Dockerfile.prod`, **When** `docker run` + `GET /api/v1/edge/download/docker-compose.yml`, **Then** 200 (fichier présent via `COPY edge/ /edge`).
3. **Given** un chemin résolu avec `base_path('../edge/...')`, **When** le fichier existe, **Then** pas de 404 ; fallback `base_path('edge/...')` documenté.

### US2 — Le script d'installation vérifie l'intégrité des téléchargements (Priority: P1, cf. #3529)
`install.sh` télécharge les fichiers et vérifie leur hash SHA-256 contre un manifeste servi par l'API avant de les écrire.

**Acceptance Scenarios**:
1. **Given** `GET /api/v1/edge/download/sha256.txt`, **Then** 200 avec `<sha256>  <filename>` par ligne pour `install.sh`, `docker-compose.yml`, `Caddyfile.edge`.
2. **Given** `install.sh --node-id X --token Y`, **When** les téléchargements réussissent, **Then** chaque fichier est vérifié contre le manifeste et un échec de hash interrompt l'installation (exit ≠ 0, message clair).
3. **Given** un manifeste indisponible (réseau coupé), **When** l'installation tente la vérification, **Then** échec explicite (pas d'écriture de fichiers non vérifiés) — fail-closed.

### US3 — L'installation documentée est exécutable sans 404 (Priority: P1)
La commande canonique de `edge/README.md` (`curl -fsSL <api>/api/v1/edge/install.sh | sudo bash -- --node-id ... --token ...`) télécharge sans 404 et produit un dossier `/opt/leopardo-edge` complet.

**Acceptance Scenarios**:
1. **Given** le README mis à jour, **When** la commande canonique est exécutée, **Then** aucune URL morte (404/network) n'apparaît.
2. **Given** un docker-compose téléchargé, **When** inspecté, **Then** `CLOUD_API_URL` = `https://gestionemployerbackend.onrender.com` (ou override `--cloud`).

## Requirements

- FR-1: `EdgeDownloadController` — résoudre les assets via `base_path('../edge/<file>')` avec fallback `base_path('edge/<file>')` ; refactoriser la résolution dans une méthode privée.
- FR-2: Nouvel endpoint public `GET /api/v1/edge/download/sha256.txt` (throttle 60,1) servant les SHA-256 des trois fichiers clés.
- FR-3: `api/Dockerfile.prod` — ajouter `COPY edge/ /edge` (après `COPY shared/i18n`), commentaire issue #3770/#3591.
- FR-4: `edge/install.sh` — `set -euo pipefail` ; télécharger `sha256.txt` et vérifier chaque fichier (`sha256sum -c`), fail-closed ; garder le check Caddyfile existant ; message d'erreur clair.
- FR-5: `edge/README.md` — documenter prérequis (Docker, ports 80/7878/7879), commande d'installation canonique, override `--cloud`, et vérification d'intégrité.
- FR-6: Test Feature `EdgeDownloadControllerTest` (ou extension d'une suite Edge existante) : 200 + contenu pour les 3 assets, 200 + lignes attendues pour `sha256.txt`, 404 pour un fichier inconnu.
- FR-7: Entrée `CHANGELOG.md` sous `## [Unreleased]` → `### Fixed` (Closes #3770, complète #3591, #3529).
