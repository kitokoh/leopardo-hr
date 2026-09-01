# Feature Specification: QA Expert — Vague de test complète 2026-08-15

**Feature Branch**: `qa-expert-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + tests locaux réels (backend PHP 8.4 + PostgreSQL 16 + Redis, frontends Next.js/Vue builds + lints, black-box vitrine/admin/API live).

## Contexte

Mission du propriétaire : tester la plateforme « dans tous les sens » — vitrine, web app, admin, mobiles, workflows, API, logiques, onboarding, cohérence — puis consigner chaque manquement selon la méthode Spec Kit (issue + spec/plan/tasks), et implémenter à la fin du test.

Constat : le swarm d'agents a déjà créé ~180 issues ouvertes (vagues QA 2026-08-14/15). La règle anti-doublon (#2400) impose de vérifier issues + branches avant création. Ce spec couvre **uniquement les manquements vérifiés localement et NON couverts** par les issues existantes.

## Findings NON couverts (vérifiés sur main @ 35aef5f4, 2026-08-15)

### F1 [P1][Web] Vitrine déployée : médias Git LFS servis comme pointeurs → images/vidéo cassées en prod

La vitrine `gestionemployer-backend.vercel.app` (déployée via Vercel) sert le **contenu des pointeurs Git LFS** (131 octets de texte) au lieu des fichiers binaires pour 5 assets de `front/web/public/` :
- `public/screenshots/web-dashboard.png` (1280×840 réel, 245 Ko)
- `public/screenshots/mobile-attendance.png`
- `public/videos/product-demo.mp4` (610 Ko réel)
- `public/videos/product-demo.webm` (445 Ko)
- `public/videos/product-demo-poster.jpg` (39 Ko)

Vérifié : `curl` sur la prod renvoie `version https://git-lfs.github.com/spec/v1\noid sha256:...` avec `content-type: image/png|video/mp4` ; l'optimiseur Next.js `/_next/image` répond 400 ; le navigateur montre des images cassées sur la home (section ProductScreenshots) et la section vidéo. `vercel.json` buildCommand = `npm run build` (aucun `git lfs pull`). La migration LFS (#1727/#1758) n'a pas prévu le build Vercel. Le fix déploiement du swarm (#2627/#2632) ne résoudra PAS ce problème (Vercel ne résout pas LFS).

**Cause racine** : 5 fichiers LFS-tracked dans `front/web/public/` + build Vercel sans résolution LFS.

**Fix** : désactiver LFS pour ces fichiers (`git lfs untrack` + `.gitattributes` override `-filter`) et committer les binaires réels (récupérés via `git lfs pull`, ~1 Mo au total).

### F2 [P1][Backend] 8 tests unitaires/feature rouges sur main — drift tests ↔ moteur

Suite complète lancée localement (PHP 8.4, PostgreSQL 16, Redis) : **8 tests échouent de façon déterministe** sur main :

| Suite | Test | Cause racine |
|---|---|---|
| `PayrollCalculatorUnitTest` | `test_get_rules_throws_for_unknown_country` | `use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;` **manquant** (perdu par merge, cf. commentaire « re-cassé par le merge direct #2164 ») → classe résolue en `Tests\Unit\...` inexistante |
| `PayrollCountryRulesTest` | `test_country_rules_expose_expected_social_contributions` | Ligne SN attend `employer=154.0` (CSS famille 3 %), moteur produit `194.0` (7 %) |
| `Payroll\SenegalRulesUnitTest` | `test_ipres_t2_for_cadre` (+ `test_ipres_t1_capped_at_432k` via fixtures) | `SnPayrollFixtures` (#2541) : **9 lignes** avec CSS famille 3 % (ex. 1 M → 89 256) alors que le moteur applique 7 % CIPRES/CLEISS (#2473 : 1 M → 91 776) |
| `AbstractCountryRulesCapTest` | `senegal contribution capped/uncapped` | idem fixtures SnPayrollFixtures |
| `CedeaoRulesUnitTest` | `other uemoa members unaffected` | attend `confidenceLevel() === 'placeholder'` pour TG, moteur renvoie `pilot` (changement #2578) |
| `CemacRulesUnitTest` | `ga is pilot with legal rules` | attend `noticePeriodDays(3.0) === 30.0`, moteur renvoie `22.0` (conversion jours ouvrés #2219/#2280) |
| `Modules\NotificationTest` | `send notification action instantiates` (+ 2 autres) | `NotificationDispatcher::__construct()` exige 1 argument, le test en passe 0 |
| `PayrollTenantIsolationTest` | `cross tenant tax slab is inaccessible` (→ F3) | cf. F3 |

GoldenSnPayrollTest a aussi 3 valeurs périmées (9070.60/51768/89256, lignes 51/111/139) — les autres lignes ont été mises à jour par le swarm, preuve du drift partiel.

**Fix** : réaligner les fixtures/tests sur le moteur (vérifié à la main, valeurs conformes `docs/payroll/SN_COMPLIANCE.md` §4bis et goldens #2473) + restaurer le `use` manquant + aligner TG/GA sur l'état réel + corriger l'instanciation NotificationDispatcher.

### F3 [P2][Backend] PayrollTenantIsolationTest — attend 403, reçoit 404 (test périmé vs Constitution §II)

`test_cross_tenant_tax_slab_is_inaccessible` (l.145-157) : un manager tenant B écrit sur un barème du tenant A → attend `403`, reçoit `404`. Depuis que les endpoints barèmes sont réservés SuperAdmin, la résolution du modèle est scopée par tenant → le slab étranger n'est pas résolu (404 anti-énumération). La **Constitution §II impose `assert 404 cross-tenant`** ; le commentaire du test référence un comportement `assertPlatformAdmin → 403` obsolète.

**Fix** : aligner le test sur 404 (anti-énumération) + commentaire explicite ; conserver un test séparé 403 pour un SuperAdmin sans permission si pertinent.

### F4 [P3][Web] /docs — 4 ancres mortes résiduelles + 3 ids orphelins

`front/web/src/app/(landing)/docs/page.tsx` : après les fixes #2274/#2293/#2208 (clos), il reste :
- 4 ancres référencées sans cible : `#api`, `#webhooks-events`, `#webhooks-intro`, `#webhooks-testing`
- 3 ids définis sans lien entrant : `#mobile-install`, `#security`, `#webhooks-security`

**Fix** : ajouter les ids manquants sur les sections réelles (ou corriger les liens) + garde anti-ancre-morte optionnelle (User Story 2 de la spec #2274 jamais implémentée).

### F5 [P3][Mobile] `apiClient.dio.options` — pattern interdit dans 3 apps

3 fichiers `user_auth_repository.dart` (employee, manager, hr) mutent le client HTTP global :
```dart
apiClient.dio.options.headers['Accept-Language'] = lang;
```
La carte rapide interdit `apiClient.dio.*` sauf `dio.download` (pattern `requestWithRetry` obligatoire). La mutation globale de `dio.options` affecte toutes les requêtes suivantes (fuite de header, non thread-safe).

**Fix** : passer le header par requête via `requestWithRetry(headers: ...)` (vérifier le support) ou une interception locale propre.

## User Stories & Testing

### US1 — Vitrine : les médias s'affichent (P1)
**Independent Test**: `curl -s https://gestionemployer-backend.vercel.app/screenshots/web-dashboard.png | head -c 8` → signature PNG (`\x89PNG`), pas `version https://git-lfs` ; navigateur : `naturalWidth > 0` sur la home.
**Acceptance**: 1. La home affiche les 2 screenshots. 2. La page /videos joue la vidéo avec poster.

### US2 — Suite backend verte (P1)
**Independent Test**: `php artisan test --filter="PayrollCalculatorUnitTest|PayrollCountryRulesTest|SenegalRulesUnitTest|CedeaoRulesUnitTest|CemacRulesUnitTest|AbstractCountryRulesCapTest|Modules\\NotificationTest|PayrollTenantIsolationTest"` → 0 échec.
**Acceptance**: 1. Les valeurs fixtures SN = moteur (7 % CSS famille). 2. TG `pilot`, GA préavis 22 j ouvrés. 3. Le `use` manquant restauré. 4. NotificationDispatcher instancié avec son argument.

### US3 — /docs : zéro ancre morte (P3)
**Independent Test**: script Python hrefs `#*` ⊆ ids sur la page.
**Acceptance**: chaque lien du TOC et des liens rapides résout une section ; ids orphelins liés ou retirés.

### US4 — Mobile : zéro `dio.options` (P3)
**Independent Test**: `rg "apiClient\.dio\." --glob '*.dart'` ne renvoie que des `dio.download`.
**Acceptance**: les 3 repos auth envoient Accept-Language par requête.

## Dependencies & Execution Order

- **Phase 1 (F1)** : indépendant (média + .gitattributes).
- **Phase 2 (F2+F3)** : backend — F2 d'abord (fixtures → tests), F3 indépendant.
- **Phase 3 (F4)** : front/web — indépendant.
- **Phase 4 (F5)** : mobile — indépendant (fichiers distincts).
- PRs : une par issue, `Closes #N` dans le body, CHANGELOG sous `## [Unreleased]`, garde anti-doublon vérifiée avant push (branches + PRs existantes).
