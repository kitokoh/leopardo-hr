# Session QA — Expert Software Engineer / Senior QA (2026-08-16, après-midi)

**Agent**: Expert SWE / QA — `kitokoh` — session multi-agents concurrente
**Périmètre**: Phase 0 (main vert + vérification audits précédents), Phase 1
(audit 360° + issues spec-kit), Phase 2/3 (implémentations).

## Contexte

Swarm très actif (expert14b → expert20 + sessions qa-360) : au début de la
session, ~70 branches, ~56 issues ouvertes, ~32 PRs. `main` bougeait toutes les
2-3 minutes (famine CI #3545 : runs annulés en pending, file saturée).

## Phase 0 — Main vert & vérification des audits précédents

### Trouvaille majeure : test rouge sur main

`PartnerApplyTest::test_apply_persists_contact_fields` était **rouge sur
main** : le fix #4186 (PR #4211) avait ajouté colonnes + fillable + propagation
dans `PartnerService::apply`, mais la validation inline de
`PartnerDashboardController::apply` ne déclarait que `type`/`payment_details`
→ `name/email/phone/website/commission_rate` silencieusement écrasés.
Issue **#4383** créée + fix (**PR #4390**) : validation étendue + cast `float`
sur `commission_rate` (decimal(6,4) → « 0.15 » propre). Test re-vert local
(2 passed) ; Pint OK ; PHPStan strict : 0 erreur sur les fichiers modifiés.

### Vérification des audits précédents (expert19 #4169→#4182) sur main

Tous vérifiés par scan de code : CsvCellSanitizer (#4169), _skipAuthRedirect
(#4170), fallback errors.SERVER_ERROR (#4171), PlatformCompanyLookup (#4172),
html lang SSR (#4173), useVitrineLocale branding (#4174), docs-page.ts
(#4175), cartes /careers cliquables (#4176), noindex /offline (#4177), lien
docs /integrations (#4178), liens iOS env-driven (#4180), toast impersonation
masqué (#4181), composants morts supprimés (#4182). **Seul #4186 était
incomplet** (le contrôleur) → #4383.

### Environnement local backend (outillage installé)

PHP 8.4.24 + Composer + PostgreSQL 14 + Redis 7 installés dans le sandbox.
Bootstrap multi-schéma reproduit (public + shared_tenants, `DB_SEARCH_PATH=public,shared_tenants`
pour les tests — ordre critique, cf. phpunit.xml). Leçon : une base sale
(runs interrompus) empoisonne `migrate:fresh` (types composites orphelins) —
toujours drop+recreate la base avant une passe de tests.

## Phase 1 — Audit 360° (constats vérifiés)

### Sondes prod (gestionemployerbackend.onrender.com) — 13:40 UTC

| Endpoint | Résultat 13:40 | Résultat 14:20 (post-deploy v4.24.0) |
|---|---|---|
| /api/v1/health | 200 | 200 — **redis.ok=false (ConnectionException)** |
| /api/v1/supported-countries | 200 | 200 |
| /api/v1/i18n/catalog/fr | 200 | 200 |
| POST /api/v1/auth/login | **500 body vide** | **401 INVALID_CREDENTIALS** ✓ |
| GET /api/v1/demo-users | **500 body vide** | **404 (hard gate délibéré)** |
| GET /api/v1/bank-exports | 500 | **401** ✓ (contrat smoke #4289) |
| GET /docs | 200 | 200 |

Cause des 500 : **file de déploiement bloquée** (7 runs Deploy queued,
famine #3545) — prod servait un build périmé (errors.php ParseError etc.).
Le déblocage (runs superseded annulés + queue drainée) a livré v4.24.0 qui a
résolu les 500. Restent : **Redis KO** (#4461) et **parcours démo** (#2646 :
401 INVALID_CREDENTIALS + /demo-users 404 — seeder démo non provisionné).

### Issue #4448 — Portails carrières par tenant 100 % FR

`[companySlug]/careers`, `jobs/[jobId]` et `ApplyForm.tsx` : métadonnées +
libellés + formulaire 100 % FR codés en dur (résiduel #4176 non couvert).

### Vérifiés sans anomalie

XSS : pas de `v-html` admin, pas de `innerHTML` mobile ; `dangerouslySetInnerHTML`
web = JSON-LD/analytics uniquement. `validatePassword` (lib/validation.ts) :
messages FR mais fonction **morte** (aucun import) — non prioritaire.

## Phase 2/3 — Implémentations

| Issue | PR | Surface | Contenu | Validation |
|---|---|---|---|---|
| #4383 (red test main) | **#4390** | api | PartnerApply validation + cast commission_rate | PartnerApplyTest 2/2, Pint, PHPStan |
| #4448 (carrières tenant) | **#4460** | web | tenant-careers.ts ×4 + metadata SSR (x-vitrine-lang) + ApplyForm localisé + garde test | tsc 0, eslint 0, jest 6/6 + 105 data/lib, mojibake OK |

Issues créées : #4383, #4448, #4461 (Redis prod). Commentaires de preuve
live : #4370 (sondes 500), #2646 (état démo), #4289 (bank-exports résolu).

## Leçons

1. **Le « main vert » était une illusion** : la famine CI (#3545) annulait les
   runs en pending → personne ne voyait la suite Feature rouge. Vérifier les
   tests localement avec la bonne config (`DB_SEARCH_PATH=public,shared_tenants`,
   cf. phpunit.xml l.45) AVANT de faire confiance à la CI.
2. **Les 500 body-vide en prod = souvent build périmé** : diagnostiquer l'état
   du pipeline de déploiement (runs queued ? SHA servi ?) avant de chasser un
   bug applicatif. `POST /auth/login` 500 + `demo-users` 500 + `bank-exports`
   500 sur un health 200 → pipeline bloqué, pas le code.
3. **Redis dégradé est invisible** : le health global reste `ok` avec
   `redis.ok=false` — ajouter un signal explicite si le drapeau « degraded »
   ne remonte pas.
4. **Les portails par tenant échappent aux audits de la vitrine** :
   `[companySlug]/careers` n'était couvert par aucune garde i18n — penser aux
   routes dynamiques tenant dans les scans anti-littéral-FR.
