# PROMPT CU-01 — Dashboard Gestionnaire Web (Vue.js 3)
# Agent : CURSOR
# Phase : Semaine 8-12
# Prérequis : CC-02, CC-03, CC-04 backend terminés et déployés

---

## CONTEXTE

Tu développes l'interface web gestionnaire de Leopardo RH.
Stack : Vue.js 3 + Inertia.js + Tailwind CSS + PrimeVue (déjà installés par Claude Code dans Laravel).

---

## DOCUMENTS DE RÉFÉRENCE

- `02_API_CONTRATS_COMPLET.md` → toutes les réponses API
- `10_RBAC_COMPLET.md` → permissions par rôle (masquer les boutons selon manager_role)
- `leopardo_rh_wireframes_COMPLET.html` → wireframes de référence visuelle

---

## ARCHITECTURE INERTIA.JS

Les données viennent des Controllers Laravel via `Inertia::render()`.
PAS de fetch() direct depuis Vue — les données sont dans `$page.props`.

```php
// Exemple dans DashboardController.php
return Inertia::render('Dashboard/Index', [
    'stats' => $this->getDashboardStats(),
    'todayAttendance' => $this->getTodayAttendance(),
]);
```

```vue
<!-- Exemple dans Vue -->
<script setup>
const props = defineProps({ stats: Object, todayAttendance: Array });
</script>
```

---

## PAGES À CRÉER — Phase 1 (Semaines 8-10)

### Dashboard (`resources/js/Pages/Dashboard/Index.vue`)
```
KPIs du jour en haut :
  - Présents aujourd'hui / Total employés
  - En retard aujourd'hui
  - Absents aujourd'hui
  - Tâches urgentes non assignées

Tableau des pointages du jour (live, mis à jour toutes les 5 min)
Alertes : absences en attente d'approbation
```

### Employés (`resources/js/Pages/Employees/`)
```
Index.vue   → DataTable PrimeVue (colonnes: matricule, nom, département, poste, statut)
             → Filtres : département, statut, search
             → Boutons selon RBAC (Créer visible uniquement pour Principal + RH)
Create.vue  → Formulaire création employé
Show.vue    → Fiche employé complète avec onglets (Infos, Pointages, Absences, Bulletins)
```

### Pointage (`resources/js/Pages/Attendance/`)
```
Index.vue   → Liste du jour avec statuts colorés
             → Sélecteur de date pour voir d'autres jours
             → Bouton "Corriger" (manuel) visible pour gestionnaire
Calendar.vue → Vue calendrier hebdomadaire par employé
```

---

## RÈGLES DE DÉVELOPPEMENT

### 1. RBAC dans l'UI
```vue
<!-- Cacher les boutons d'action selon le rôle -->
<Button
  v-if="$page.props.auth.user.manager_role === 'principal' || $page.props.auth.user.manager_role === 'rh'"
  label="Créer un employé"
  @click="router.visit('/employees/create')"
/>
```

### 2. DataTable PrimeVue (pattern obligatoire)
```vue
<DataTable
  :value="employees"
  :paginator="true"
  :rows="20"
  :loading="loading"
  filterDisplay="menu"
  :globalFilterFields="['matricule', 'firstName', 'lastName', 'email']"
  sortMode="multiple"
  responsiveLayout="scroll"
>
  <Column field="matricule" header="Matricule" sortable />
  <Column field="fullName" header="Nom" sortable />
  <!-- ... -->
</DataTable>
```

### 3. Pas de style inline
Utiliser uniquement des classes Tailwind CSS. Jamais de `style=""`.

---

## COMMIT ATTENDU
```
feat: add manager dashboard with employee list and attendance overview
feat: add employee CRUD pages with RBAC-aware UI
```

---
---

# PROMPT VO-01 — Landing Page Leopardo RH
# Agent : V0.DEV (Vercel)
# Phase : Semaine 13-14

---

## MISSION

Crée la landing page commerciale de Leopardo RH.
Elle sera intégrée dans Laravel Blade (SEO-friendly).
Format attendu : HTML + Tailwind CSS (fichier unique).

## SECTIONS REQUISES (dans l'ordre exact)

### 1. Navigation
- Logo Leopardo RH (texte stylisé + icône léopard)
- Liens : Fonctionnalités | Tarifs | Blog | Contact
- CTA : bouton "Essai gratuit" en orange (#E67E22)

### 2. Hero Section
- Titre principal : "Gérez vos RH simplement"
- Sous-titre : "Pointage GPS, absences, paie et tâches — tout en un"
- CTA primaire : "Commencer gratuitement — 14 jours d'essai"
- CTA secondaire : "Voir une démo"
- Image/mockup : téléphone avec l'app mobile à droite

### 3. Pain Points (3 colonnes)
- 📄 "Fini les feuilles de présence papier"
- ❌ "Fini les erreurs de calcul de paie"
- ⏰ "Fini les retards non tracés"

### 4. Fonctionnalités (4 cartes)
- 📍 Pointage GPS + Biométrique ZKTeco
- 🌴 Gestion absences et congés
- 💰 Paie automatisée avec bulletins PDF
- ✅ Tâches et suivi de performance

### 5. Plans Tarifaires (3 colonnes)
```
Starter       Business      Enterprise
Gratuit       79€/mois      Sur devis
5 employés    200 employes  Illimité
              [Mise en avant]
```

### 6. Pays supportés
- Algérie, Maroc, Tunisie, Turquie, France (avec drapeaux emoji)

### 7. FAQ (6 questions accordéon)
- "Est-ce que mes données sont sécurisées ?"
- "Fonctionne avec les appareils ZKTeco ?"
- "Disponible en arabe ?"
- "Puis-je changer de plan ?"
- "Y a-t-il une app mobile ?"
- "Contrat d'engagement ?"

### 8. Footer
- Logo + tagline
- Liens : CGU | Politique de confidentialité | Contact | Blog
- Copyright 2026 Leopardo RH

## STYLE

```
Couleur primaire  : #1A5276 (bleu foncé)
Couleur accent    : #E67E22 (orange)
Font              : Inter (Google Fonts)
Border-radius     : lg (arrondi moderne)
Shadows           : md
Background hero   : gradient bleu foncé → bleu moyen
```

## NOTES POUR L'INTÉGRATION BLADE

Le fichier HTML sera intégré dans `resources/views/landing/index.blade.php`.
Remplacer les URLs statiques par `{{ asset('images/...') }}`.
Les formulaires de contact utiliseront `@csrf` de Laravel.

---
---

# PROMPT MA-01 — Génération fichiers Mock JSON Flutter
# Agent : MANUS (Agent autonome)
# Phase : IMMÉDIATEMENT (avant toute autre tâche)

---

## MISSION

Générer 8 fichiers JSON de mock data pour l'application Flutter.
Ces fichiers permettent à Jules de développer sans API réelle.

## INSTRUCTION EXACTE

Les fichiers sont DÉJÀ créés dans `03_MOCK_JSON/`.
Vérifier qu'ils sont conformes aux contrats API dans `01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`.

Si des fichiers manquent ou sont non conformes, les créer/corriger.

Fichiers à vérifier/créer :
1. `mock_auth_login.json` → POST /auth/login
2. `mock_auth_me.json` → GET /auth/me
3. `mock_attendance_today_A_not_checked.json` → GET /attendance/today (data: null)
4. `mock_attendance_today_B_checked_in.json` → GET /attendance/today (avec check_in)
5. `mock_attendance_history.json` → GET /attendance (30 entrées variées)
6. `mock_absences.json` → GET /absences (4 absences, statuts variés)
7. `mock_tasks.json` → GET /tasks (4 tâches, priorités variées)
8. `mock_payroll.json` → GET /payroll (6 derniers bulletins)
9. `mock_notifications.json` → GET /notifications (5 notifs, 2 non lues)

## VÉRIFICATIONS OBLIGATOIRES

- Toutes les dates sont en ISO 8601 (`2026-04-15T07:55:12Z`)
- Tous les montants utilisent le point décimal (`75000.00`)
- Les structures correspondent EXACTEMENT aux réponses API documentées
- Les données sont réalistes (noms algériens, montants en DZD, timezone Africa/Algiers)

---
---

# PROMPT MA-02 — Génération CHANGELOG + Git Conventions
# Agent : MANUS (Agent autonome)
# Phase : Semaine 1

---

## MISSION

Créer les fichiers de conventions Git et le CHANGELOG initial.

## FICHIER 1 : `api/CHANGELOG.md`

```markdown
# CHANGELOG — Leopardo RH API
Format : Keep a Changelog (keepachangelog.com)
Versioning : Semantic Versioning (semver.org)

## [Unreleased]
### À faire
- Module Auth (login, logout, me, FCM)
- Module Pointage (check-in, check-out, today, history)
- Module Configuration (departments, positions, schedules, sites)
- Module Absences
- Module Avances
- Module Tâches
- Module Paie
- Module Notifications
- Module Évaluations
- Module Rapports
- Super Admin

## [0.0.1] - 2026-03-29
### Ajouté
- Initialisation du projet Laravel 11
- Configuration PostgreSQL 16 multi-schéma
- Schéma SQL complet (schéma public)
- Seeders : plans, langues, modèles RH par pays
- TenantMiddleware (switch de schéma)
```

## FICHIER 2 : `README.md` (monorepo racine)

Créer un README complet avec :
- Description du projet
- Structure du monorepo (api/, mobile/, docs/)
- Prérequis (PHP 8.3, PostgreSQL 16, Redis 7, Flutter 3.x, Node.js 20)
- Instructions de setup local (backend + mobile)
- Conventions Git (branches + commits)
- Liens vers la documentation


---

## SUPPORT RTL — ARABE (OBLIGATOIRE)

Le produit supporte le français (LTR), l'arabe (RTL), le turc (LTR) et l'anglais (LTR).
Le dashboard Vue.js/Inertia DOIT gérer le RTL arabe nativement.

### Stratégie implémentation RTL

**1. Tailwind CSS — activer le variant RTL dans tailwind.config.js :**
```javascript
// tailwind.config.js
module.exports = {
  content: ['./resources/**/*.{vue,js,ts}'],
  plugins: [],
  // Tailwind v3 supporte rtl: nativement
}
```

**2. Layout racine (app.blade.php) :**
```html
<html :lang="$page.props.locale" :dir="$page.props.textDirection">
```

**3. Inertia — partager la direction depuis Laravel :**
```php
// AppServiceProvider.php
Inertia::share([
    'locale'        => fn() => app()->getLocale(),
    'textDirection' => fn() => in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr',
]);
```

**4. Composant Vue — classes conditionnelles RTL :**
```vue
<template>
  <!-- ✅ Utiliser les classes Tailwind rtl: -->
  <div class="flex rtl:flex-row-reverse">
    <div class="ml-4 rtl:ml-0 rtl:mr-4">Contenu</div>
  </div>

  <!-- ✅ Pour les icônes directionnelles (flèches) -->
  <ChevronRightIcon class="rtl:rotate-180" />

  <!-- ✅ Pour les textes -->
  <p class="text-left rtl:text-right">Texte</p>
</template>
```

**5. Règles à respecter :**
- Utiliser `ms-` (margin-start) et `me-` (margin-end) plutôt que `ml-`/`mr-` pour les espacements directionnels
- Tester chaque écran en arabe — activer via Settings > Langue > العربية
- Les formulaires de saisie de données (montants, dates) restent LTR même en mode RTL

**6. Fichiers de traduction Laravel :**
```
resources/lang/
├── fr/          (Français — LTR)
├── ar/          (العربية — RTL)
├── tr/          (Türkçe — LTR)
└── en/          (English — LTR)
```
