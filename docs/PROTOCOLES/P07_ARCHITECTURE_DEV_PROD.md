# P07 — Architecture à deux volets dev/prod : surveillance continue de la santé

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** gardien technique infra (exécution) + PM (arbitrages)
> **Portée :** les deux volets d'architecture (dev = continu sur `main` ; prod = stable, Release
> taguée) sur **Render, Vercel, Cloudflare Pages/Workers, Neon et Mailgun**, et la cohérence entre
> la **configuration réelle** des fournisseurs et la **documentation du dépôt**.
> **Ancrage existant :** `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (topologie + leçons de dérive),
> `docs/ops/DOMAINS.md` (registre canonique des domaines), `docs/ops/DEPLOYMENT_URLS.md`,
> `docs/ops/RENDER_QUEUE_WORKERS.md`, `docs/ops/GOOGLE_OAUTH_ENV.md`, `docs/deployment/`,
> `docs/CI_CD_SECRETS.md`, workflows `deploy-main.yml` (dev) / `deploy-prod.yml` (prod),
> `render.yaml` + `render.prod.yaml`, `docs/architecture/AUDIT_ARCHITECTURE_2026-09-05.md` (précédent d'audit).

## 1. Objet

Garantir **à tout moment** que l'architecture à deux volets est saine et **réfléchie** — c'est-à-dire
que (a) dev et prod sont réellement séparés (données, secrets, domaines, déclencheurs), (b) la
configuration documentée dans le dépôt **égale** la configuration live chez les fournisseurs, et
(c) toute dérive est détectée et corrigée vite, pas découverte par un client.

Constat moteur : le dépôt a déjà subi des dérives coûteuses — `render.yaml` décrivait un Postgres
et un Redis qui n'existaient pas dans le live (#6831, corrigé 2026-09-05) ; la sémantique de
déploiement Render (HEAD de `main`, pas le commit du tag) a dû être documentée et gatée. Ce
protocole rend la surveillance **régulière et opposable**, pas ponctuelle.

## 2. Topologie de référence (vérifiée live le 2026-09-09 via API Render)

| Volet | Déclencheur | API Render | Web Vercel | Admin CF Pages | Base de données |
|---|---|---|---|---|---|
| **dev** (continu) | push `main` (`deploy-main.yml`) | `gestionemployerBackend` (free, Docker `api/Dockerfile.prod`, `/api/v1/health`) | projets `leopardo*` (workspace dev) | `leo-admin.pages.dev` | Neon (projet dev) |
| **prod** (stable) | Release taguée `vX.Y.Z` (`release.yml` → `deploy-prod.yml`) | `leopardo-prod` (free) | projets `leopardo-*-prod` (workspace prod — pattern 1 verticale = 1 projet, #6918) | `leo-admin-prod.pages.dev` | Neon (projet prod) |

Règles structurelles opposables :
1. **Un volet = un compte/workspace par fournisseur** (Vercel : 2 workspaces ; Cloudflare : 2
   comptes — dev `28a39f…`, prod `30540c…` ; Render : 2 services ; Mailgun : 2 domaines dev/prod).
2. **Zéro clé prod dans le volet dev, zéro donnée dev en prod.** Chaque volet a ses secrets
   (`docs/CI_CD_SECRETS.md`, `docs/ops/GOOGLE_OAUTH_ENV.md`) ; les clés de paiement réelles
   (Stripe/Chargily) ne vivent qu'en prod.
3. **La prod ne se déploie que par Release taguée** ; un push `main` n'atteint jamais la prod.
4. **La prod n'est jamais utilisée comme environnement de test.** Les essais passent par dev /
   sandbox / pilote (P01).
5. **Toute modification d'infra documentée dans la même PR que le code** qui la consomme
   (domaine, variable, service, garde) — `DOMAINS.md` et la topologie sont des sources de vérité
   et bougent avec le code (dérive #6831 = fichier non corrigé dans la même PR).

## 3. Registres canoniques (sources de vérité machine-vérifiables)

| Registre | Contenu | Garde associée |
|---|---|---|
| `docs/ops/DOMAINS.md` | domaines live vs target, par surface | `check-canonical-domains.sh` |
| `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` | topologie, déclencheurs, pièges (tag ≠ HEAD) | revue (non automatisée) |
| `.env.example` (par app) | variables attendues | `check-env-example-parity.sh`, `check-env-example-safety.sh` |
| `render.yaml` / `render.prod.yaml` | définition déclarative dev/prod | `check-render-env-parity.sh` |
| `dev-hub/tools/launch-workflow-contracts.json` + `render-verify-services.sh` | contrats de lancement | workflows `launch-*.yml` |

## 4. Surveillance — cadence et actions

### 4.1 Hebdomadaire (automatisé, gardien technique)
1. `main` vert : checks requis + déploiement dev réussi (`deploy-main.yml`) ;
2. healthchecks publics des deux volets : dev `gestionemployerbackend.onrender.com/api/v1/health`,
   prod `leopardo-prod.onrender.com/api/v1/health` (HTTP 200, version cohérente avec CHANGELOG) ;
3. smoke post-déploiement (`dev-hub/tools/smoke-post-deploy.sh`) ;
4. queue/workers prod (`docs/ops/RENDER_QUEUE_WORKERS.md`) : jobs en échec ? files qui grossissent ?

### 4.2 Mensuelle — « Audit de volet » (couplé à la revue des protocoles)
1. **Parité config live vs dépôt** : interroger les API fournisseurs (Render `/v1/services`,
   Vercel `/v9/projects`, Cloudflare, Mailgun) et comparer au registre : services, plans, vars
   d'env, domaines, healthchecks. Toute différence = issue `infra` prioritaire avec le diff.
2. **Parité des secrets** : les clés listées dans `docs/deployment/KEYS_A_CONFIGURER.md` et
   `docs/CI_CD_SECRETS.md` existent-elles dans les bons volets (et seulement les bons) ?
3. **Audit architecture** (précédent : `AUDIT_ARCHITECTURE_2026-09-05.md`) : revue rapide des
   décisions structurantes du mois (nouveaux BC, contrats partagés, edge, desktop P06) — la
   topologie dev/prod les absorbe-t-elle sans torsion ?
4. **Coûts & limites** : plans gratuits (Render free, CF, Vercel hobbys) — seuils approchés
   (bande passante, builds, workers) ? Un volet gratuit qui devient un goulot est une décision PM,
   pas une découverte.

### 4.3 Déclenché (hors cadence)
- Nouveau domaine / sous-domaine / verticale → mettre à jour `DOMAINS.md` + topologie **dans la
  même PR** (#6918 pattern) ;
- Incident P1 → post-mortem (`docs/ops/INCIDENTS.md`) + correction du registre si la réalité a
  divergé ;
- Rotation de secret → runbooks dédiés (`RUNBOOK_ROTATION_REDIS_1472.md`,
  `RUNBOOK_SECRET_ROTATION_PURGE.md`) + mise à jour des registres.

## 5. Définition de « volet sain » (DoD de l'audit mensuel)

| Critère | Preuve |
|---|---|
| Séparation stricte dev/prod (données, secrets, domaines, déclencheurs) | Audit §4.2.1-2 sans écart |
| Registres à jour (`DOMAINS.md`, topologie, `.env.example`, yaml Render) | Zéro diff live vs dépôt |
| Prod joignable + versionnée + déployée par Release | Healthcheck prod + dernier deploy = dernière Release |
| Dev continu et vert (main → dev) | deploy-main vert sur le dernier commit |
| Rollback documenté et praticable (API + frontends) | `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md` à jour |
| Pas de dette d'audit ouverte > 1 mois | Issues `infra` closes dans le délai |

## 6. Gardes & indicateurs

- **Existant :** `deploy-main.yml`, `deploy-prod.yml`, `deploy-staging.yml`, `render.prod.yaml`,
  `check-render-env-parity.sh`, `check-canonical-domains.sh`, `check-env-example-*.sh`,
  `launch-observability-smoke.yml`, `launch-api-profile-smoke.yml`, `queue-supervision.yml`,
  `queue-worker-fallback.yml`, `database-backup.yml`, `cleanup-orphan-runs.yml`.
- **À créer (issues) :** (a) workflow `infra-audit.yml` (hebdo : healthchecks dev+prod + rapport
  automatique de parité via les API fournisseurs) ; (b) script `check-render-live-vs-yaml.sh`
  (diff services/vars live vs `render.yaml`/`render.prod.yaml`, généralisation de la leçon #6831) ;
  (c) checklist d'audit mensuel versionnée dans `docs/ops/` (template).
- **Indicateurs :** nombre d'écarts live-vs-dépôt (cible 0 à la fin de chaque audit) ; délai de
  correction d'une dérive (cible < 1 semaine) ; incidents P1 dus à une dérive d'infra (cible 0).

## 7. Rôles

| Rôle | Responsabilités |
|---|---|
| Gardien technique infra | Exécute hebdo/mensuel, crée les issues `infra`, corrige les dérives, tient les registres |
| PM | Arbitre les coûts/limites des volets, décide les migrations d'infra, signe les post-mortems |
| Agents | Toute PR touchant l'infra met à jour registres + yaml dans la même PR (règle 5 du §2) |

## 8. Revue mensuelle — questions spécifiques

- [ ] La topologie documentée est-elle encore la topologie réelle (0 écart) ?
- [ ] Les deux volets ont-ils chacun leur rôle clair — ou dev est-il devenu une « demi-prod » ?
- [ ] Un fournisseur/plan approche-t-il une limite (coût, quota) qui exige une décision PM ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — état live vérifié via API Render/Vercel ; consolidation de la leçon #6831 en surveillance régulière |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
