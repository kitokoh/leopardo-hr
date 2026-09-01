# Feature Specification: Session QA Expert 8 2026-08-15 — audit infrastructure & périphérie (CI, edge, outillage, proxy web)

**Feature Branch**: `docs/qa-expert8-infra-2026-08-15`
**Created**: 2026-08-15 | **Status**: Draft
**Input**: Session QA 360° (runtime production + audit statique) ciblant les zones **non couvertes** par les 7 sessions experts du 2026-08-15 : workflows CI (39 fichiers), edge/, dev-hub/, postman/, render.yaml, docker-compose.yml, proxy/API routes de la vitrine, middleware web. Anti-doublon vérifié contre les 205+ issues ouvertes et les 130 branches distantes au 2026-08-15 ~15:30 UTC (registre détaillé : `findings-registry.md`).

## User Scenarios & Testing

### User Story 1 — Un drill de restauration ne peut pas détruire la base source (Priority: P1)

`dev-hub/scripts/backup_drill.sh:67-68,174` exécute `DROP SCHEMA ... CASCADE` sur `RESTORE_DB_URL` sans vérifier qu'elle diffère de `DATABASE_URL`. Un copier-coller d'env = perte de données production. → **#3518**

**Pourquoi P1** : risque de perte de données irréversible sur la donnée paie/PII des tenants — le pire scénario possible pour un HR OS.

**Test indépendant** : `RESTORE_DB_URL=$DATABASE_URL bash dev-hub/scripts/backup_drill.sh` doit échouer immédiatement avec un message explicite, avant toute connexion.

**Acceptance Scenarios**:
1. **Given** `RESTORE_DB_URL` identique à `DATABASE_URL`, **When** on lance le script, **Then** il refuse de tourner (exit ≠ 0, message clair).
2. **Given** `RESTORE_DB_URL` vide, **When** on lance le script, **Then** refus explicite.
3. **Given** une cible distincte valide, **When** `CONFIRM_RESTORE=YES` est absent, **Then** le script demande une confirmation explicite.

### User Story 2 — La CI « Jobs & Queues Contracts » se déclenche sur le code réel (Priority: P1)

`.github/workflows/backend-jobs-ci.yml:10-12,21-23` filtre sur des chemins pré-modulaires supprimés (`api/app/Services/WebhookDispatcher.php`, `PayrollService.php`, `Http/Controllers/Api/V1/PayrollRunController.php`). Les évolutions du dispatcher Billing (webhooks Stripe/payouts — chemins argent) ne déclenchent plus ce workflow. → **#3519**

**Pourquoi P1** : trou de couverture CI silencieux sur les chemins argent, en contradiction avec la Constitution §IV.

**Test indépendant** : un commit touchant `api/app/Modules/Billing/Infrastructure/Services/WebhookDispatcher.php` déclenche le workflow (vérifier via `gh run list --workflow backend-jobs-ci.yml`).

**Acceptance Scenarios**:
1. **Given** les `paths:` corrigés vers la structure `Modules/`, **When** on pousse un changement sous `api/app/Modules/Billing/`, **Then** le workflow se déclenche.
2. **Given** un path listé dans un workflow, **When** il ne matche aucun fichier, **Then** une garde CI le signale (nouveau check ou extension d'un check existant).

### User Story 3 — Zéro credential réel dans les artefacts du repo (Priority: P2)

La collection Postman embarque `admin@leopardo-rh.com` / `password123` en clair (3 requêtes, lignes 188/208/268 — mot de passe historiquement réel d'après `render.yaml`), et `dev-hub/tools/staging-demo-auth-smoke.sh:13-17` retombe sur `password123` par défaut. Repo **public**. → **#3520**, **#3521**

**Pourquoi P2** : fuite de credentials historiques + normalisation d'un secret faible ; Constitution §V « zéro secret dans le code ».

**Test indépendant** : `grep -rn 'password123' postman/ dev-hub/ examples/` ne retourne plus de body/script exécutable (uniquement docs marquées « exemple fictif » le cas échéant).

**Acceptance Scenarios**:
1. **Given** la collection Postman, **When** on inspecte les bodies auth, **Then** ils référencent `{{admin_email}}` / `{{admin_password}}` (variables d'environment non commitées).
2. **Given** le script smoke, **When** la variable de mot de passe est absente, **Then** il échoue explicitement (`:?required`), sans défaut.
3. **Given** la fuite historique, **When** la PR est ouverte, **Then** une note recommande la rotation du compte admin production.

### User Story 4 — Le gate web et le proxy API dégradent proprement (Priority: P2)

Deux failles de robustesse côté vitrine : (a) `front/web/src/middleware.ts:24` n'exige que la *présence* du cookie `leopardo_token` — un cookie forgé franchit le gate serveur et sert le HTML dashboard ; (b) `front/web/src/app/api/v1/[...path]/route.ts:64` n'a **aucun try/catch** — une panne backend renvoie une 500 HTML au lieu d'une 502 JSON exploitable. → **#3522**, **#3523**

**Pourquoi P2** : (a) faux sentiment de sécurité + surface dashboard exposée ; (b) toute l'API proxifiée devient inparseable par les clients quand le backend tousse.

**Test indépendant** : (a) requête avec cookie forgé → comportement documenté/test automatisé ; (b) mocker `fetch` rejeté → réponse 502 JSON `{error: 'backend_unavailable'}`.

**Acceptance Scenarios**:
1. **Given** un cookie `leopardo_token` forgé, **When** on accède à une route dashboard, **Then** le comportement est soit une redirection login (validation forme du token), soit explicitement documenté comme gate cosmétique avec test de non-régression.
2. **Given** le backend injoignable, **When** le proxy reçoit une requête, **Then** il répond `502` JSON + log structuré, jamais de stack HTML.
3. **Given** la correction, **When** `npm run lint && npm run build` (front/web), **Then** tout est vert.

### User Story 5 — Versioning & bootstrap d'infrastructure véridiques (Priority: P2)

Quatre écarts déploiement/version : `api/.env.example:10` épingle `APP_VERSION=4.23.5` (prod `/health` rapporte 4.23.5 au lieu de 4.24.0 — vérifié en live) → **#3528** ; `edge/install.sh:63` exécute en root un compose téléchargé sans vérification d'intégrité → **#3529** ; `edge/docker-entrypoint.edge.sh` avale `migrate`/`route:cache`/`event:cache` via `|| true` → **#3530** ; `render.yaml:9` nomme le service `leopardo-api` (hostname ≠ `gestionemployerbackend.onrender.com` attendu par CORS/README) → **#3531**.

**Pourquoi P2** : observabilité faussée (version), supply-chain root sur nœuds biométriques (install.sh), healthcheck mensonger (entrypoint), blueprint Render cassé (render.yaml).

**Test indépendant** : `grep APP_VERSION api/.env.example` = valeur de `config/app.php` ; `bash -n edge/install.sh` contient une vérification sha256 ; entrypoint sans `|| true` sur migrate ; `render.yaml` `name: gestionemployerbackend`.

**Acceptance Scenarios**:
1. **Given** `.env.example`, **When** on compare à `config/app.php`, **Then** les versions concordent (+ garde CI optionnelle).
2. **Given** `edge/install.sh`, **When** le checksum ne correspond pas, **Then** l'installation aborte.
3. **Given** un échec de migration au boot edge, **When** l'entrypoint tourne, **Then** le conteneur échoue ou se marque `degraded`, jamais « sain » silencieusement.
4. **Given** `render.yaml`, **When** on déploie le blueprint, **Then** le hostname par défaut matche la config CORS.

### User Story 6 — Gouvernance CI alignée avec la réalité (Priority: P2/P3)

Quatre écarts de gouvernance : `cancel-in-progress: true` annule CodeQL/scans sur main pendant les vagues de merges (uploads security-events manquants) → **#3532** ; CODEOWNERS « 1 approval » décoratif vs protection canonique 0 review → **#3533** ; `fix-composer-lock.yml` peut pusher directement (bypass checks) → **#3534** ; job stub `mobile-flutter-stable-compat` maintient un contexte hors canon → **#3538**.

**Pourquoi P2/P3** : la Constitution §VII fait de la CI la source de vérité ; ces écarts affaiblissent les garanties (sécurité) ou entretiennent l'ambiguïté (gouvernance).

**Acceptance Scenarios**:
1. **Given** un merge sur main, **When** un second merge suit immédiatement, **Then** les workflows sécurité (CodeQL, secret-scan) du premier run **ne sont pas annulés** (`cancel-in-progress` conditionnel sur `github.ref != 'refs/heads/main'`).
2. **Given** CODEOWNERS, **When** on lit le commentaire protection, **Then** il reflète la protection réelle (ou la protection exige réellement des reviews).
3. **Given** `fix-composer-lock.yml`, **When** il s'exécute, **Then** il ouvre une PR au lieu de pusher sur la branche protégée.
4. **Given** `tests.yml`, **When** on liste les required contexts canoniques, **Then** aucun stub ne prétend en maintenir un hors canon.

### User Story 7 — Dette technique web/api résiduelle (Priority: P3)

Trois nettoyages : toolchain MDX morte dans `front/web/package.json` (next-mdx-remote, gray-matter, reading-time, rehype-*, remark-gfm, ts-node — 0 import) → **#3535** ; `/integrations` sans layout (aucune metadata) + `guides/layout.tsx` « Checklist Paie 2024 » périmé → **#3536** ; `EdgeSyncDaemonCommand.php:30-41` lit `env()` hors `config/` (silencieusement nul après `config:cache`) → **#3537**.

**Pourquoi P3** : hygiène supply-chain, SEO, configuration — faible impact immédiat, dette cumulative.

**Acceptance Scenarios**:
1. **Given** `package.json` web, **When** on grep chaque dépendance retirée, **Then** build + lint restent verts.
2. **Given** `/integrations`, **When** on inspecte le HTML, **Then** title/description/canonical/OG propres à la page ; guides n'annonce plus de millésime 2024.
3. **Given** `EdgeSyncDaemonCommand`, **When** `config:cache` est joué, **Then** toutes les clés lues passent par `config()`.

## Requirements

### Functional Requirements
- **FR-001** : `backup_drill.sh` refuse toute exécution dont la cible est vide, égale à `DATABASE_URL`, ou non confirmée explicitement.
- **FR-002** : Les `paths:` de `backend-jobs-ci.yml` pointent vers la structure `api/app/Modules/` réelle ; une garde détecte les paths orphelins.
- **FR-003** : Aucun credential (réel ou historique) en clair dans `postman/`, `dev-hub/`, `examples/` — variables d'environnement obligatoires.
- **FR-004** : Le proxy `/api/v1/[...path]` retourne une 502 JSON contrôlée sur panne backend ; le comportement du gate middleware est soit durci soit documenté + testé.
- **FR-005** : `APP_VERSION` exemple = version `config/app.php` ; `/health` production rapporte la version réelle après redéploiement.
- **FR-006** : `edge/install.sh` vérifie l'intégrité (sha256 épinglé) avant toute exécution privilégiée.
- **FR-007** : L'entrypoint edge ne masque plus les échecs de migration/cache.
- **FR-008** : `render.yaml` produit un hostname aligné sur `cors.php`/README.
- **FR-009** : Les workflows sécurité sur `main` ne sont jamais annulés par concurrence (`cancel-in-progress` conditionnel).
- **FR-010** : CODEOWNERS/protection canonique cohérents ; `fix-composer-lock.yml` ouvre une PR ; stub `mobile-flutter-stable-compat` supprimé ou canonisé.
- **FR-011** : Dépendances MDX mortes retirées ; metadata `/integrations` + description `guides` corrigées ; `env()` hors `config/` éliminés d'`EdgeSyncDaemonCommand`.

## Success Criteria
- **SC-001** : Chaque issue #3518-#3523 / #3528-#3538 a une PR `fix/<issue>-<slug>` verte (5 checks requis) avec `Closes #N` dans le body.
- **SC-002** : Aucune régression : lint/build web verts, actionlint vert, scripts shell vérifiables via `bash -n` + shellcheck.
- **SC-003** : Les corrections P1 (#3518, #3519) sont démontrées par un test d'échec explicite (script refuse / workflow se déclenche).

## Assumptions
- Le mot de passe `password123` n'est plus actif en production (rotation recommandée quand même, note dans #3520).
- La correction du gate middleware (#3522) peut se limiter à durcissement formel + test, la donnée restant protégée par l'API.
- Les corrections edge (#3529, #3530) ne peuvent pas être validées par un boot réel dans cette session (pas de Docker local) — validation statique + shellcheck + revue.
