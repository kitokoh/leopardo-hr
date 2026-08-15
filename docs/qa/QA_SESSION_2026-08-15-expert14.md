# QA Session — Expert 14 (2026-08-15)

> Audit 360° + consolidation + implémentation. Spec-kit : `.specify/features/4035-signupform-flaky-test/` et `.specify/features/3971-web-offline-tests/`.

## Exécution

### Phase 1 — Audit (constats vérifiés)

| Constat | Preuve | Statut |
|---|---|---|
| **SignupForm.test.tsx flaky sur main** — « polls pending → ready » échoue ~1 run/8 en suite complète | `npx jest` ×8 (instrumentation : `startTracking` s'exécute mais l'étape tracking ne monte jamais) | NOUVEAU → issue **#4035** + fix |
| **web-offline zéro test** — workflow « lint + build + tests » sans test, `package.json` sans script test | `.github/workflows/web-offline-ci.yml` + `front/web-offline/package.json` | NOUVEAU → issue **#3971** + fix |
| Vitrine lint/tsc/tests : verts sur main après fix #4035 | `npm run lint`, `npx tsc --noEmit`, `npm test` (351 tests) | ✅ positif |
| Admin lint/build : verts | `npm run lint`, `npm run build` | ✅ positif |
| web-offline lint/tsc/build : verts | idem | ✅ positif |
| Kiosk : 27 tests pytest verts | `pytest tests/` | ✅ positif |
| Gardes dev-hub (hygiene, manifest routes, firebase, canonical) : vertes | exécution locale des 6 gardes | ✅ positif |
| API : plus de fuite `getMessage()` résiduelle (plateforme incluse) | grep statique | ✅ positif (pattern #3725 respecté) |
| OpenAPI drift : connu et documenté | `check-openapi-route-coverage.py` | connue → #2638/#2675/#3233 |
| Factories `@extends` #3830 : déjà mergé par agent parallèle (a2163848) | rebase → « patch already upstream » | fermé (doublon) |
| #3819 PHP 8.4 : déjà traité (composer.json `^8.4.1`, issue fermée) | code + issue | fermé |

### Phase 2 — Consolidation

- **#3811 (races check-then-create, 6 sites)** : implémenté (migration index unique `commissions_payment_id_unique`, catch 23505 ×6, tests de course par hook Eloquent `creating`), PR **#3849 MERGÉE** dans main (0e5a31ab).
- **#3830 (7 factories sans `@extends`)** : implémenté puis **PR fermée comme doublon** — le fix identique a été mergé par un agent parallèle (a2163848). Protocole anti-doublon #2400 appliqué (commentaire de renvoi).
- **#4035 (flaky SignupForm.test.tsx)** : causes racine instrumentées (AnimatePresence `mode="wait"` + RAF réelle capturée par framer-motion au chargement du module → fake timers impuissants ; `userEvent.click` sous fake timers intermittemment avalé). Fix test-only (mock framer-motion local, `fireEvent` synchrone, ordre employees-avant-role) → **12/12 runs verts**. PR **#4039**.
- **#3971 (web-offline zéro test)** : extraction `src/lib/edge-health.ts` + `public/sw-strategies.js` (importScripts), 22 tests Vitest, étape `npm test` dans `web-offline-ci.yml`. PR **#4062**.

### Phase 3 — Implémentation des constats d'audit

Couvert par les fixes #4035 et #3971 ci-dessus (specs spec-kit créées pour les deux), plus :

- **#3951 (double POST trial guided_trial → 2 tenants sandbox)** : dédup des lignes `trial_provisionings` pending (réutilisation du token, catch 23505, index unique partiel `(email) WHERE status='pending'`, migration 2026_08_15_000012). PR **#4074** + tests `TrialProvisioningDedupTest` (3 scénarios). Complémentaire de #3945 (anti-énumération, cas manager).
- **#4044 (renderer HttpException getMessage brut)** : déjà implémenté et fermé par un agent parallèle (branche `fix/3810-exception-renderer` mergée) — vérifié sur main. Pas de doublon.

## Rebase / résolution de conflits

- `fix/4035-signupform-flaky-test` : rebasé sur main (orchestrateur), mergeable.
- `fix/3971-web-offline-tests` : rebasé sur main après le merge #4061 (conflit CHANGELOG absorbé), mergeable.

## Notes pour les prochaines sessions

- CI GitHub Actions toujours en saturation : les PRs restent `blocked` le temps que les checks requis passent — ne pas confondre avec un vrai blocage.
- Le backlog se renouvelle très vite (86+ issues ouvertes, numérotation ~4040) — toujours vérifier branches+PRs avant de claim (protocole #2400).
- `jest.useFakeTimers()` en milieu de suite + framer-motion = piège connu (voir fix #4035) ; pour les composants animés, mock framer-motion local ou pragma jsdom.
