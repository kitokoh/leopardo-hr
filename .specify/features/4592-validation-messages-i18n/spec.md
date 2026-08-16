# Feature Specification: Validation messages localisés ×4 locales (Closes #4592)

**Feature Branch**: `fix/4592-validation-messages-i18n`
**Issue**: #4592 (P2, api/i18n)
**Presets**: `leopardo-multitenancy` (aucune table ajoutée), i18n errors.php

## Contexte

8 messages de validation ajoutés via `$validator->errors()->add(...)` avec des
littéraux FR/EN en dur dans les réponses 422 du funnel RH/paie. Les tenants
`en`/`tr`/`ar` reçoivent du français (ou de l'anglais) brut — même classe que
les batches #3237/#4191/#4292 déjà traités en `lang/errors.php`.

## User Stories

### US1 — Erreurs 422 localisées (P1)

En tant que tenant `en`/`tr`/`ar`, je reçois les messages de validation du
funnel RH/paie dans ma langue (Accept-Language / ?lang), pas en FR brut.

**Acceptance Scenarios**:
1. Given POST /api/v1/employees sans `password` ni `send_invitation`, When
   Accept-Language: ar, Then erreur `password` en arabe.
2. Given POST /api/v1/employees role=manager sans `manager_role`, When
   Accept-Language: tr, Then erreur `manager_role` en turc.
3. Given PATCH /api/v1/employees/{id} avec changement de rôle par un
   manager non-principal, When Accept-Language: en, Then erreur `role` en
   anglais.
4. Given POST /api/v1/payroll-runs avec `country_code` ≠ pays légal du
   tenant, Then erreur `country_code` avec `:country` interpolé ×4 locales.
5. Given POST /api/v1/public-holidays avec `year` ≠ année de `date`, Then
   erreur `year` localisée ×4.

## Requirements

- **FR-001**: 8 clés ajoutées dans `api/lang/{fr,en,tr,ar}/errors.php`
  (`EMPLOYEE_PASSWORD_OR_INVITATION_REQUIRED`,
  `EMPLOYEE_MANAGER_ROLE_REQUIRED`,
  `EMPLOYEE_PRINCIPAL_MANAGER_CREATION_FORBIDDEN`,
  `EMPLOYEE_ROLE_CHANGE_MANAGER_ONLY`,
  `EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN`,
  `PAYROLL_RUN_COUNTRY_MISMATCH` (:country interpolé),
  `PUBLIC_HOLIDAY_YEAR_MISMATCH`, `PUBLIC_HOLIDAY_MONTH_DAY_MISMATCH`).
- **FR-002**: `__('errors.KEY')` dans `StoreEmployeeRequest`,
  `UpdateEmployeeRequest`, `StorePayrollRunRequest`, `PublicHolidayController`.
- **FR-003**: parité de clés des 4 locales (garde `LangCatalogParityTest`).
- **FR-004**: test feature `ValidationMessagesLocalizedTest` — 5 scénarios ×
  4 locales (ou échantillon représentatif), interpolations vérifiées.

## Success Criteria

- `rg 'Le mot de passe|Le type de manager|Seul le super admin|Seul le manager
  principal|Le pays du run|The year must match|The month_day must match' api/app`
  → 0 résultat (hors commentaires).
- `php -l` vert sur les 4 catalogues + 4 fichiers modifiés.
- Réponse 422 en `?lang=ar` = arabe (test).
- PHPStan strict vert (fichiers Core/Modules modifiés).
