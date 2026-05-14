# Good First Issues — Leopardo RH

10+ issues a creer sur GitHub avec le label `good first issue` pour attirer des contributeurs.

---

## Backend (Laravel)

### 1. Ajouter la validation email unique sur `POST /api/v1/employees`
**Labels** : `good first issue`, `backend`, `validation`
**Description** : Actuellement, il est possible de creer deux employes avec le meme email dans un tenant. Ajouter une regle `unique` scope au tenant.
**Fichiers** : `app/Http/Controllers/Api/V1/EmployeeController.php`

### 2. Ajouter un endpoint `GET /api/v1/me/contract` (singulier)
**Labels** : `good first issue`, `backend`, `api`
**Description** : Retourner le contrat actif de l'employe connecte. Route dediee plus simple que le listing complet.
**Fichiers** : `routes/modules/rh.php`, nouveau controller ou methode existante

### 3. Ajouter le test `FleetControllerTest` manquant
**Labels** : `good first issue`, `backend`, `testing`
**Description** : La suite Feature couvre la plupart des controllers sauf FleetController. Ecrire les tests pour overview, live-map et rapports.
**Fichiers** : `tests/Feature/FleetControllerTest.php`

### 4. Ajouter le test `PaySlipControllerTest` manquant
**Labels** : `good first issue`, `backend`, `testing`
**Description** : Tester le listing, le detail et le telecharement PDF des bulletins de paie.
**Fichiers** : `tests/Feature/PaySlipControllerTest.php`

---

## Frontend — Admin Dashboard (Vue.js)

### 5. Ajouter un mode sombre au dashboard
**Labels** : `good first issue`, `frontend`, `ui`
**Description** : Le dashboard admin utilise Tailwind. Ajouter le support `dark:` aux composants principaux et un toggle dans Settings.
**Fichiers** : `front/admin-dashboard/src/components/layout/Sidebar.vue`, `DashboardLayout.vue`

### 6. Ajouter des filtres par date sur PayrollView
**Labels** : `good first issue`, `frontend`, `feature`
**Description** : La vue paie affiche tous les runs. Ajouter un date picker pour filtrer par mois/annee.
**Fichiers** : `front/admin-dashboard/src/views/payroll/PayrollView.vue`

### 7. Ameliorer l'accessibilite du DataTable
**Labels** : `good first issue`, `frontend`, `a11y`
**Description** : Ajouter les roles ARIA (`role="grid"`, `aria-sort`, `aria-label`) au composant DataTable pour les lecteurs d'ecran.
**Fichiers** : `front/admin-dashboard/src/components/common/DataTable.vue`

---

## Frontend — Vitrine (Next.js)

### 8. Corriger les slugs sitemap vs blog
**Labels** : `good first issue`, `frontend`, `seo`, `bug`
**Description** : Le sitemap genere les URLs depuis les noms de fichiers `.md` alors que les pages blog utilisent les slugs de `blog.ts`. Aligner les deux.
**Fichiers** : `front/web/src/app/api/sitemap/route.ts`, `front/web/src/data/blog.ts`

### 9. Ajouter une page /pricing avec les plans
**Labels** : `good first issue`, `frontend`, `feature`
**Description** : Creer une page pricing responsive avec les 3 plans (Starter, Pro, Enterprise) et CTA vers /demo.
**Fichiers** : `front/web/src/app/(landing)/pricing/page.tsx`

---

## Mobile (Flutter)

### 10. Ajouter un ecran "Mon profil" avec photo
**Labels** : `good first issue`, `mobile`, `feature`
**Description** : Ecran profil avec photo, infos employe, changement de langue. Utiliser le pattern `ConsumerWidget` + `FutureProvider`.
**Fichiers** : `front/mobile/lib/features/profile/`

### 11. Ajouter les tests unitaires pour les modeles
**Labels** : `good first issue`, `mobile`, `testing`
**Description** : Ecrire des tests `fromJson` pour les 6 nouveaux modeles (Contract, TrainingEnrollment, ExpenseClaim, Approval, OnboardingStep, VehiclePosition).
**Fichiers** : `front/mobile/test/models/`

### 12. Internationaliser l'ecran AiChatScreen
**Labels** : `good first issue`, `mobile`, `i18n`
**Description** : Les textes de l'ecran Chat IA sont en dur en francais. Utiliser les cles `AppLocalizations` pour FR/EN/AR/TR.
**Fichiers** : `front/mobile/lib/features/ai_chat/screens/ai_chat_screen.dart`

---

## Documentation

### 13. Traduire DEVELOPMENT.md en anglais
**Labels** : `good first issue`, `documentation`
**Description** : Le guide contributeur est en francais. Ajouter une version anglaise ou transformer en bilingue.
**Fichiers** : `DEVELOPMENT.md`
