# QA Session 2026-08-17 — swe-qa-360 (final)

**Agent** : swe-qa-360 — mission 3 phases (issues ouvertes → merge drain → audit 360°) sur kitokoh/leopardo-hr. Contexte : concurrence multi-agents intense (kitokoh-agent), CI intermittente (saturation #3545, 503 API).

## Merge drain & convergence (protocole #2400)

| PR | Sujet | Sort |
|----|-------|------|
| #4743/#4744/#4745/#4746/#4747 | merge drain principal (env parity, URLs fail-closed, dedup runs, admin UX, tests Feature) | mergés |
| #4753, #4770, #4801, #4785, #4852 | doublons fermés avec renvoi canonique (titre i18n, URLs, Edge 4687 ×2, CI 4723 ×2, CabinetShare) | fermés |
| #4802 | #4723 duplication CI → actions composites + script partagé (−282 lignes, co-contribué) | **merged** |
| #4817 | #4787 Zkteco search_path reset/restore (routes publiques) | **merged** |
| Branches orphelines | 4555/4609/4579-dashboard-i18n/close-all-issues ×2/claim markers ×2 nettoyées | supprimées |

## Issues sécurité

- **#4687 (P2)** : issue fermée À TORT (branche citée sans contenu — vérif SHA). Rouvert + fix réel (`$hidden` EdgeNode/EdgeLicense + makeVisible + tests) — contenu absorbé par #4822 (merged).
- **#4787 (P1)** : Zkteco routes publiques — search_path reset `shared_tenants,public` + restauration finally (pattern #2689) + 4 tests. Mergé (#4817).
- **#4798 (P2)** : CabinetShare accessByToken — fix déjà sur main (TenantManager::withinTenant, plus complet) → PR fermée doublon.

## i18n — découverte critique

- **#4762 (P1)** : ARB désynchronisés (clés `userAuth*` ajoutées à la main par #4650 sans passer par shared). Ma PR de promotion (partagée ×4) fermée pour contamination de branche (worktree partagé entre sessions).
- **🔴 Build mobile cassé sur main** : le « fix » #4837 (régénération ARB sans promotion) a SUPPRIMÉ 13 getters référencés par les écrans user_auth → `flutter analyze` échoue (undefined_getter). Issue rouvert avec preuve (16/14/13 réf. par écran vs 0 getter généré).
- **Fix final (PR #4856)** : 13 clés promues dans shared/i18n ×4 (valeurs historiques #4650, chemins dot round-trip), syncs rejoués idempotents, 39/39 références couvertes ×4 locales, validate-and-sync vert.

## Audit 360° (vérifs sur main)

- `errors.*` backend : 107 clés utilisées ×4 locales — 0 manquante ✅
- `api.*` admin : 10 clés présentes ×4 ; manques résiduels (unexpectedError) suivis (#4794, assigné) ; les autres (`api.error`, `api.network`…) sont des IDs de breadcrumb Sentry, pas des traductions (faux positifs).
- Références `l10n.*` mobiles : 91 uniques — seul le manque user_auth (#4856) ; pas d'autre build cassé latent.

## Leçons

1. Vérifier les fermetures d'issues par SHA/branche (cas #4687 — branche citée sans contenu).
2. Toujours brancher depuis `origin/main` propre — jamais depuis une branche de travail (contamination worktree, fermeture #4771).
3. ARB = fichiers générés : promouvoir dans shared (dot lowercase round-trip), vérifier 100 % des références `l10n.*` avant de fermer un fix i18n.
4. Checks requis vs non requis : lighthouse / web E2E / Workers Builds échouent sur toutes les PR (env) — non bloquants ; actionlint peut échouer sur 503 reviewdog transitoire (re-run).
5. Concurrence : converger vite — commenter + fermer le doublon, garder la PR la plus complète ; co-contribuer sur la branche existante plutôt que d'en créer une nouvelle.
