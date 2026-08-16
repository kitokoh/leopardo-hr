/**
 * Extrait public du CHANGELOG produit (non automatise).
 * A synchroniser ponctuellement avec CHANGELOG.md a la racine du depot.
 *
 * #4610 : contenu localise ×4 locales — avant, les 4 locales recevaient les
 * titres/bullets FR (le chrome de la page et le sitemap etaient deja i18n).
 * `publicChangelogReleases` reste exporte (alias FR) pour compatibilite.
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
    title: "Première release publique — sécurité, CI et qualité",
    bullets: [
        "Durcissement sécurité complet : SSO SAML/OIDC chiffré au repos, uploads contraints, auth web cookie httpOnly, XSS kiosk éliminé, mobile JWT hors Hive.",
        "Couverture backend 71 % mesurée en CI (gate par module, Payroll ≥ 80 % cible) ; 1 917 tests, 424 endpoints API documentés.",
        "Pagination et contrats de réponse normalisés (Growth, Caméras, DeviceToken, CabinetShare).",
        "CI : 69 actions pinnées SHA, dependency-review bloquant, gate anti-stale SHA, scan secret A-2 réellement exécuté."
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: "Correctifs production — cold start, E2E et paie",
    bullets: [
        "Warm-up anti cold-start Render pour l’E2E staging (timeout Playwright 15 s).",
        "Robustesse Vercel : ignoreCommand avec fallback SHA précédent.",
        "Correctifs paie : numéro de facture réellement persisté, workers/scheduler Render démarrés, index FK salary_advances."
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "Compilation Dart réparée sur les 3 apps mobiles + CI durcie",
    bullets: [
        "Les main.dart de leopardo_employee, leopardo_manager et leopardo_hr déclaraient main() sans async alors que le corps attend SentryFlutter.init — alignés sur Future<void> main() async (bloquait le job Flutter sur main).",
        "CI/CD : durcissement supply-chain (pinning SHA des actions tierces) et déduplication du setup PHP/Flutter via des actions composites réutilisables (~360 lignes en moins)."
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "34 alertes Dependabot résolues (11 high, 16 moderate, 7 low)",
    bullets: [
        "api (composer) : symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup) ; form-data corrigé (injection CRLF).",
        "Vitrine et admin : npm audit fix (form-data, ws, js-yaml), postcss fixé, vite 6.4.3 (SSRF dev-server, path traversal).",
        "web-offline : Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning) ; audit npm/composer à 0 vulnérabilité résiduelle."
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "Sécurité CI : injection workflow_run corrigée, permissions explicites",
    bullets: [
        "deploy-main.yml : head_branch n'est plus interpolé dans un bloc run: shell (variable d'environnement) + vérification head_repository == github.repository.",
        "Permissions GITHUB_TOKEN explicites (contents: read) sur les workflows manquants ; phpstan-modules.neon charge désormais Larastan (36 erreurs undefined method résolues)."
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "Module Marketing (Phase 1) : schéma et modèles de base",
    bullets: [
        "Nouveau module DDD api/app/Modules/Marketing/ avec migrations tenant social_accounts et social_posts.",
        "Aucun token OAuth brut stocké (référence chiffrée au profil agrégateur) ; modèles Eloquent scopés par tenant, tests Feature inclus."
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "Rôle manager marketing accepté par l'API (Module Marketing — Phase 0)",
    bullets: [
        "StoreEmployeeRequest/UpdateEmployeeRequest validaient manager_role sans la valeur marketing — ajoutée à la liste autorisée (POST/PATCH /employees)."
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "Drip email onboarding : 3 emails de nurturing automatiques",
    bullets: [
        "SendTrialDripEmailJob envoie J+1, J+3, J+7 après provisionnement trial (retries bornés, statut trial vérifié).",
        "Nouveau modèle OnboardingProgress scopé par company_id + employee_id ; wizard onboarding mobile refactorisé (barre de progression, états requis/erreur)."
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "ParseError paie corrigée + pause déduite des heures travaillées",
    bullets: [
        "ParseError PHP dans 7 fichiers CountryRules réparée (CI main débloquée).",
        "Les minutes de pause (break_minutes) sont désormais déduites des heures travaillées (AttendanceLog)."
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "PHPStan Modules Architecture réparé (scope Eloquent Absence)",
    bullets: [
        "AbsenceService::request() et LeavePolicyController::balances() accédaient à des scopes Eloquent inconnus — typages corrigés, gate CI verte."
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "Isolation multi-tenant des jobs en file d'attente",
    bullets: [
        "TenantMiddleware positionne correctement le search_path PostgreSQL et le binding current_company pour les jobs.",
        "Nouvelle interface App\\Contracts\\Queue\\TenantScopedJob : tout job nécessitant un contexte tenant déclare tenantCompanyId()."
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "CI : 136 échecs restants résolus (company_id manquant)",
    bullets: [
        "Même classe de bug qu'en 4.22.3 sur AbsenceType : company_id (NOT NULL) absent des $fillable/casts du modèle canonique — corrigé, 899 tests verts."
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "CI : 160 échecs restants résolus (modèles canoniques)",
    bullets: [
        "Le modèle canonique Absence (via le shim App\\Models\\Absence) ne déclarait ni company_id (NOT NULL) dans $fillable ni le cast approprié — aligné, 899 tests verts."
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "CI : shims app/Models réparés (class_alias + génération)",
    bullets: [
        "75 fichiers shim app/Models/*.php générés en v4.22.0 : class_alias résolvait un nom de classe incorrect — régénérés, Backend + Coverage repassent au vert."
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "Nettoyage documentation — mojibake et cohérence",
    bullets: [
        "Correction d'encodages cassés (mojibake) dans les diagrammes UML et autres docs — aucun changement fonctionnel."
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "CI débloqué : migration aux apostrophes échappées",
    bullets: [
        "Apostrophes échappées en style PHP dans un commentaire SQL de la migration employee_attendance_preferences — le CI qui bloquait tous les merges repasse au vert."
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural Phase 2 — modèles, services, FormRequests",
    bullets: [
        "17 modèles orphelins repositionnés dans Core/Tenant/Domain/Models et Core/Auth/Domain/Models ; services et FormRequests réorganisés par module."
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural API — doublons legacy supprimés",
    bullets: [
        "90 controllers doublons supprimés de app/Http/Controllers/Api/V1 (migrés dans app/Modules/*/Interfaces/Api/V1)."
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync : EdgeController migré vers le module DDD",
    bullets: [
        "EdgeController et EdgeDownloadController migrés de app/Http/Controllers/Api/V1 vers App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.",
        "PHPStan modules relevé de niveau 3 → 5 avec suppressions ciblées pour les modules en migration."
    ],
  }
]

const changelogByLocale: Record<'fr' | 'en' | 'tr' | 'ar', PublicChangelogRelease[]> = {
  fr: [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: "Première release publique — sécurité, CI et qualité",
    bullets: [
        "Durcissement sécurité complet : SSO SAML/OIDC chiffré au repos, uploads contraints, auth web cookie httpOnly, XSS kiosk éliminé, mobile JWT hors Hive.",
        "Couverture backend 71 % mesurée en CI (gate par module, Payroll ≥ 80 % cible) ; 1 917 tests, 424 endpoints API documentés.",
        "Pagination et contrats de réponse normalisés (Growth, Caméras, DeviceToken, CabinetShare).",
        "CI : 69 actions pinnées SHA, dependency-review bloquant, gate anti-stale SHA, scan secret A-2 réellement exécuté."
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: "Correctifs production — cold start, E2E et paie",
    bullets: [
        "Warm-up anti cold-start Render pour l’E2E staging (timeout Playwright 15 s).",
        "Robustesse Vercel : ignoreCommand avec fallback SHA précédent.",
        "Correctifs paie : numéro de facture réellement persisté, workers/scheduler Render démarrés, index FK salary_advances."
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "Compilation Dart réparée sur les 3 apps mobiles + CI durcie",
    bullets: [
        "Les main.dart de leopardo_employee, leopardo_manager et leopardo_hr déclaraient main() sans async alors que le corps attend SentryFlutter.init — alignés sur Future<void> main() async (bloquait le job Flutter sur main).",
        "CI/CD : durcissement supply-chain (pinning SHA des actions tierces) et déduplication du setup PHP/Flutter via des actions composites réutilisables (~360 lignes en moins)."
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "34 alertes Dependabot résolues (11 high, 16 moderate, 7 low)",
    bullets: [
        "api (composer) : symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup) ; form-data corrigé (injection CRLF).",
        "Vitrine et admin : npm audit fix (form-data, ws, js-yaml), postcss fixé, vite 6.4.3 (SSRF dev-server, path traversal).",
        "web-offline : Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning) ; audit npm/composer à 0 vulnérabilité résiduelle."
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "Sécurité CI : injection workflow_run corrigée, permissions explicites",
    bullets: [
        "deploy-main.yml : head_branch n'est plus interpolé dans un bloc run: shell (variable d'environnement) + vérification head_repository == github.repository.",
        "Permissions GITHUB_TOKEN explicites (contents: read) sur les workflows manquants ; phpstan-modules.neon charge désormais Larastan (36 erreurs undefined method résolues)."
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "Module Marketing (Phase 1) : schéma et modèles de base",
    bullets: [
        "Nouveau module DDD api/app/Modules/Marketing/ avec migrations tenant social_accounts et social_posts.",
        "Aucun token OAuth brut stocké (référence chiffrée au profil agrégateur) ; modèles Eloquent scopés par tenant, tests Feature inclus."
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "Rôle manager marketing accepté par l'API (Module Marketing — Phase 0)",
    bullets: [
        "StoreEmployeeRequest/UpdateEmployeeRequest validaient manager_role sans la valeur marketing — ajoutée à la liste autorisée (POST/PATCH /employees)."
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "Drip email onboarding : 3 emails de nurturing automatiques",
    bullets: [
        "SendTrialDripEmailJob envoie J+1, J+3, J+7 après provisionnement trial (retries bornés, statut trial vérifié).",
        "Nouveau modèle OnboardingProgress scopé par company_id + employee_id ; wizard onboarding mobile refactorisé (barre de progression, états requis/erreur)."
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "ParseError paie corrigée + pause déduite des heures travaillées",
    bullets: [
        "ParseError PHP dans 7 fichiers CountryRules réparée (CI main débloquée).",
        "Les minutes de pause (break_minutes) sont désormais déduites des heures travaillées (AttendanceLog)."
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "PHPStan Modules Architecture réparé (scope Eloquent Absence)",
    bullets: [
        "AbsenceService::request() et LeavePolicyController::balances() accédaient à des scopes Eloquent inconnus — typages corrigés, gate CI verte."
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "Isolation multi-tenant des jobs en file d'attente",
    bullets: [
        "TenantMiddleware positionne correctement le search_path PostgreSQL et le binding current_company pour les jobs.",
        "Nouvelle interface App\\Contracts\\Queue\\TenantScopedJob : tout job nécessitant un contexte tenant déclare tenantCompanyId()."
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "CI : 136 échecs restants résolus (company_id manquant)",
    bullets: [
        "Même classe de bug qu'en 4.22.3 sur AbsenceType : company_id (NOT NULL) absent des $fillable/casts du modèle canonique — corrigé, 899 tests verts."
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "CI : 160 échecs restants résolus (modèles canoniques)",
    bullets: [
        "Le modèle canonique Absence (via le shim App\\Models\\Absence) ne déclarait ni company_id (NOT NULL) dans $fillable ni le cast approprié — aligné, 899 tests verts."
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "CI : shims app/Models réparés (class_alias + génération)",
    bullets: [
        "75 fichiers shim app/Models/*.php générés en v4.22.0 : class_alias résolvait un nom de classe incorrect — régénérés, Backend + Coverage repassent au vert."
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "Nettoyage documentation — mojibake et cohérence",
    bullets: [
        "Correction d'encodages cassés (mojibake) dans les diagrammes UML et autres docs — aucun changement fonctionnel."
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "CI débloqué : migration aux apostrophes échappées",
    bullets: [
        "Apostrophes échappées en style PHP dans un commentaire SQL de la migration employee_attendance_preferences — le CI qui bloquait tous les merges repasse au vert."
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural Phase 2 — modèles, services, FormRequests",
    bullets: [
        "17 modèles orphelins repositionnés dans Core/Tenant/Domain/Models et Core/Auth/Domain/Models ; services et FormRequests réorganisés par module."
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "Nettoyage architectural API — doublons legacy supprimés",
    bullets: [
        "90 controllers doublons supprimés de app/Http/Controllers/Api/V1 (migrés dans app/Modules/*/Interfaces/Api/V1)."
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync : EdgeController migré vers le module DDD",
    bullets: [
        "EdgeController et EdgeDownloadController migrés de app/Http/Controllers/Api/V1 vers App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.",
        "PHPStan modules relevé de niveau 3 → 5 avec suppressions ciblées pour les modules en migration."
    ],
  }
  ],
  en: [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: "First public release — security, CI and quality",
    bullets: [
        "Complete security hardening: SAML/OIDC SSO encrypted at rest, constrained uploads, httpOnly web auth cookie, kiosk XSS eliminated, mobile JWT kept out of Hive.",
        "Backend coverage 71% measured in CI (per-module gate, Payroll ≥ 80% target); 1,917 tests, 424 documented API endpoints.",
        "Normalized pagination and response contracts (Growth, Cameras, DeviceToken, CabinetShare).",
        "CI: 69 SHA-pinned actions, blocking dependency-review, anti-stale SHA gate, A-2 secret scan actually executed."
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: "Production fixes — cold start, E2E and payroll",
    bullets: [
        "Render anti-cold-start warm-up for E2E staging (Playwright 15s timeout).",
        "Vercel robustness: ignoreCommand with previous SHA fallback.",
        "Payroll fixes: invoice number actually persisted, Render workers/scheduler started, salary_advances FK index."
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "Dart compilation fixed on the 3 mobile apps + hardened CI",
    bullets: [
        "The main.dart of leopardo_employee, leopardo_manager and leopardo_hr declared main() without async while the body awaited SentryFlutter.init — aligned on Future<void> main() async (was blocking the Flutter job on main).",
        "CI/CD: supply-chain hardening (SHA pinning of third-party actions) and deduplication of PHP/Flutter setup via reusable composite actions (~360 fewer lines)."
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "34 Dependabot alerts resolved (11 high, 16 moderate, 7 low)",
    bullets: [
        "api (composer): symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup); form-data fixed (CRLF injection).",
        "Vitrine and admin: npm audit fix (form-data, ws, js-yaml), postcss fixed, vite 6.4.3 (dev-server SSRF, path traversal).",
        "web-offline: Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning); npm/composer audit at 0 residual vulnerability."
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "CI security: workflow_run injection fixed, explicit permissions",
    bullets: [
        "deploy-main.yml: head_branch is no longer interpolated into a run: shell block (environment variable) + head_repository == github.repository check.",
        "Explicit GITHUB_TOKEN permissions (contents: read) on the missing workflows; phpstan-modules.neon now loads Larastan (36 undefined method errors resolved)."
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "Marketing Module (Phase 1): base schema and models",
    bullets: [
        "New DDD module api/app/Modules/Marketing/ with tenant migrations social_accounts and social_posts.",
        "No raw OAuth token stored (encrypted reference to the aggregator profile); Eloquent models tenant-scoped, Feature tests included."
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "Marketing manager role accepted by the API (Marketing Module — Phase 0)",
    bullets: [
        "StoreEmployeeRequest/UpdateEmployeeRequest validated manager_role without the marketing value — added to the allowed list (POST/PATCH /employees)."
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "Onboarding drip email: 3 automatic nurturing emails",
    bullets: [
        "SendTrialDripEmailJob sends J+1, J+3, J+7 after trial provisioning (bounded retries, verified trial status).",
        "New OnboardingProgress model scoped by company_id + employee_id; mobile onboarding wizard refactored (progress bar, required/error states)."
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "Payroll ParseError fixed + break time deducted from worked hours",
    bullets: [
        "PHP ParseError in 7 CountryRules files fixed (main CI unblocked).",
        "Break minutes (break_minutes) are now deducted from worked hours (AttendanceLog)."
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "PHPStan Modules Architecture fixed (Absence Eloquent scope)",
    bullets: [
        "AbsenceService::request() and LeavePolicyController::balances() accessed unknown Eloquent scopes — typings fixed, CI gate green."
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "Multi-tenant isolation of queued jobs",
    bullets: [
        "TenantMiddleware now correctly sets the PostgreSQL search_path and the current_company binding for jobs.",
        "New App\\Contracts\\Queue\\TenantScopedJob interface: any job requiring a tenant context declares tenantCompanyId()."
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "CI: 136 remaining failures resolved (missing company_id)",
    bullets: [
        "Same bug class as 4.22.3 on AbsenceType: company_id (NOT NULL) missing from the canonical model's $fillable/casts — fixed, 899 green tests."
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "CI: 160 remaining failures resolved (canonical models)",
    bullets: [
        "The canonical Absence model (via the App\\Models\\Absence shim) declared neither company_id (NOT NULL) in $fillable nor the proper cast — aligned, 899 green tests."
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "CI: app/Models shims fixed (class_alias + generation)",
    bullets: [
        "75 shim files app/Models/*.php generated in v4.22.0: class_alias resolved an incorrect class name — regenerated, Backend + Coverage green again."
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "Documentation cleanup — mojibake and consistency",
    bullets: [
        "Fixed broken encodings (mojibake) in UML diagrams and other docs — no functional change."
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "CI unblocked: escaped apostrophes migration",
    bullets: [
        "PHP-style escaped apostrophes in an SQL comment of the employee_attendance_preferences migration — the CI that was blocking all merges is green again."
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "Architectural cleanup Phase 2 — models, services, FormRequests",
    bullets: [
        "17 orphan models repositioned into Core/Tenant/Domain/Models and Core/Auth/Domain/Models; services and FormRequests reorganized by module."
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "API architectural cleanup — legacy duplicates removed",
    bullets: [
        "90 duplicate controllers removed from app/Http/Controllers/Api/V1 (migrated into app/Modules/*/Interfaces/Api/V1)."
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync: EdgeController migrated to the DDD module",
    bullets: [
        "EdgeController and EdgeDownloadController migrated from app/Http/Controllers/Api/V1 to App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.",
        "PHPStan modules raised from level 3 to 5 with targeted suppressions for migrating modules."
    ],
  }
  ],
  tr: [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: "İlk halka açık sürüm — güvenlik, CI ve kalite",
    bullets: [
        "Kapsamlı güvenlik sıkılaştırması: SAML/OIDC SSO şifreli saklama, kısıtlı yüklemeler, httpOnly web kimlik çerezi, kiosk XSS ortadan kaldırıldı, mobil JWT Hive dışında.",
        "Backend kapsamı CI'da %71 ölçülüyor (modül başına gate, Payroll ≥ %80 hedef); 1.917 test, 424 belgelenmiş API uç noktası.",
        "Sayfalama ve yanıt sözleşmeleri standartlaştırıldı (Growth, Cameras, DeviceToken, CabinetShare).",
        "CI: SHA ile sabitlenmiş 69 aksiyon, engelleyici dependency-review, bayat SHA karşıtı gate, A-2 gizli taraması gerçekten çalışıyor."
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: "Üretim düzeltmeleri — cold start, E2E ve maaş",
    bullets: [
        "Render cold start karşıtı ısıtma (Playwright 15 sn zaman aşımı).",
        "Vercel sağlamlığı: önceki SHA geri dönüşlü ignoreCommand.",
        "Maaş düzeltmeleri: fatura numarası gerçekten kalıcılaşıyor, Render workers/scheduler başlatıldı, salary_advances FK indeksi."
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "3 mobil uygulamada Dart derlemesi düzeltildi + CI sıkılaştırıldı",
    bullets: [
        "leopardo_employee, leopardo_manager ve leopardo_hr main.dart dosyaları, gövde SentryFlutter.init beklerken main()'i async'siz bildiriyordu — Future<void> main() async ile hizalandı (main üzerindeki Flutter işini bloke ediyordu).",
        "CI/CD: tedarik zinciri sıkılaştırması (üçüncü taraf aksiyonların SHA sabitlemesi) ve yeniden kullanılabilir kompozit aksiyonlarla PHP/Flutter kurulumunun tekilleştirilmesi (~360 satır daha az)."
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "34 Dependabot uyarısı çözüldü (11 yüksek, 16 orta, 7 düşük)",
    bullets: [
        "api (composer): symfony/yaml 8.0.8 → 8.1.1 (ReDoS Parser::cleanup); form-data düzeltildi (CRLF enjeksiyonu).",
        "Vitrine ve admin: npm audit fix (form-data, ws, js-yaml), postcss düzeltildi, vite 6.4.3 (dev-server SSRF, path traversal).",
        "web-offline: Next.js 16.2.10 + ESLint 9 (SSRF, XSS, cache poisoning); npm/composer denetiminde sıfır kalan açık."
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "CI güvenliği: workflow_run enjeksiyonu düzeltildi, açık izinler",
    bullets: [
        "deploy-main.yml: head_branch artık run: shell bloğuna enterpole edilmiyor (ortam değişkeni) + head_repository == github.repository doğrulaması.",
        "Eksik iş akışlarında açık GITHUB_TOKEN izinleri (contents: read); phpstan-modules.neon artık Larastan yüklüyor (36 tanımsız metod hatası çözüldü)."
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "Pazarlama Modülü (Faz 1): temel şema ve modeller",
    bullets: [
        "tenant migration'ları social_accounts ve social_posts içeren yeni DDD modülü api/app/Modules/Marketing/.",
        "Ham OAuth token saklanmıyor (toplayıcı profile şifreli referans); Eloquent modelleri tenant kapsamlı, Feature testleri dahil."
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "Pazarlama yönetici rolü API tarafından kabul edildi (Pazarlama Modülü — Faz 0)",
    bullets: [
        "StoreEmployeeRequest/UpdateEmployeeRequest manager_role'u marketing değeri olmadan doğruluyordu — izin verilen listeye eklendi (POST/PATCH /employees)."
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "Onboarding drip e-postası: 3 otomatik besleme e-postası",
    bullets: [
        "SendTrialDripEmailJob, trial sağlamasından sonra J+1, J+3, J+7 gönderir (sınırlı yeniden denemeler, doğrulanmış trial durumu).",
        "company_id + employee_id ile kapsamlı yeni OnboardingProgress modeli; mobil onboarding sihirbazı yeniden düzenlendi (ilerleme çubuğu, zorunlu/hata durumları)."
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "Maaş ParseError düzeltildi + mola, çalışılan saatlerden düşülüyor",
    bullets: [
        "7 CountryRules dosyasındaki PHP ParseError düzeltildi (main CI açıldı).",
        "Mola dakikaları (break_minutes) artık çalışılan saatlerden düşülüyor (AttendanceLog)."
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "PHPStan Modules Architecture düzeltildi (Absence Eloquent scope)",
    bullets: [
        "AbsenceService::request() ve LeavePolicyController::balances() bilinmeyen Eloquent scope'larına erişiyordu — tipler düzeltildi, CI gate yeşil."
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "Kuyruktaki işlerin çok kiracılı izolasyonu",
    bullets: [
        "TenantMiddleware artık işler için PostgreSQL search_path ve current_company bağlamını doğru ayarlıyor.",
        "Yeni App\\Contracts\\Queue\\TenantScopedJob arayüzü: kiracı bağlamı gerektiren her iş tenantCompanyId() bildirir."
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "CI: kalan 136 hata çözüldü (eksik company_id)",
    bullets: [
        "4.22.3'teki AbsenceType ile aynı hata sınıfı: company_id (NOT NULL) kanonik modelin $fillable/casts listesinde yok — düzeltildi, 899 yeşil test."
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "CI: kalan 160 hata çözüldü (kanonik modeller)",
    bullets: [
        "Kanonik Absence modeli (App\\Models\\Absence shim'i üzerinden) $fillable'da company_id (NOT NULL) bildirmiyor ve uygun cast'e sahip değildi — hizalandı, 899 yeşil test."
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "CI: app/Models shim'leri düzeltildi (class_alias + üretim)",
    bullets: [
        "v4.22.0'da üretilen 75 shim dosyası app/Models/*.php: class_alias hatalı bir sınıf adı çözüyordu — yeniden üretildi, Backend + Coverage tekrar yeşil."
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "Dokümantasyon temizliği — mojibake ve tutarlılık",
    bullets: [
        "UML diyagramları ve diğer dokümanlardaki bozuk kodlamalar (mojibake) düzeltildi — işlevsel değişiklik yok."
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "CI açıldı: kaçışlı kesme işareti migration'ı",
    bullets: [
        "employee_attendance_preferences migration'ının SQL yorumundaki PHP tarzı kaçışlı kesme işaretleri — tüm merge'leri bloke eden CI tekrar yeşil."
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "Mimari temizlik Faz 2 — modeller, servisler, FormRequest'ler",
    bullets: [
        "17 sahipsiz model Core/Tenant/Domain/Models ve Core/Auth/Domain/Models içine taşındı; servisler ve FormRequest'ler modüle göre yeniden düzenlendi."
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "API mimari temizliği — eski kopyalar kaldırıldı",
    bullets: [
        "app/Http/Controllers/Api/V1'den 90 kopya controller silindi (app/Modules/*/Interfaces/Api/V1 içine taşındı)."
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync: EdgeController DDD modülüne taşındı",
    bullets: [
        "EdgeController ve EdgeDownloadController, app/Http/Controllers/Api/V1'den App\\Modules\\EdgeSync\\Interfaces\\Api\\V1'e taşındı.",
        "PHPStan modülleri, geçiş halindeki modüller için hedefli istisnalarla 3. seviyeden 5. seviyeye yükseltildi."
    ],
  }
  ],
  ar: [
  {
    version: '4.24.0',
    isoDate: '2026-08-11',
    title: "الإصدار العام الأول — الأمان، CI والجودة",
    bullets: [
        "تشديد أمني شامل: SSO SAML/OIDC مشفر أثناء التخزين، رفع ملفات مقيد، ملف تعريف ارتباط httpOnly، إزالة XSS من الكشك، JWT للجوال خارج Hive.",
        "تغطية الواجهة الخلفية 71% مقاسة في CI (بوابة لكل وحدة، الرواتب ≥ 80% هدف)؛ 1,917 اختبارًا، 424 نقطة نهاية API موثقة.",
        "توحيد الترقيم وعقود الاستجابة (Growth, Cameras, DeviceToken, CabinetShare).",
        "CI: 69 إجراءً مثبتًا بـ SHA، dependency-review حاجز، بوابة ضد SHA القديم، فحص الأسرار A-2 يُنفذ فعليًا."
    ],
  },
  {
    version: '4.23.5',
    isoDate: '2026-07-19',
    title: "إصلاحات الإنتاج — البدء البارد، E2E والرواتب",
    bullets: [
        "تسخين مضاد للبدء البارد لـ Render لبيئة E2E التجريبية (مهلة Playwright 15 ثانية).",
        "متانة Vercel: ignoreCommand مع التراجع إلى SHA السابق.",
        "إصلاحات الرواتب: رقم الفاتورة يُحفظ فعليًا، تشغيل workers/scheduler في Render، فهرس FK لـ salary_advances."
    ],
  },
  {
    version: '4.23.4',
    isoDate: '2026-07-19',
    title: "إصلاح ترجمة Dart في التطبيقات الثلاثة + تشديد CI",
    bullets: [
        "ملفات main.dart للتطبيقات الثلاثة تعلن main() بدون async بينما ينتظر الجسم SentryFlutter.init — تمت مواءمتها مع Future<void> main() async (كان يعطل وظيفة Flutter على main).",
        "CI/CD: تشديد سلسلة التوريد (تثبيت SHA للإجراءات الخارجية) وإزالة الازدواجية في إعداد PHP/Flutter عبر إجراءات مركبة قابلة لإعادة الاستخدام (~360 سطرًا أقل)."
    ],
  },
  {
    version: '4.23.3',
    isoDate: '2026-07-19',
    title: "حل 34 تنبيهًا من Dependabot (11 حرجة، 16 متوسطة، 7 منخفضة)",
    bullets: [
        "api (composer): symfony/yaml 8.0.8 ← 8.1.1 (ReDoS Parser::cleanup)؛ إصلاح form-data (حقن CRLF).",
        "الواجهة والإدارة: npm audit fix (form-data, ws, js-yaml)، إصلاح postcss، vite 6.4.3 (SSRF لخادم التطوير، path traversal).",
        "web-offline: Next.js 16.2.10 + ESLint 9 (SSRF, XSS, تسميم ذاكرة التخزين المؤقت)؛ تدقيق npm/composer بلا ثغرات متبقية."
    ],
  },
  {
    version: '4.23.2',
    isoDate: '2026-07-16',
    title: "أمان CI: إصلاح حقن workflow_run، صلاحيات صريحة",
    bullets: [
        "deploy-main.yml: لم يعد head_branch يُدرج في كتلة run: shell (متغير بيئة) + التحقق من head_repository == github.repository.",
        "صلاحيات GITHUB_TOKEN صريحة (contents: read) على سير العمل الناقصة؛ phpstan-modules.neon يحمّل Larastan الآن (حل 36 خطأ أسلوب غير معرّف)."
    ],
  },
  {
    version: '4.23.1',
    isoDate: '2026-07-16',
    title: "وحدة التسويق (المرحلة 1): المخطط الأساسي والنماذج",
    bullets: [
        "وحدة DDD جديدة api/app/Modules/Marketing/ مع ترحيلات tenant لـ social_accounts و social_posts.",
        "لا يُخزَّن أي رمز OAuth خام (مرجع مشفر إلى ملف المجمّع)؛ نماذج Eloquent بنطاق tenant، مع اختبارات Feature."
    ],
  },
  {
    version: '4.23.0',
    isoDate: '2026-07-16',
    title: "قبول دور مدير التسويق من API (وحدة التسويق — المرحلة 0)",
    bullets: [
        "كان StoreEmployeeRequest/UpdateEmployeeRequest يتحقق من manager_role دون قيمة marketing — أُضيفت إلى القائمة المسموحة (POST/PATCH /employees)."
    ],
  },
  {
    version: '4.22.8',
    isoDate: '2026-07-12',
    title: "بريد التنقيط للتأهيل: 3 رسائل رعاية تلقائية",
    bullets: [
        "يرسل SendTrialDripEmailJob رسائل J+1 وJ+3 وJ+7 بعد توفير النسخة التجريبية (محاولات محدودة، حالة تجريبية موثقة).",
        "نموذج OnboardingProgress جديد بنطاق company_id + employee_id؛ إعادة هيكلة معالج تأهيل الجوال (شريط تقدم، حالات مطلوبة/خطأ)."
    ],
  },
  {
    version: '4.22.7',
    isoDate: '2026-07-05',
    title: "إصلاح ParseError الرواتب + خصم فترة الراحة من ساعات العمل",
    bullets: [
        "إصلاح ParseError في 7 ملفات CountryRules (فتح CI الرئيسي).",
        "تُخصم دقائق الراحة (break_minutes) الآن من ساعات العمل (AttendanceLog)."
    ],
  },
  {
    version: '4.22.6',
    isoDate: '2026-07-05',
    title: "إصلاح PHPStan Modules Architecture (نطاق Eloquent لـ Absence)",
    bullets: [
        "كان AbsenceService::request() و LeavePolicyController::balances() يصلان إلى نطاقات Eloquent غير معروفة — تم إصلاح الأنواع، بوابة CI خضراء."
    ],
  },
  {
    version: '4.22.5',
    isoDate: '2026-07-05',
    title: "عزل متعدد المستأجرين للوظائف المجدولة",
    bullets: [
        "يضبط TenantMiddleware search_path الخاص بـ PostgreSQL وربط current_company للوظائف بشكل صحيح.",
        "واجهة جديدة App\\Contracts\\Queue\\TenantScopedJob: كل وظيفة تتطلب سياق tenant تعلن tenantCompanyId()."
    ],
  },
  {
    version: '4.22.4',
    isoDate: '2026-07-04',
    title: "CI: حل 136 فشلًا متبقٍ (company_id مفقود)",
    bullets: [
        "نفس فئة خطأ 4.22.3 مع AbsenceType: company_id (NOT NULL) غائب عن $fillable/casts للنموذج الأساسي — تم الإصلاح، 899 اختبارًا أخضر."
    ],
  },
  {
    version: '4.22.3',
    isoDate: '2026-07-04',
    title: "CI: حل 160 فشلًا متبقٍ (النماذج الأساسية)",
    bullets: [
        "نموذج Absence الأساسي (عبر shim App\\Models\\Absence) لم يعلن company_id (NOT NULL) في $fillable ولا التحويل المناسب — تمت المواءمة، 899 اختبارًا أخضر."
    ],
  },
  {
    version: '4.22.2',
    isoDate: '2026-07-04',
    title: "CI: إصلاح shims في app/Models (class_alias + توليد)",
    bullets: [
        "75 ملف shim app/Models/*.php وُلّدت في v4.22.0: class_alias كان يحل اسم فئة غير صحيح — أُعيد توليدها، Backend + Coverage عادتا للأخضر."
    ],
  },
  {
    version: '4.22.1',
    isoDate: '2026-07-02',
    title: "تنظيف التوثيق — mojibake والاتساق",
    bullets: [
        "إصلاح الترميزات المعطوبة (mojibake) في مخططات UML ومستندات أخرى — لا تغيير وظيفي."
    ],
  },
  {
    version: '4.21.1',
    isoDate: '2026-07-01',
    title: "فتح CI: ترحيل الفواصل المفلتة",
    bullets: [
        "فواصل مفلتة بنمط PHP في تعليق SQL لترحيل employee_attendance_preferences — عاد CI الذي كان يعطل كل عمليات الدمج إلى الأخضر."
    ],
  },
  {
    version: '4.22.0',
    isoDate: '2026-07-01',
    title: "التنظيف المعماري المرحلة 2 — النماذج والخدمات وFormRequest",
    bullets: [
        "إعادة وضع 17 نموذجًا يتيمًا في Core/Tenant/Domain/Models و Core/Auth/Domain/Models؛ إعادة تنظيم الخدمات وFormRequest حسب الوحدة."
    ],
  },
  {
    version: '4.21.0',
    isoDate: '2026-07-01',
    title: "التنظيف المعماري للـ API — إزالة التكرارات القديمة",
    bullets: [
        "حذف 90 وحدة تحكم مكررة من app/Http/Controllers/Api/V1 (نُقلت إلى app/Modules/*/Interfaces/Api/V1)."
    ],
  },
  {
    version: '4.17.4',
    isoDate: '2026-07-04',
    title: "EdgeSync: ترحيل EdgeController إلى وحدة DDD",
    bullets: [
        "ترحيل EdgeController و EdgeDownloadController من app/Http/Controllers/Api/V1 إلى App\\Modules\\EdgeSync\\Interfaces\\Api\\V1.",
        "رفع PHPStan للوحدات من المستوى 3 إلى 5 مع استثناءات مستهدفة للوحدات قيد الترحيل."
    ],
  }
  ],
}

/**
 * #4610 : releases localisees selon la locale (fallback FR).
 */
export function getChangelogReleases(locale: string): PublicChangelogRelease[] {
  return changelogByLocale[locale as 'fr' | 'en' | 'tr' | 'ar'] ?? changelogByLocale.fr
}
