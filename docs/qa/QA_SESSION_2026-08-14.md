# QA Leopardo HR — Session du 2026-08-14

Mission : tester la plateforme (workflows API, vues, boutons, logique), documenter
les manquements, et les corriger via la technique spec kit.

## Méthode
1. Revue statique ciblée (4 agents) : Payroll, Auth/Sécurité/Tenant, front/web, admin-dashboard
2. Exécution locale : PHP 8.4 + PostgreSQL + Redis, migrations multi-tenant OK
3. Suite de tests complète en cours
4. Vérification manuelle des findings critiques

## Findings confirmés (vérifiés à la main)

### SÉCURITÉ
- [SEC-1 CRITIQUE] `POST /zkteco/sync-attendance/{serialNumber}` + `heartbeat` publiques,
  écriture de pointages sans authentification ni secret (fraude de paie possible)
  → routes/modules/integrations.php:42-44, ZktecoController.php, ZktecoIntegrationService.php
- [SEC-2 MAJEUR] Module Fleet sans RBAC : tout employé peut CRUD véhicules, GPS live,
  maintenance, alertes (tracking.php:11, VehicleController, FleetController, ...)
- [SEC-3 MAJEUR] ScheduleController store/update sans isManager() (incohérent avec index/show/destroy)
- [SEC-4 MAJEUR] Groupe /ai sans middleware tenant → scope BelongsToCompany inerte (ai.php:14)
- [SEC-5 MODÉRÉ] UserAuthService ignore status (compte suspended peut se connecter)
- [SEC-6 MODÉRÉ] /auth/register crée un compte sans company_id → login ultérieur impossible
- [SEC-7 MODÉRÉ] OAuth Google sans paramètre state (CSRF login)
- [SEC-8 MODÉRÉ] Webhooks marketing/bounce fail-open si secret non configuré

### PAYROLL
- [PAY-1 MAJEUR] Préavis : DZ renvoie des jours OUVRÉS (22/44), les 7 autres pays des jours
  CALENDAIRES (30/60/90) → surpaie ~36 % via base × noticeDays/22 (PayrollCalculator:1155-1160,
  AlgeriaPayrollRules:177-193 vs CemacPayrollRules:325-342, CedeaoPayrollRules:334-352, SenegalPayrollRules:149-158)
- [PAY-2 MAJEUR] Simulation ≠ bulletin SN : TRIMF omise (PayrollSimulationController:165)
- [PAY-3 MAJEUR] income_tax_by_slab applique barèmes annuels à base mensuelle (simulateur)
- [PAY-4 MAJEUR] CotisationSimulationController : items détaillés incohérents avec totaux (SN T2, FR CSG 98,25 %)
- [PAY-5 MAJEUR] Run bloqué en `calculating` après échec (PayrollRunController:113)
- [PAY-6 MAJEUR] bulkPay accepte calculated (contourne validation F-11) + régularisation run paid
  non locked échoue (BulkPaymentController:41, PayrollRegularizationService:45-47, PayrollCalculator:327-328)
- [PAY-7 MAJEUR] rules_version/rules_identifier/rules_period jamais persistés sur le run
  (PayrollCalculator:285-295, 425-433 vs 500-502)
- [PAY-8 MAJEUR] Export SEPA : placeholders littéraux <IBAN>PLACEHOLDER_COMPANY_IBAN</IBAN>,
  IBAN 'UNKNOWN', CCP fabriqué (BankExportGenerator:96-97, 102, 114, 131)
- [PAY-9 MAJEUR] Journal CSV : neutralisation anti-injection casse les montants négatifs
  (PayrollJournalGenerator:82)
- [PAY-10 MINEUR] EndOfContractService prorata Mon-Fri quel que soit le pays
- [PAY-11 MINEUR] referenceGross12Months : 2 implémentations divergentes (1/10e)
- [PAY-12 MINEUR] PayrollAccountingExportService sans neutralisation CSV
- [PAY-13 MINEUR] Doubles audit logs déclarations CNAS/CNSS
- [PAY-14 MINEUR] CnasDeclarationGenerator mort + GeneratePayroll appelle méthode inexistante
- [PAY-15 MINEUR] PayrollCycleService::closeCycle jamais appelé
- [PAY-16 MINEUR] PublicHolidayService : fériés chargés seulement l'année du début de période

### FRONT WEB
- [WEB-1 MAJEUR] Bouton télécharger contrat cassé : GET /contracts/{id}/pdf → backend expose
  /contracts/{contract}/generate-pdf (front/web/src/app/(dashboard)/contracts/page.tsx:81)
- [WEB-2 MINEUR] /edge-nodes orpheline (aucune entrée menu)
- [WEB-3 MINEUR] Routes Next mortes : /api/analytics/track, /api/csrf-token, /api/downloads

### ADMIN DASHBOARD
- [ADM-1 MAJEUR] 8 endpoints appelés sans préfixe /admin ou inexistants : fleet/alerts,
  hr-reports, ai/conversations(+messages), marketing oauth PUT, training/sessions,
  training/enrollments, webhooks/{id}/test → UIs vides en silence
- [ADM-2 MAJEUR] SSE /notifications/stream : URL double /api/v1/api/v1 (useNotificationStream.js:25)
- [ADM-3 MINEUR] Boutons inertes : Analytics « Voir détails », CompanyDetail « Accès Super-Console »,
  Growth « Gérer », EditUser « Changer l'avatar »
- [ADM-4 MINEUR] Handlers factices : impersonateUser/resetPassword/sendMessage/viewAuditLog (UserDetailModal),
  CreateUserModal mock submit, EditUserModal resetPassword/sendWelcomeEmail (setTimeout+toast)
- [ADM-5 MINEUR] Module Users 100 % mock (generateMockUsers(150)), AnalyticsView 100 % mock
- [ADM-6 MINEUR] CommandPalette « Véhicules » → /vehicles inexistant (NotFound)
- [ADM-7 MINEUR] LogsView.vue orphelin + clé i18n marketing.oauth.nav_title manquante

## Bilan de la session

### Issues créées (spec-first) et PRs
| Issue | Sujet | PR | Statut implémentation |
|-------|-------|----|----------------------|
| #2216 | Pointage ZKTeco non authentifié (P0) | #2279 | ✅ implémenté (13 tests) |
| #2217 | RBAC Fleet + Planning | #2299 | ✅ implémenté (9 tests) |
| #2219 | Préavis jours calendaires vs ouvrés (7 pays) | — | 📋 spec rédigée, non implémenté (légal, goldens à recalculer) |
| #2220 | Simulateur ≠ bulletin (TRIMF, tranches, cotisations) | #2338 | ✅ implémenté (22 tests, 345 assertions) |
| #2221 | Machine à états du run | #2324 | ✅ implémenté (4 tests) |
| #2223 | Exports SEPA/journal/accounting | #2325 | ✅ implémenté (17 tests) |
| #2224 | Bouton contrat PDF web | #2292 | ✅ implémenté (ESLint+tsc) |
| #2225 | Dashboard admin 8 endpoints + SSE | #2341 | ✅ implémenté (15 tests) |
| #2303 | Tests unitaires payroll périmés (main rouge) | #2307 | ✅ implémenté (59 tests) |

### Autres constats (rapportés, non traités)
- Mobile employé : `/me/vehicles` n'existait pas côté backend (feature position véhicule cassée) → corrigé dans #2217 (nouvel endpoint scopé).
- Auth : compte suspended peut se connecter (UserAuthService ignore status) — pas d'issue créée (à faire).
- /auth/register crée un compte sans company_id → login ultérieur impossible — pas d'issue (à faire).
- Webhooks marketing/bounce fail-open si secret non configuré.
- OAuth Google sans state.
- Tests locaux : RefreshTenantDatabase + CreatesMvpSchema incompatibles dans un même run (pré-existant, lié au setup CI).

### CI
La file GitHub Actions était saturée (issue #2131 connue) pendant toute la session ; les PRs sont en attente de checks. Validation locale : PHPStan strict level 8, Pint, tests ciblés (rappel : la suite complète locale prend ~40 min et est sensible à l'interleaving des traits DB).
