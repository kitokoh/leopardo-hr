# Session QA — Expert Agent-360 (2026-08-16, après-midi)

**Agent**: agent-360 — audit 360° indépendant + implémentation (kitokoh/leopardo-hr)
**Périmètre**: Phase 0 (merges/PRs ouvertes), Phase 1 (audit 360° → issues spec-kit),
Phase 2/3 (implémentation des findings).

---

## Phase 0 — PRs ouvertes & branches

- **6 PRs ouvertes synchronisées avec main** (#4270, #4275, #4276, #4277, #4279, #4280) :
  merges `origin/main` + résolutions de conflits poussées. La meute a mergé 5 d'entre elles
  (#4275/#4276/#4277/#4279/#4280) entre-temps.
- **PR #4270 (ADR-0014) fermée sans merge par un agent concurrent** (branche supprimée) →
  contenu ré-atterri via **PR #4456** (`fix/4394-adr-0014-plans-canoniques`) avec alignement
  COMPLET : seeder 79€/66€/200 emp/Free 30j, `planCode` ×4 plans ×4 locales, FAQ tarifs +
  checkout alignés à 200 (la PR #4270 avait oublié FAQ/checkout — incohérence 200 vs 250
  détectée à l'audit), `localizedCanonical` #4201 préservé.
- **Leçons de collision** : 2 branches dupliquées supprimées (#4400 — fix équivalent de la
  meute conservé, #4416 — merge swarm #4422 conservé), #4401/#4403/#4404/#4396/#4414 laissés
  aux agents qui les avaient claimés (le nom de branche EST le lock), #4402 clôturé avec
  preuve (déjà corrigé par #4348/#4321).

## Phase 1 — Audit 360° (findings nouveaux, vérifiés sur le code)

**22 issues spec-kit créées (#4395 → #4417)**, format Constat/Cause racine/Fix attendu/
Critères d'acceptation, label `qa-audit-2026-08-16` :

| # | Sévérité | Surface | Sujet |
|---|----------|---------|-------|
| 4395 | P1 | API/i18n | Trial signup — 6 messages FR dans VerifyTrialSignup (funnel onboarding EN/TR/AR) |
| 4396 | P2 | API/i18n | Batch 3 — ~20 chaînes FR résiduelles (9 fichiers hors #3237/#4191) |
| 4398 | P2 | API/sec | FK cross-tenant sans validation exists (manager_id/department_id) |
| 4399 | P3 | API | Jobs sans failed() — SendTrialDripEmailJob + PublishScheduledPostJob |
| 4400 | P2 | Web/seo | hreflang fr → URL localisée elle-même (localizedAlternates) |
| 4401 | P2 | Web/seo | Sitemap : variantes ?lang fantômes (/privacy /terms) + /offline noindex listé |
| 4402 | P2 | Web/bug | FAQ accordéon mort sur 4 pages modules (déjà corrigé #4348 → fermé) |
| 4403 | P2 | Web/seo | JSON-LD global : Enterprise Offer sans price (NaN) + données FR |
| 4404 | P2 | Web/i18n | Plan Enterprise AR — typo showsCurrency → CTA checkout au lieu de contact |
| 4405 | P3 | Web | Cluster résiduel : meta racine FR, hreflang /privacy-/terms, useId, env client |
| 4406 | P2 | Mobile | SyncService raw Dio() sans timeout → sync Edge peut staller pour toujours |
| 4407 | P2 | Mobile | Upload cabinet sans try/catch ×3 apps (fichier perdu, snackbar bloqué) |
| 4408 | P2 | Mobile/i18n | platform_admin ~40 chaînes FR + ApiClient core FR + widgets core FR |
| 4409 | P3 | Mobile | Cluster : marketing sans l10n, dead repos/providers, notifications, init fr_FR, demo no-op |
| 4410 | P2 | Admin/i18n | ~39 fichiers Vue FR + 10 clés manquantes dans les 4 catalogues admin |
| 4411 | P1 | Ops/edge | Schéma SQLite Edge jamais provisionné → sync offline morte en silence |
| 4412 | P2 | Ops | render.yaml — APP_URL absent des workers → URLs http://localhost dans les mails |
| 4413 | P2 | CI | bootstrap.sh : option --force inexistante + docker-compose v1 requis |
| 4414 | P2 | CI | 72/75 jobs sans timeout-minutes (runners bloqués 6h, famine #3545 récurrente) |
| 4415 | P2 | CI/sec | web-ci E2E : runs PR contre PROD avec creds démo + traces Playwright uploadées |
| 4416 | P3 | CI/sec | Creds hardcodés dans capture_screenshots.py + agent-smoke-api.sh |
| 4417 | P3 | CI/docs | Refs mortes docs/PLAN_ACTION2 (8 outils) + images flottantes + doc mobile |

**Vérifié propre** (0 finding) : scoping cross-tenant API (BelongsToCompany + fail-closed #3727),
routes mortes (0/738), TODO/FIXME app, auth manquante sur endpoints publics, actionlint sur
les 40 workflows, healthcheck render.yaml.

## Phase 2/3 — Implémentations (10 PRs agent-360)

| PR | Issue | Sujet | Statut |
|----|-------|-------|--------|
| #4424 | 4412 | render.yaml APP_URL workers | ouverte |
| #4425 | 4413 | bootstrap.sh --force + compose v2 | ouverte |
| #4426 | 4416 | creds scripts → env (DOUBLON #4422 mergé → fermée) | fermée |
| #4427 | 4417 | refs mortes PLAN_ACTION2 | ouverte |
| #4441 | 4395 | Trial signup i18n ×4 locales + test | ouverte |
| #4442 | 4398 | FK cross-tenant validées + test | ouverte |
| #4443 | 4399 | failed() sur 2 jobs + test | ouverte |
| #4452 | 4405 | cluster web (meta localisées, hreflang, useId, env) | ouverte |
| #4456 | 4394/4270 | ADR-0014 plans canoniques (ré-atterrissage complet) | ouverte |
| #4458 | 4411 | Edge SQLite provisionné + readiness | ouverte |

Spécifications spec-kit : `.specify/features/{4395,4398,4399,4411}-*/spec.md` (chacune avec
critères d'acceptation).

## Leçons

1. **Le nom de branche EST le lock** — la meute réclame les branches dans les minutes qui
   suivent la création des issues : vérifier `ls-remote` AVANT de créer une branche, même pour
   ses propres issues d'audit.
2. **Une PR fermée ≠ travail perdu si la branche est conservée localement** : #4270 fermée 2×
   sans merge ; le contenu (ADR validé) a été ré-atterri proprement via l'issue de suivi #4394.
3. **Vérifier chaque claim avant de pousser** — deux de mes branches (#4400, #4416) étaient des
   doublons de fixes de la meute ; les supprimer (pas de force-push, pas de guerre de PR).
4. **Main bouge ~1 merge/min** : merger main dans ses branches juste avant de pousser, et
   re-vérifier `mergeable_state` avant toute action.
