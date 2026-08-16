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
    title: 'Compilation Dart réparée sur les 3 apps mobiles + CI durcie',
    bullets: [
      'Les main.dart de leopardo_employee, leopardo_manager et leopardo_hr déclaraient main() sans async alors que le corps attend SentryFlutter.init — alignés sur Future<void> main() async (bloquait le job Flutter sur main).',
      'CI/CD : durcissement supply-chain (pinning SHA des actions tierces) et déduplication du setup PHP/Flutter via des actions composites réutilisables (~360 lignes en moins).'
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: '34 alertes Dependabot résolues (11 high, 16 moderate, 7 low)',
    bullets: [
      'api (composer) : symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup) ; form-data corrigé (injection CRLF).',
      'Vitrine et admin : npm audit fix (form-data, ws, js-yaml), postcss fixé, vite 6.4.3 (SSRF dev-server, path traversal).',
      'web-offline : Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning) ; audit npm/composer à 0 vulnérabilité résiduelle.'
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: 'Sécurité CI : injection workflow_run corrigée, permissions explicites',
    bullets: [
      'deploy-main.yml : head_branch n\'est plus interpolé dans un bloc run: shell (variable d\'environnement) + vérification head_repository == github.repository.',
      'Permissions GITHUB_TOKEN explicites (contents: read) sur les workflows manquants ; phpstan-modules.neon charge désormais Larastan (36 erreurs undefined method résolues).'
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: 'Module Marketing (Phase 1) : schéma et modèles de base',
    bullets: [
      'Nouveau module DDD api/app/Modules/Marketing/ avec migrations tenant social_accounts et social_posts.',
      'Aucun token OAuth brut stocké (référence chiffrée au profil agrégateur) ; modèles Eloquent scopés par tenant, tests Feature inclus.'
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: 'Rôle manager marketing accepté par l\'API (Module Marketing — Phase 0)',
    bullets: [
      'StoreEmployeeRequest/UpdateEmployeeRequest validaient manager_role sans la valeur marketing — ajoutée à la liste autorisée (POST/PATCH /employees).'
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: 'Drip email onboarding : 3 emails de nurturing automatiques',
    bullets: [
      'SendTrialDripEmailJob envoie J+1, J+3, J+7 après provisionnement trial (retries bornés, statut trial vérifié).',
      'Nouveau modèle OnboardingProgress scopé par company_id + employee_id ; wizard onboarding mobile refactorisé (barre de progression, états requis/erreur).'
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: 'ParseError paie corrigée + pause déduite des heures travaillées',
    bullets: [
      'ParseError PHP dans 7 fichiers CountryRules réparée (CI main débloquée).',
      'Les minutes de pause (break_minutes) sont désormais déduites des heures travaillées (AttendanceLog).'
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: 'PHPStan Modules Architecture réparé (scope Eloquent Absence)',
    bullets: [
      'AbsenceService::request() et LeavePolicyController::balances() accédaient à des scopes Eloquent inconnus — typages corrigés, gate CI verte.'
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: 'Isolation multi-tenant des jobs en file d\'attente',
    bullets: [
      'TenantMiddleware positionne correctement le search_path PostgreSQL et le binding current_company pour les jobs.',
      'Nouvelle interface App\Contracts\Queue\TenantScopedJob : tout job nécessitant un contexte tenant déclare tenantCompanyId().'
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: 'CI : 136 échecs restants résolus (company_id manquant)',
    bullets: [
      'Même classe de bug qu\'en 4.22.3 sur AbsenceType : company_id (NOT NULL) absent des $fillable/casts du modèle canonique — corrigé, 899 tests verts.'
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: 'CI : 160 échecs restants résolus (modèles canoniques)',
    bullets: [
      'Le modèle canonique Absence (via le shim App\Models\Absence) ne déclarait ni company_id (NOT NULL) dans $fillable ni le cast approprié — aligné, 899 tests verts.'
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: 'CI : shims app/Models réparés (class_alias + génération)',
    bullets: [
      '75 fichiers shim app/Models/*.php générés en v4.22.0 : class_alias résolvait un nom de classe incorrect — régénérés, Backend + Coverage repassent au vert.'
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: 'Nettoyage documentation — mojibake et cohérence',
    bullets: [
      'Correction d\'encodages cassés (mojibake) dans les diagrammes UML et autres docs — aucun changement fonctionnel.'
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: 'CI débloqué : migration aux apostrophes échappées',
    bullets: [
      'Apostrophes échappées en style PHP dans un commentaire SQL de la migration employee_attendance_preferences — le CI qui bloquait tous les merges repasse au vert.'
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: 'Nettoyage architectural Phase 2 — modèles, services, FormRequests',
    bullets: [
      '17 modèles orphelins repositionnés dans Core/Tenant/Domain/Models et Core/Auth/Domain/Models ; services et FormRequests réorganisés par module.'
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: 'Nettoyage architectural API — doublons legacy supprimés',
    bullets: [
      '90 controllers doublons supprimés de app/Http/Controllers/Api/V1 (migrés dans app/Modules/*/Interfaces/Api/V1).'
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: 'EdgeSync : EdgeController migré vers le module DDD',
    bullets: [
      'EdgeController et EdgeDownloadController migrés de app/Http/Controllers/Api/V1 vers App\Modules\EdgeSync\Interfaces\Api\V1.',
      'PHPStan modules relevé de niveau 3 → 5 avec suppressions ciblées pour les modules en migration.'
    ],
  },]

/**
 * #4610 — sélecteur de releases consommé par la page /changelog.
 * Résiduel : les bulletins sont FR uniques (localisation du contenu des
 * releases en cours — PR #4675) ; la fonction garde la signature locale
 * pour que la page puisse basculer vers des données localisées sans
 * changement d'appel.
 */
export function getChangelogReleases(_locale: string): PublicChangelogRelease[] {
  return publicChangelogReleases;
}
