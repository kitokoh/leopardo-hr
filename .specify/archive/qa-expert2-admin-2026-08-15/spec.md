# Feature Specification: QA Expert #2 — Admin (front/admin-dashboard) (2026-08-15)

**Feature**: `qa-expert2-admin-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + revue statique experte (rg/scripts) + cross-check issues existantes.

## Contexte

Deuxième vague de test expert de la mission propriétaire (tester « dans tous les sens », consigner chaque manquement selon la méthode Spec Kit, puis implémenter). Les findings ci-dessous sont **nouveaux** : vérifiés contre les ~140 issues ouvertes et les branches/PRs existantes (règle anti-doublon #2400).

## Findings non couverts (issues créées)

### #3033 [P1] Admin — build prod cassé : DocumentReportIcon inexistant dans @heroicons/vue → vite build échoue (deploy Cloudflare bloqué)

> **Constat** : `CommandPalette.vue` importe `DocumentReportIcon` qui **n'existe pas** dans `@heroicons/vue` 2.x → `vite build` échoue (MISSING_EXPORT). Tout déploiement admin est bloqué.
> **Preuve** : - `front/admin-dashboard/src/components/common/CommandPalette.vue:45,94,124`
- `grep DocumentReportIcon node_modules/@heroicons/vue/24/outline/esm/` → vide
- `npm run build` → erreur MISSING_EXPORT reproduite
> **Impact** : P1 : CI/deploy Cloudflare Pages cassé ; impossible de livrer les correctifs admin.

### #3034 [P1] Admin — CompanyDetailView crashe : lit health.adoption.kiosk.active jamais renvoyé par le backend → fiche entreprise blanche

> **Constat** : `CompanyDetailView` lit `health.adoption.kiosk.active` (et `company.slug`/`created_at`) que le backend ne renvoie jamais → exception au rendu → page détail entreprise vide/blanche.
> **Preuve** : - `front/admin-dashboard/src/views/companies/CompanyDetailView.vue:80-81,301,309`
- Backend `/platform/companies/{company}/health` : shape sans `kiosk`
> **Impact** : P1 : la fiche client (action « Activer client », abonnement) est inutilisable.

### #3036 [P2] Admin — DashboardView « Priorités Portefeuille » : lit item.name/slug/mrr_eur au lieu de company.*/subscription.mrr → noms vides, MRR 0€, lien /companies/undefined

> **Constat** : Le widget « Priorités Portefeuille » mappe des champs inexistants du payload → noms vides, MRR 0 €, lien Détails → `/companies/undefined`.
> **Preuve** : - `front/admin-dashboard/src/views/DashboardView.vue:81,88-89,109,112`
> **Impact** : Widget principal du cockpit inexploitable.

### #3037 [P2] Admin — DashboardView « Inscriptions en attente » : lit request.name/manager_email au lieu de company_name/email → titres vides

> **Constat** : Le widget « Inscriptions en attente » lit `request.name`/`request.manager_email` alors que l'API renvoie `company_name`/`email` → titres vides.
> **Preuve** : - `front/admin-dashboard/src/views/DashboardView.vue:133-134`
> **Impact** : Demandes entrantes illisibles dans le cockpit.

### #3038 [P2] Admin — UsersView : colonne « Inscription » toujours « - » (mapping createdAt vs created_at) alors que la date existe côté API

> **Constat** : `UsersView` mappe `createdAt`/`lastLoginAt` mais `UserTable` lit `created_at`/`last_login_at` → la colonne « Inscription » affiche toujours « - ».
> **Preuve** : - `front/admin-dashboard/src/views/users/UsersView.vue:317`
- `front/admin-dashboard/src/components/users/UserTable.vue:121,189`
> **Impact** : Donnée existante invisible.

### #3039 [P3] Admin — 16 clés i18n manquantes dans les 4 locales (users.impersonation.*, users.toast.bulkDone) → fallback FR en ar/tr

> **Constat** : 16 clés i18n utilisées par UsersView sont absentes des catalogues → les locales ar/tr affichent du français.
> **Preuve** : - `front/admin-dashboard/src/views/users/UsersView.vue:155-195,374,423-451` (clés `users.impersonation.*`, `users.toast.bulkDone`…)
- Catalogues `src/i18n/locales/*.json` sans ces clés
> **Impact** : Incohérence i18n admin (connexe #2639/#2613).

### #3041 [P3] Admin — raccourci Alt+R pointe /recruitment (route tenant gardée) → rebond systématique + toast

> **Constat** : Le raccourci Alt+R navigue vers `/recruitment`, route tenant gardée par design (#2272) → rebond muet systématique.
> **Preuve** : - `front/admin-dashboard/src/composables/useKeyboardShortcuts.js:51-53`
> **Impact** : Raccourci inutile/bruyant.

### #3042 [P3] Admin — recherche header peut naviguer vers des routes tenant gardées (Paie, Congés…) → rebond muet

> **Constat** : La recherche globale du header propose des routes tenant gardées (Paie, Congés…) → navigation → rebond silencieux.
> **Preuve** : - `front/admin-dashboard/src/components/layout/Header.vue:148-163`
> **Impact** : UX dégradée (connexe #2611/#2787).

### #3043 [P3] Admin — bandeau « Mode maintenance » jamais déclenchable : setMaintenanceMode exposé mais aucun appelant

> **Constat** : `SystemAlertsOverlay` expose `setMaintenanceMode` mais aucun composant ne l'appelle → le bandeau « Mode maintenance » est mort.
> **Preuve** : - `front/admin-dashboard/src/components/alerts/SystemAlertsOverlay.vue:227-230`
> **Impact** : Fonctionnalité annoncée inatteignable.

### #3044 [P3] Admin — boutons d'action des notifications jamais affichés (aucune notification ne porte le champ action)

> **Constat** : `NotificationPanel` affiche conditionnellement des boutons d'action sur `notification.action` mais l'API n'envoie jamais ce champ → boutons morts.
> **Preuve** : - `front/admin-dashboard/src/components/notifications/NotificationPanel.vue:38-50,151-152`
> **Impact** : Code mort / promesse UI non tenue.

### #3045 [P3] Admin — export CSV AnalyticsView sans échappement anti-injection de formule (incohérent avec UsersView)

> **Constat** : L'export CSV d'AnalyticsView ne protège pas les cellules commençant par =,+,-,@ → injection de formule possible ; UsersView a déjà le garde.
> **Preuve** : - `front/admin-dashboard/src/views/analytics/AnalyticsView.vue:234-239`
> **Impact** : Sécurité desktop (Excel/Sheets) — classe #2700 non couverte ici.

### #3046 [P3] Admin — 6 imports d'icônes inutilisés dans CommandPalette (résidu du retrait des entrées tenant)

> **Constat** : CommandPalette importe 6 icônes inutilisées (warnings lint) — résidu du retrait des entrées tenant.
> **Preuve** : - `front/admin-dashboard/src/components/common/CommandPalette.vue:81-88`
> **Impact** : Hygiène (warnings lint).

## Règles d'implémentation
- Une PR par issue avec `Closes #N` dans le body (Constitution §VII).
- Pas de données fabriquées : endpoint réel ou état vide honnête.
- i18n : les 4 locales FR/EN/TR/AR dans le même changement ; jamais de clés brutes affichées.
- Vérifier la garde anti-doublon avant push : `git ls-remote --heads origin | grep <issue>`.