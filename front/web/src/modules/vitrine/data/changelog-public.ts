/**
 * Extrait public du CHANGELOG produit (non automatise).
 * A synchroniser ponctuellement avec CHANGELOG.md a la racine du depot.
 */
export type PublicChangelogRelease = {
  version: string
  isoDate: string
  title: string
  bullets: string[]
}

export const publicChangelogReleases: PublicChangelogRelease[] = [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: 'Première release publique — sécurité, CI et qualité',
    bullets: [
      'Durcissement sécurité complet : SSO SAML/OIDC chiffré au repos, uploads contraints, auth web cookie httpOnly, XSS kiosk éliminé, mobile JWT hors Hive.',
      'Couverture backend 71 % mesurée en CI (gate par module, Payroll ≥ 80 % cible) ; 1 917 tests, 424 endpoints API documentés.',
      'Pagination et contrats de réponse normalisés (Growth, Caméras, DeviceToken, CabinetShare).',
      'CI : 69 actions pinnées SHA, dependency-review bloquant, gate anti-stale SHA, scan secret A-2 réellement exécuté.',
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: 'Correctifs production — cold start, E2E et paie',
    bullets: [
      'Warm-up anti cold-start Render pour l\u2019E2E staging (timeout Playwright 15 s).',
      'Robustesse Vercel : ignoreCommand avec fallback SHA précédent.',
      'Correctifs paie : numéro de facture réellement persisté, workers/scheduler Render démarrés, index FK salary_advances.',
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: 'Bug de compilation Dart dans leopardo_employee, leopardo_man',
    bullets: [
      'Bug de compilation Dart dans leopardo_employee, leopardo_manager, leopardo_hr : void main() { ... await ... } : : les trois main.dart declaraient m...',
      'CI/CD : durcissement supply-chain (P1) + deduplication setup PHP/Flutter (P2) : , suite a AUDIT_CICD_2026-07-19.md :'
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: 'Resolution des 34 alertes Dependabot(11 high, 16 moderate, 7',
    bullets: [
      'Resolution des 34 alertes Dependabot : (11 high, 16 moderate, 7 low) ouvertes depuis l\'activation :',
      '- api (composer) : symfony/yaml 8.0.8 -> 8.1.1 (ReDoS Parser::cleanup(), exponential memory allocation via alias recursion).'
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: 'CI/Securite : alertes CodeQL high sur deploy-main.yml: githu',
    bullets: [
      'CI/Securite : alertes CodeQL high sur deploy-main.yml : : github.event.workflow_run.head_branch etait interpole directement dans un bloc run: shell...',
      'CI : permissions GITHUB_TOKEN manquantes : : ajout d\'un bloc permissions: contents: read explicite sur architecture-check.yml, i18n-enterprise.yml...'
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: 'Module Marketing (Phase 1) : schema et modeles de base: crea',
    bullets: [
      'Module Marketing (Phase 1) : schema et modeles de base : : creation du module api/app/Modules/Marketing/ (Domain/Providers), suivant le pattern DDD...',
      '- Migrations tenant create_social_accounts_table et create_social_posts_table.'
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: 'Role manager marketing invalidable via l\'API malgre le suppo',
    bullets: [
      'Role manager marketing invalidable via l\'API malgre le support modele existant (Module Marketing - Phase 0) : : la migration 2026_06_22_000001_add_...'
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: 'Drip Email onboarding (Lot 2 - P2): SendTrialDripEmailJob di',
    bullets: [
      'Drip Email onboarding (Lot 2 - P2) : : SendTrialDripEmailJob dispatche automatiquement 3 emails de nurturing (J+1, J+3, J+7) dès qu\'une entreprise...',
      'Modèle OnboardingProgress : : nouveau modèle Eloquent AppModulesHRDomainModelsOnboardingProgress avec migration 2026_07_12_115602_create_onboarding...'
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: 'CI cassee sur main : ParseError PHP dans les regles de paie ',
    bullets: [
      'CI cassee sur main : ParseError PHP dans les regles de paie pays : : 7 fichiers sous api/app/Modules/Payroll/Infrastructure/Services/CountryRules/...',
      'Bug metier : la pause (break_minutes) n\'etait jamais deduite des heures travaillees : : AttendanceLog a une colonne schedule_id (FK) mais aucune re...'
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: 'Gate CI "PHPStan — Modules Architecture" casse sur main: Abs',
    bullets: [
      'Gate CI "PHPStan — Modules Architecture" casse sur main : : AbsenceService::request() et LeavePolicyController::balances() (module Absence) accedai...'
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: 'Isolation multi-tenant des Jobs en file d\'attente: TenantMid',
    bullets: [
      'Isolation multi-tenant des Jobs en file d\'attente : : TenantMiddleware positionne correctement le search_path PostgreSQL et le binding current_comp...',
      '- Nouvelle interface AppContractsQueueTenantScopedJob : tout job necessitant un contexte tenant declare tenantCompanyId().'
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: 'Suite du fix CI v4.22.3 : 136 echecs restants sur 899 tests ',
    bullets: [
      'Suite du fix CI v4.22.3 : 136 echecs restants sur 899 tests (Backend) : :',
      '- Meme bug que sur Absence/ExpenseClaim (cf 4.22.3), cette fois sur AppModulesAbsenceDomainModelsAbsenceType : company_id (NOT NULL sur absence_typ...'
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: 'Suite du fix CI v4.22.2 : 160 echecs restants sur 899 tests ',
    bullets: [
      'Suite du fix CI v4.22.2 : 160 echecs restants sur 899 tests (Backend + Backend Coverage) : :',
      '- Modele canonique AppModulesAbsenceDomainModelsAbsence (utilise via le shim AppModelsAbsence) ne declarait ni company_id (NOT NULL en base) dans $...'
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: 'CI casse sur main malgre le fix v4.21.1 (Backend + Backend C',
    bullets: [
      'CI casse sur main malgre le fix v4.21.1 (Backend + Backend Coverage toujours en echec, 633/902 tests) : :',
      '75 fichiers shim app/Models/*.php : (aliases DDD generes en v4.22.0) : class_alias(AppModules...Foo::class, ...) resolvait le nom de classe cible r...'
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: 'Nettoyage documentation projet (lisibilite/coherence, pas de',
    bullets: [
      'Nettoyage documentation projet (lisibilite/coherence, pas de changement fonctionnel) : :',
      '- docs/dossierdeConception/19_diagrammes_uml/{01,02,03,04}_*.md : correction d\'un encodage casse (mojibake, ex. employe9s -> employés, de9tecte9 ->...'
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: 'CI cassé sur main — bloquait tous les merges:',
    bullets: [
      'CI cassé sur main — bloquait tous les merges : :',
      '- Migration 2026_06_29_000202_create_employee_attendance_preferences_table.php : apostrophes échappées en style PHP (\') dans un commentaire SQL Pos...'
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: 'Nettoyage architectural Phase 2 — modèles, services, FormReq',
    bullets: [
      'Nettoyage architectural Phase 2 — modèles, services, FormRequests :',
      '17 modèles orphelins : placés dans Core/Tenant/Domain/Models/, Core/Auth/Domain/Models/,'
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: 'Nettoyage architectural API — suppression des doublons legac',
    bullets: [
      'Nettoyage architectural API — suppression des doublons legacy :',
      '90 controllers : dans app/Http/Controllers/Api/V1/ supprimés (doublons migrés dans app/Modules/*/Interfaces/Api/V1/). Restent : EdgeController, Edg...'
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: 'EdgeSync: Migre EdgeController et EdgeDownloadController de ',
    bullets: [
      'EdgeSync : : Migre EdgeController et EdgeDownloadController de AppHttpControllersApiV1 vers AppModulesEdgeSyncInterfacesApiV1. Routes gérées par Ed...',
      'PHPStan modules : : Niveau relevé de 3 → 5 avec suppressions ciblées pour les modules en cours de migration.'
    ],
  },]
