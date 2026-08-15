# Plan: QA Expert 2026-08-15

**Input**: spec.md

## Phase 1 — F1: médias LFS vitrine (P1) — issue à créer
1. `git lfs untrack` des 5 fichiers `front/web/public/` (screenshots ×2, videos ×3) + override `.gitattributes` (`front/web/public/screenshots/*.png -filter`, `front/web/public/videos/* -filter`).
2. `git add --renormalize` + commit des binaires réels (déjà matérialisés par `git lfs pull`).
3. Vérifier que `git lfs ls-files` ne liste plus ces fichiers ; `file` confirme PNG/MP4/WebM.
4. Note : déclencher un re-deploy Vercel pour propager (le swarm gère les deploys #2627/#2632 — le merge suffit si le pipeline re-déploie).

## Phase 2 — F2+F3: tests backend rouges (P1/P2)
1. `SnPayrollFixtures::socialCharges()` — 9 valeurs employer alignées sur le moteur (11426.60 / 16440 / 27840 / 33540 / 54288 / 65376 / 91776 / 168336 / 193536) + commentaires mis à jour (CSS famille 7 % × min(brut, 63 000)).
2. `GoldenSnPayrollTest` — lignes 51/111/139 réalignées (11426.60 / 54288 / 91776).
3. `PayrollCountryRulesTest` — ligne SN : employer 154.0 → 194.0.
4. `PayrollCalculatorUnitTest` — restaurer `use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;`.
5. `CedeaoRulesUnitTest::test_other_uemoa_members_unaffected` — TG : `'placeholder'` → `'pilot'` (aligné sur #2578).
6. `CemacRulesUnitTest::test_ga_is_pilot_with_legal_rules` — `noticePeriodDays(3.0)` : 30.0 → 22.0 (jours ouvrés).
7. `NotificationTest` — instancier `NotificationDispatcher` avec son argument (vérifier le constructeur : quel contrat ?).
8. `PayrollTenantIsolationTest::test_cross_tenant_tax_slab_is_inaccessible` — `assertForbidden()` → `assertNotFound()` (×2) + commentaire Constitution §II.
9. Vérifier : les 8 suites → 0 échec. PHPStan strict + Pint sur les fichiers touchés.

## Phase 3 — F4: ancres /docs (P3)
1. Ajouter `id="api"`, `id="webhooks-events"`, `id="webhooks-intro"`, `id="webhooks-testing"` sur les sections réelles du TOC/liens rapides (ou corriger les hrefs).
2. Lier les ids orphelins (`mobile-install`, `security`, `webhooks-security`) depuis le TOC/liens rapides (ou les retirer s'ils n'ont pas de contenu).
3. Script de garde : hrefs `#*` ⊆ ids (réutiliser le check Python de la session).

## Phase 4 — F5: mobile dio.options (P3)
1. Vérifier la signature de `requestWithRetry` (headers par requête ?).
2. Remplacer `apiClient.dio.options.headers['Accept-Language'] = lang` par un header par requête dans les 3 `user_auth_repository.dart`.
3. Garde : `rg "apiClient\.dio\."` → uniquement `dio.download`.

## Vérifications finales
- `php artisan test` (suites ciblées) vert
- `npm run lint` + `npm run build` (web + admin) verts (pas touchés mais sanity)
- `git lfs ls-files` : 0 fichier dans front/web/public
- CHANGELOG.md : entrées `### Fixed` par PR
- PRs avec `Closes #N` dans le body
