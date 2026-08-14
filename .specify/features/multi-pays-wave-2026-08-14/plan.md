# Plan: Vague Multi-Pays Paie Afrique 2026-08 (complétion)

**Input**: spec.md (US1-US4) + Constitution + registre project-state

## Architecture / Décisions techniques

- **Moteur multi-pays** : `CountryRulesInterface` (Domain/Contracts) — résolveur unique `CountryRulesResolver` sans fallback silencieux (#1868) ; classes pays (`SenegalPayrollRules`, `CedeaoPayrollRules`, `CemacPayrollRules`, `DzPayrollRules`, ...) ; `AbstractCountryRules` porte les défauts et `complianceSource()`/`verificationDate()` (#1872/#2104).
- **Contrat de calcul** : `PayrollCalculationPresenter` expose le bloc `compliance` (`level`, `warning`, `warning_key`, `source`, `verification_date`) sur simulation et bulletin (#1869/#1872).
- **Garde placeholder** : 422 sur `POST /cotisation-simulation` et `POST /payroll/simulate` tant que `acknowledge_placeholder=true` absent ; acceptation auditée (`AuditLog`).
- **Multi-tenant** : `search_path` PostgreSQL, migrations dans `database/migrations/tenant/`, tests `PayrollTenantIsolationTest` (404 cross-tenant).
- **Golden tests** : suite `Golden{CC}PayrollTest` par pays + `GoldenGenericEngineTest` (mécanique moteur) ; cas sourcés à la main (#1938).
- **Registre de validation** : `docs/payroll/VALIDATION_EXPERTE.md` + `_TEMPLATE_VALIDATION_EXPERTE.md` ; règles de release (aucun `production` sans fiche signée).
- **Onboarding** : playbook `docs/specifications/PAYS_ONBOARDING_PLAYBOOK.md` + garde `check-country-catalog.sh` dans `Module Structure Validator`.
- **Clients** : la Web App `front/web` (et admin/mobile en suivi) doit afficher le bloc `compliance`.

## Phases

### Phase 1 — Moteur multi-pays (issues #1820-#1829, #1867-#1875, #1930-#2041)
Mergé sur main (vague 2026-08-14) : résolveur, classes pays, golden, garde placeholder, contrat, audit, merge queue, garde migrations, validation docs.

### Phase 2 — Conformité & validation experte
- Registre `VALIDATION_EXPERTE.md` + fiches pays + ticket SN #1912 (ouvert) + ticket reliquats #2124 (ouvert).
- **Restant** : validation humaine (externe) ; fiche SN signée avant `production`.

### Phase 3 — Clients (frontends)
- **Restant** : affichage du bloc `compliance` dans `front/web` (issue #2116), puis admin/mobile.

### Phase 4 — Complétion catalogue
- **Restant** : TG placeholder→pilot (#2121), golden MA/TN (#2122), préavis SN par catégorie (#2123), RICF CI / abattement GA / CNAC DZ (expert, #2124).

## Fichiers touchés (référence)

- `api/app/Modules/Payroll/{Domain,Infrastructure,Interfaces}/**`
- `api/tests/Feature/Payroll/{Golden/**,CountryRulesResolverTest.php,ComplianceConfidenceApiTest.php,PayrollTenantIsolationTest.php}`
- `front/web/src/**`, `shared/i18n/locales/*.json`
- `docs/payroll/*_COMPLIANCE.md`, `docs/payroll/VALIDATION_EXPERTE.md`
- `.specify/memory/project-state.md`

## Contraintes

- PHPStan Strict level 8 = 0 erreur ; coverage Payroll ≥ 80 % ; `Closes #issue` dans chaque PR ; CHANGELOG à jour ; jamais de push direct sur main.
