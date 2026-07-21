# Good First Issues — Leopardo RH

> ⚠️ Voir aussi `docs/GESTION_PROJET/GOOD_FIRST_ISSUES.md` (liste soeur, perimetre gestion projet).
> Ce fichier a ete corrige le 2026-07-21 : les chemins pointaient vers `front/mobile/` et
> `app/Http/Controllers/Api/V1/` (l'un et l'autre supprimes depuis, voir `api/ARCHITECTURE.md`
> et `CHANGELOG.md`), et deux items demandaient des tests deja existants dans le depot.

10+ issues a creer sur GitHub avec le label `good first issue` pour attirer des contributeurs.

---

## Backend (Laravel)

### 1. Ajouter la validation email unique sur `POST /api/v1/employees`
**Labels** : `good first issue`, `backend`, `validation`
**Description** : Actuellement, il est possible de creer deux employes avec le meme email dans un tenant. Ajouter une regle `unique` scope au tenant.
**Fichiers** : `api/app/Modules/HR/Interfaces/Api/V1/Controllers/EmployeeController.php`

### 2. Ajouter un endpoint `GET /api/v1/me/contract` (singulier)
**Labels** : `good first issue`, `backend`, `api`
**Description** : Retourner le contrat actif de l'employe connecte. Route dediee plus simple que le listing complet.
**Fichiers** : `api/routes/modules/rh.php`, nouveau controller ou methode existante dans `api/app/Modules/HR/Interfaces/Api/V1/Controllers/`

---

## Frontend — Admin Dashboard (Vue.js)

### 3. Ajouter un mode sombre au dashboard
**Labels** : `good first issue`, `frontend`, `ui`
**Description** : Le dashboard admin utilise Tailwind. Ajouter le support `dark:` aux composants principaux et un toggle dans Settings.
**Fichiers** : `front/admin-dashboard/src/components/layout/Sidebar.vue`, `DashboardLayout.vue`

### 4. Ajouter des filtres par date sur PayrollView
**Labels** : `good first issue`, `frontend`, `feature`
**Description** : La vue paie affiche tous les runs. Ajouter un date picker pour filtrer par mois/annee.
**Fichiers** : `front/admin-dashboard/src/views/payroll/PayrollView.vue`

### 5. Ameliorer l'accessibilite du DataTable
**Labels** : `good first issue`, `frontend`, `a11y`
**Description** : Ajouter les roles ARIA (`role="grid"`, `aria-sort`, `aria-label`) au composant DataTable pour les lecteurs d'ecran.
**Fichiers** : `front/admin-dashboard/src/components/common/DataTable.vue`

---

## Frontend — Vitrine (Next.js)

### 6. Corriger les slugs sitemap vs blog
**Labels** : `good first issue`, `frontend`, `seo`, `bug`
**Description** : Le sitemap genere les URLs depuis les noms de fichiers `.md` alors que les pages blog utilisent les slugs de `blog.ts`. Aligner les deux.
**Fichiers** : `front/web/src/app/api/sitemap/route.ts`, `front/web/src/data/blog.ts`

### 7. Ajouter une page /pricing avec les plans
**Labels** : `good first issue`, `frontend`, `feature`
**Description** : Creer une page pricing responsive avec les 3 plans (Starter, Pro, Enterprise) et CTA vers /demo.
**Fichiers** : `front/web/src/app/(landing)/pricing/page.tsx`

---

## Mobile (Flutter)

### 8. Ajouter un ecran "Mon profil" avec photo (leopardo_employee)
**Labels** : `good first issue`, `mobile`, `feature`
**Description** : `leopardo_employee` n'a pas d'ecran profil dedie ; les infos compte sont noyees dans `features/settings/`. Ajouter un ecran profil avec photo, infos employe, changement de langue. Utiliser le pattern `ConsumerWidget` + `FutureProvider`, coherent avec `features/settings/screens/settings_screen.dart`.
**Fichiers** : `front/mobile_apps/leopardo_employee/lib/features/profile/` (nouveau)

### 9. Ajouter les tests unitaires des modeles partages a `leopardo_employee` et `leopardo_platform_admin`
**Labels** : `good first issue`, `mobile`, `testing`
**Description** : Les 6 modeles partages (`Contract`, `TrainingEnrollment`, `ExpenseClaim`, `Approval`, `OnboardingStep`, `VehiclePosition`) vivent dans `leopardo_core/lib/models/` et ont deja des tests `fromJson` dans `leopardo_hr/test/models/` et `leopardo_manager/test/models/`, mais pas dans `leopardo_employee/test/` (dossier `test/` absent) ni `leopardo_platform_admin/test/models/`. Porter les memes tests vers ces deux apps.
**Fichiers** : `front/mobile_apps/leopardo_employee/test/models/` (nouveau), `front/mobile_apps/leopardo_platform_admin/test/models/`

### 10. Internationaliser l'ecran AiChatScreen
**Labels** : `good first issue`, `mobile`, `i18n`
**Description** : Les textes de l'ecran Chat IA sont en dur en francais dans les 3 apps qui l'embarquent. Utiliser les cles `AppLocalizations` pour FR/EN/AR/TR.
**Fichiers** : `front/mobile_apps/leopardo_employee/lib/features/ai_chat/screens/ai_chat_screen.dart`, memes fichiers dans `leopardo_manager/` et `leopardo_hr/`

---

## Documentation

### 11. Documenter les codes d'erreur API
**Labels** : `good first issue`, `documentation`, `api`
**Description** : Il n'existe pas de reference centralisee des codes d'erreur API personnalises (ex: `RATE_LIMIT_EXCEEDED`, `UNSUPPORTED_API_VERSION`, `TENANT_NOT_FOUND`). Creer un fichier qui liste, pour chaque code, le statut HTTP, le code erreur, le message et un exemple de reponse JSON.
**Fichiers** : `docs/api/ERROR_CODES.md` (nouveau)
