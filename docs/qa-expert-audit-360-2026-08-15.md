# Session QA — Expert audit 360° + implémentation (2026-08-15, après-midi/soir)

**Agent**: Audit expert — `kitokoh` (session multi-agents concurrente)
**Périmètre**: Audit 360° complet (web vitrine, admin-dashboard, API Laravel,
mobile Flutter ×3, edge/web-offline, CI) + nettoyage + implémentation.

## Phase 1 — Audit 360° & gap analysis

- **52 issues spec-kit créées** (#3918 → #3972), toutes vérifiées sur le code
  (pas de supposition), avec `## Constat` / `## Cause racine` / `## Fix attendu` /
  `## Critères d'acceptation`.
- Couverture : web vitrine (11), admin-dashboard (11), API (11), mobile (7),
  edge/CI (12).
- Points P1 majeurs découverts :
  - **#3941** : `POST /user/google-signin` émettait un token Sanctum sans
    AUCUNE vérification Google (identité forgée → prise de contrôle de compte).
  - **#3952** : les 3 apps Flutter ne compilaient pas (argument nommé dupliqué
    `maxRetriesOverride` — mauvaise fusion, aucune garde CI).
  - **#3918** : JsonLd fallback sur domaine étranger banni.
  - **#3919** : triple schéma de prix contradictoire vitrine/checkout.
  - **#3929** : admin — 401 en session figeait la SPA.

## Phase 2 — Cleanup & dette

- Merge orchestrator : boucle de merge automatisée (merge des PRs `clean`,
  sync des branches `behind`) — a contribué au drain de la file de PRs.
- 7 branches « claim marker » vides identifiées (fix/2789, 3237, 3249, 3278,
  3334, 3600, 3834) — aucun code, issues restées ouvertes.
- **Fix critique CI** : `SSOControllerTest.php` — méthode hors classe
  (parse error PHP) → PHPUnit fatal sur main (#4070).

## Phase 3 — Implémentations (10 PRs mergées/ouvertes)

| # | Issue | Fix | PR | Statut |
|---|-------|-----|----|--------|
| 3941 | Google Sign-In sans vérification | `GoogleIdentityVerifier` (JWKS Google, iss/aud/exp/email_verified) + mobile ×3 apps envoient `idToken` | #4043 | **mergée** |
| 3929 | Admin 401 fige la SPA | `clearSession()` store Pinia + intercepteur | #4050 | **mergée** |
| 3942 | TenantMiddleware fail-open ordinary | gardes suspended/archived pour comptes sans entreprise | #4065 | **mergée** |
| 3943 | Estimations `viewAny` sans scope | `authorize('view')` + test superviseur hors équipe | #4066 | **mergée** |
| 3944 | Reset password TOCTOU | consommation atomique (UPDATE conditionnel) | #4067 | **mergée** |
| 3945 | Trial signup énumération | réponse uniforme, détection au verify (OTP) | #4068 | **mergée** |
| 4070 | SSOControllerTest parse error | méthode déplacée dans la classe | #4070 | **mergée** |
| 4079 | Provisioning fillable #3677 (role/status/company_id abandonnés) | `set explicite + save` ×3 chemins | #4080 | **mergée** |
| 4088 | CI mobile rouge (dio 5, Locale.language, tests périmés) | fixes compile + tests + warnings | #4090 | **mergée** |
| 4091 | `/auth/me` 403 pour comptes ordinaires | `stageFor()` sans requête scopée + fixture nullable | #4093 | ouverte |
| 4092 | DER SPKI invalide (OpenSSL 3) → SSO/Google KO | `positiveInteger()` + `derLengthLength()` | #4098 | ouverte |

Validations locales : PostgreSQL 14 + PHP 8.4 + OpenSSL 3 (tests exécutés),
Flutter 3.47 (`flutter analyze` 0 issue ×4 packages, `flutter test` vert).

## Leçons

1. Le durcissement fillable #3677 a cassé silencieusement 3 chemins de
   provisioning (role/status abandonnés) — **une régression P1 détectée en
   exécutant les tests localement**, pas par la CI (file saturée).
2. La CI mobile était rouge (runs annulés) — les erreurs de compilation
   passaient inaperçues ; `flutter analyze` local sur les 4 packages est
   indispensable.
3. La fixture de test mvp (SQL) a dérivé des migrations réelles
   (`employees.company_id`, `user_employee_links`) → tests #3727/#3942
   inexécutables ; alignée.
4. Concurrence : les autres agents ont implémenté ~30 de mes issues d'audit
   en parallèle — **41/52 issues d'audit closes en fin de session**.
