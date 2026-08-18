# Plan de correction — Session QA 2026-08-17 (bilan chef de projet)

Session QA complète : vitrine live, API prod, tests locaux sur main, revue des
audits 08-15/08-16. Ce document est le tableau de bord de résolution des issues
créées par la session — **statut vérifié le 2026-08-18 à l'aube**.

## Résolution par priorité

| Priorité | Issue | Sujet | Statut (2026-08-18) |
|---|---|---|---|
| P0 | #4947 | POST /api/v1/employees → 500 (NOT NULL password_hash) | ✅ Corrigé (PR #4964, mergée) |
| P1 | #4948 | Trial guidé bloqué « pending » (worker queue Render) | 🟡 Infra Render — hors code, à re-tenter en prod |
| P1 | #4949 | Trial OTP self_service → 500 en live (post #4874) | ✅ Corrigé (durcissement findExistingManager) |
| P1 | #4950 | Checkout sandbox actif en prod (env Vercel) | ✅ Corrigé (garde NODE_ENV=production, #4995) |
| P1 | #4931 | GET avec effets de bord / secrets en query string | ✅ Corrigé (billing/portal POST, X-Token) |
| P1 | #4930 | Verbes HTTP incohérents (approve/reject) | ✅ PR #5010 — convention POST + alias dépréciés |
| P1 | #4932 | Doublons de routes à déprécier | ⏳ Ouvert |
| P2 | #4951 | Pricing 14 vs 30 jours (live) | ✅ PR #5016 — unification 14 jours ×4 locales |
| P2 | #4952 | Tunnel paiement KO (CHECKOUT_UNAVAILABLE, pas de clé Stripe) | 🟡 Décision produit / clé Stripe requise |
| P3 | #4953 | Label « v4.16 » obsolète sur login admin | ✅ Corrigé (PR #4967) |
| P3 | #4954 | Bouton « Acces Demo » en prod | ✅ Déploiement stale (retiré du code #4511) |
| P3 | #4955 | Réponses 429 non localisées | ✅ Corrigé (PR #4965) |
| P3 | #4956 | « 6 pays » vs 19 codes réels sur /about | ✅ Corrigé (mergé sur main) |
| P3 | #4936 | Drift « 19 modules » vs 18 dossiers | ✅ Corrigé (docs alignées) |
| P3 | #4943 | CSS orphelin vitrine/styles | ✅ Purge (merged) |
| P3 | #4945 | Specs e2e exclues du lint | ✅ Corrigé (lint src e2e) |

## Sujets non dupliqués (déjà suivis)
#2646 (demo-users prod), #2906 (blog), #3765/#3766 (stabilisation prod),
#4842 (OpenAPI drift), #4867 (vitrine en retard sur main), #4574 (portail i18n).

## Reste à traiter (code)
- **#4929 (P0)** onboarding — contrat fragmenté (3 jeux d'étapes concurrents).
- **#4933 (P2)** trous CRUD métier (expense-claims, absences, training, loans…).
- **#4942 (P2)** unifier le système de boutons web.
- **#4944 (P2)** e2e des parcours métier web.
- **#4972 (P2)** migrer les 16 derniers tests Feature vers RefreshTenantDatabase.
- **#4978/#4980 (P1)** stabiliser la baseline PostgreSQL CI (suites flaky).

## Leçons retenues
1. Vérifier les déploiements stale avant d'ouvrir un bug « reproductible en
   prod » (#4954, #4867).
2. Une env var mal configurée (CHECKOUT_SANDBOX) vaut un bug P1 — durcir côté
   code (NODE_ENV guard) pour rendre l'erreur de config structurellement
   impossible (#4950).
3. Les actions d'approbation doivent être POST (convention #4930) — les
   clients Flutter consomment les anciens verbes : conserver des alias
   dépréciés le temps de la migration.
