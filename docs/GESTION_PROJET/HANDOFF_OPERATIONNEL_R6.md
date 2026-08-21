# 🤝 Handoff opérationnel — R6 (livrable final du plan, issue #5160)

**Version** : 1.0 · **Date** : 2026-08-20 · **Auteur** : Agent PM
**Objet** : passation complète de l'exploitation Leopardo RH — pour l'équipe, les ops et les agents entrants.
**Règle d'or** : ce document est le **point d'entrée unique** ; chaque procédure détaillée vit dans son runbook dédié (index §4).

---

## 1. État du système (au 2026-08-20)

| Composant | Valeur |
|---|---|
| Version | `v4.24.0` (release 2026-08-11 ; tag git) |
| Branche canonique | `main` (protection : checks requis, PR obligatoire) |
| API (prod) | `https://gestionemployerbackend.onrender.com` (Render, web service `gestionemployerbackend`, plan starter, région frankfurt) |
| Vitrine (prod) | Vercel — **domaine `leopardo-rh.com` NXDOMAIN** (issue #3452, action DNS en attente) |
| Proxy API vitrine | `https://gestionemployer-backend.vercel.app` |
| Base de données | PostgreSQL Render `leopardo-db` (schémas `public` + `shared_tenants`, multitenancy mode `schema`) |
| Cache/Queue/Session | Redis interne Render `leopardo-redis` (plan free) — **⚠️ env live en déviation : `QUEUE_CONNECTION=database`, `CACHE_STORE/SESSION_DRIVER=file`** (issue #5172) |
| Mail | API HTTP **Mailgun** (`MAIL_MAILER=mailgun`, #5139 — le SMTP sortant est bloqué par Render) |
| Workers | ⚠️ **`leopardo-queue-worker` et `leopardo-scheduler` NON provisionnés sur Render** (issue #5172 — runbook livré, action ops requise) |
| Google OAuth | ⚠️ **`GOOGLE_CLIENT_ID/SECRET/REDIRECT_URL` absents de l'env Render** (issue #5170 — runbook livré, action ops requise) |

## 2. Qui fait quoi (RACI simplifié)

| Rôle | Personne/Équipe | Responsabilités |
|---|---|---|
| Fondateur / décideur | Kitokoh | Décisions produit (ex. #5171), exceptions freeze scope (#5147), budget |
| Ops infra | (accès Render/Vercel/Google Cloud) | Secrets, DNS, provisionnement workers, redéploiements manuels |
| Dev API/Web/Mobile | Agents + mainteneurs | PRs, fixes, tests |
| QA | Agents QA | Sessions, carnets pilotes (#5152), vérifications live |
| Pilotes DZ (×3) | PME algériennes | Feedback, cas réels de paie |

**Accès critiques à documenter** : Render (`africanovatech`), Vercel, Google Cloud (OAuth), Mailgun, Sentry, Slack (`#alerts-*`), GitHub (secrets Actions : `RENDER_DEPLOY_HOOK_URL`, `FIREBASE_*`…).

## 3. Cycle de vie d'un changement (rappel opérationnel)

1. Issue → self-assign + **branche = lock** (protocole #2400, nommage `fix/<N>-<slug>`/`feat/`/`docs/`).
2. Spec d'abord pour tout travail significatif (Spec Kit, `.specify/features/`).
3. PR vers `main` avec **`Closes #N` dans le body** (garde #2512) + **entrée CHANGELOG** (garde governance).
4. Checks requis verts (Vercel rouge = quota, non bloquant — leçon #4868) → merge.
5. Push main → `deploy-main.yml` (path-aware : API seulement si `api/**` change ; hook Render + healthcheck).
6. Vérification live (health `/api/v1/health`, parcours concernés) + mise à jour CHANGELOG/AGENTS.md si leçon.

## 4. Index des runbooks (source de vérité)

| Domaine | Runbook |
|---|---|
| Déploiement staging/prod | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` |
| Rollback | `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md` |
| Incident P1 | `docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md` |
| Observabilité (Sentry) | `docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md` |
| Alerting | `docs/GESTION_PROJET/RUNBOOK_ALERTING.md` |
| Backup/restore PostgreSQL | `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` |
| Ops (index général) | `docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md` |
| Tests locaux | `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` |
| **Google OAuth prod** | `docs/GESTION_PROJET/RUNBOOK_GOOGLE_OAUTH_PROD.md` (nouveau, #5170) |
| **Workers de queue Render** | `docs/GESTION_PROJET/RUNBOOK_RENDER_WORKERS.md` (nouveau, #5172) |
| Drills | `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` |
| Déploiement initial Render | `docs/GESTION_PROJET/RENDER_SETUP.md` |

**Scripts de garde** : `dev-hub/tools/` (gouvernance, CI, vérifs) — dont `render-verify-services.sh` (#5172).

## 5. Coûts & budget (cadence agents)

- **Budget agents** : `docs/OPS/BUDGET_AGENTS.md` (#5148) — plafond mensuel, arrêt au plafond, cadence 1 agent feature + 50 % traîne, tableau hebdo à remplir chaque vendredi.
- **Coûts infra** : Render (web starter + DB starter + Redis free), Vercel (quota 100 deploys/j gratuit — déploiements path-aware pour ne pas le brûler), Mailgun (sandbox → domaine), Sentry/UptimeRobot.
- **⚠️ Leçons CI** : le quota Vercel s'épuise (~100/j) et bloque visuellement les PR (non bloquant) ; les runs GitHub Actions peuvent ne pas se créer sous rafale (leçon sweep-qa-360) — commenter la PR et attendre plutôt que merger sans checks.

## 6. Traîne connue & risques (au handoff)

| Sévérité | Sujet | Issue | État |
|---|---|---|---|
| P0 | Google OAuth 500 prod (env manquante) | #5170 | Runbook livré — **action ops** |
| P0 | Création compte Google impossible (UNKNOWN_ACCOUNT) | #5171 | **Décision produit** (a/b) requise |
| P1 | Workers de queue absents Render | #5172 | Runbook livré — **action ops** |
| P1 | Trial OTP → 503 | #5162 | Branche agent en cours |
| P1 | DNS vitrine NXDOMAIN | #3452 | Action DNS (registrar/Vercel) |
| P1 | Bilan 60 jours | #5159 | ✅ Livré (PR PM) |
| P1 | Golden tests DZ ≥ 40 | #5149 | 31/40 — à compléter |
| P1 | Clôture DZ + benchmark 10k | #5150 | Non démarré |
| P2 | E2E funnel Playwright | #5146 | Spec posée — implémentation |
| P2 | Dette i18n (8 983 chaînes) | #2755 | En veille (freeze #5147, sauf P0) |
| P2 | Dédup Flutter `leopardo_hr`/`leopardo_manager` | #2601 | Chantier structurel — en veille |

## 7. Prochaines actions immédiates (72 h)

1. **Ops** : renseigner `GOOGLE_*` sur Render (#5170) → vérifier 302.
2. **Ops** : provisionner les 2 workers (#5172) → `render-verify-services.sh` vert + drain des `trial_provisionings` pendants (`trial-provisionings:sweep`).
3. **Produit** : arbitrer #5171 (invitation-first vs self-service sécurisé).
4. **Ops** : restaurer le DNS `leopardo-rh.com` (#3452).
5. **QA** : vérification live des parcours trial guided/OTP + Google après (1)-(2).
6. **Paie DZ** : lancer #5149 (golden ≥ 40) et #5150 (clôture + benchmark).

## 8. Critères de clôture du handoff (R6)

- [ ] Les 2 runbooks ops (#5170, #5172) sont appliqués (env + workers vérifiés)
- [ ] Tous les accès critiques sont documentés dans un coffre/vault (hors repo)
- [ ] Le bilan 60 jours (#5159) est publié et le gate J60 (17/10) planifié
- [ ] Les pilotes DZ sont signés et onboardés (#5151-#5156)

---

*Handoff généré au 2026-08-20 — dernières entrées CHANGELOG incluses (fusion 24 branches, fix P0 #5161, Mailgun #5139, deploy path-aware).*
