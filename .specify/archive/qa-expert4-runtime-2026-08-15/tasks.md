# Tasks: Session QA Expert 4 — Runtime & Merge Campaign (2026-08-15)

**Input**: spec.md + findings-registry.md

**Prerequisites**: spec.md (required)

> **Anti-doublon (Constitution §VII + #2400)** : les constats déjà couverts par
> les vagues parallèles sont référencés, PAS dupliqués (voir table C/D du
> findings-registry). Les implémentations de la session sont déjà livrées
> (#3175/#3177/#3178/#3184) ; les tâches ci-dessous sont les reliquats.

## Phase 1 — Déploiement staging (US1)

- [ ] T-E4-001 [P1] [US1] Désengorger la file CI (cancel-orphan-runs.sh --superseded, cron #2413) jusqu'à ce que `deploy-staging.yml` démarre et termine en success sur main.
- [ ] T-E4-002 [P1] [US1] Smoke post-deploy staging : version ≥ main, `/api/v1/supported-countries`, `/i18n/catalog/fr`, `/api-explorer`, `/api/v1/demo-users`, `/api/v1/auth/me` (Accept: application/json → 401 JSON).
- [ ] T-E4-003 [P2] [US1] (Si le smoke échoue) diagnostiquer le workflow deploy (secrets, concurrency, entrypoint Render) — suivre `docs/GESTION_PROJET/RUNBOOK_MARKETING_ROLLBACK.md` en cas de rouge persistant.

## Phase 2 — Cohérence canonicals (US2)

- [ ] T-E4-004 [P2] [US2] `front/web/src/lib/site.ts` vs `site-url.ts` : un seul `DEFAULT_SITE_URL` (leopardo-rh.com, domaine de marque documenté dans DEPLOYMENT_PRODUCTION.md) — l'autre fichier importe la constante partagée ; zéro défaut divergent.
- [ ] T-E4-005 [P3] [US2] Vérifier sitemap.ts / robots.ts / layouts landing : tous les canonicals passent par la source unique.

## Phase 3 — Nettoyage (US3)

- [ ] T-E4-006 [P3] [US3] Supprimer la branche distante `fix/qa-omnichannel-web-2026-08-15` (contenu fusionné via #2891, vérifié).
- [ ] T-E4-007 [P3] [US3] `stores/realtime.js:308` : id de repli déterministe (horodatage + compteur ou `crypto.randomUUID`) au lieu de `Date.now() + Math.random()`.

## Phase 4 — Gouvernance (US4, délivrable processus)

- [ ] T-E4-008 [P3] [US4] Décision D-E4-01 (essai 14 jours) : commentaire sur #2909/#2721 signalant l'arbitrage propriétaire demandé (le texte initial demandait 30 jours).
- [ ] T-E4-009 [P3] [US4] Suivi #2627/#2632/#2654 jusqu'à résolution effective (ne pas fermer tant que staging n'est pas à jour).

## Déjà livré dans la session (trace)

- #3055 leave-balances garde rôle → PR #3177
- #3034/#3036/#3037/#3038 cockpit admin → PR #3175
- #3022 clés i18n OTP → PR #3178
- #3058 webhook secret → PR #3184
- #2697/#2699 fermées avec preuve code
- Doublons #2982/#3112/#3115 fermés avec renvoi ; #3132 fermée (redondante)
- 84 runs orphelins/supersédés annulés (file CI)
