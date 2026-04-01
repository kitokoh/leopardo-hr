# CU-01 — Dashboard Web Vue.js 3 (Inertia.js)
# Agent : Cursor
# Durée : 10-12 heures (réparti sur plusieurs sessions)
# Prérequis : CC-03 vert (Employés + Config backend OK), CC-04 vert (Pointage OK)

---

## ARCHITECTURE INERTIA.JS — RÈGLE FONDAMENTALE

**Pas de fetch() direct depuis Vue.**
Toutes les données viennent des Controllers Laravel via `Inertia::render()` dans `$page.props`.
Le routing est géré par Laravel (pas de vue-router côté client).

```php
// Exemple controller Laravel → Vue
return Inertia::render('Dashboard/Index', [
    'stats'           => $this->getDashboardStats(),
    'todayAttendance' => $this->getTodayAttendanceSummary(),
    'pendingAbsences' => $this->getPendingAbsences(),
]);
```

```vue
<!-- Exemple Vue — lire depuis $page.props -->
<script setup>
const props = defineProps({
  stats: Object,
  todayAttendance: Array,
  pendingAbsences: Array,
});
</script>
```

---

## PAGES À CRÉER — LISTE COMPLÈTE

### Phase 1 — Semaine 8-10

| Page | Fichier Vue | Données Inertia |
|---|---|---|
| Dashboard | `Pages/Dashboard/Index.vue` | stats, todayAttendance, pendingAbsences |
| Liste employés | `Pages/Employees/Index.vue` | employees (paginés), departments, positions |
| Créer employé | `Pages/Employees/Create.vue` | departments, positions, schedules, sites |
| Fiche employé | `Pages/Employees/Show.vue` | employee, absences, attendanceLogs, payslips |
| Modifier employé | `Pages/Employees/Edit.vue` | employee, departments, positions, schedules |
| Pointages du jour | `Pages/Attendance/Index.vue` | attendances, employees |
| Calendrier pointage | `Pages/Attendance/Calendar.vue` | month, year, employeeId (filtres) |

### Phase 2 — Semaine 10-12

| Page | Fichier Vue | Données Inertia |
|---|---|---|
| Liste absences | `Pages/Absences/Index.vue` | absences (paginées), filters |
| Détail absence | `Pages/Absences/Show.vue` | absence, employee |
| Liste avances | `Pages/Advances/Index.vue` | advances, filters |
| Paie — génération | `Pages/Payroll/Generate.vue` | employees, month, year |
| Paie — liste bulletins | `Pages/Payroll/Index.vue` | payrolls (paginés) |
| Tâches & projets | `Pages/Tasks/Index.vue` | tasks, projects, employees |
| Évaluations | `Pages/Evaluations/Index.vue` | evaluations |
| Rapports | `Pages/Reports/Index.vue` | (filtre mois/année) |
| Config entreprise | `Pages/Settings/Index.vue` | settings |
| Config départements | `Pages/Settings/Departments.vue` | departments |
| Config plannings | `Pages/Settings/Schedules.vue` | schedules |
| Super Admin | `Pages/Admin/Companies/Index.vue` | companies (paginées) |

---

## COMPOSANTS PARTAGÉS OBLIGATOIRES

```
resources/js/Components/
├── Layout/
│   ├── AppLayout.vue        ← layout principal avec sidebar
│   └── Sidebar.vue          ← navigation latérale avec RBAC
├── UI/
│   ├── DataTable.vue        ← wrapper PrimeVue DataTable avec config standard
│   ├── StatusBadge.vue      ← badge coloré (pending=jaune, approved=vert, etc.)
│   ├── ConfirmDialog.vue    ← dialog de confirmation avant suppression
│   ├── PageHeader.vue       ← titre + actions + breadcrumbs
│   └── EmptyState.vue       ← illustration + message quand liste vide
├── Forms/
│   ├── FormField.vue        ← label + input + message d'erreur standardisé
│   └── DateRangePicker.vue  ← sélecteur de plage de dates
└── Charts/
    └── AttendanceChart.vue  ← graphique présences avec Chart.js
```

---

## DASHBOARD — SPÉCIFICATION COMPLÈTE

```vue
<!-- resources/js/Pages/Dashboard/Index.vue -->
<template>
  <!-- KPIs en haut — 4 cartes -->
  <div class="grid grid-cols-4 gap-4 mb-6">
    <KpiCard
      :value="stats.presentToday"
      :total="stats.totalEmployees"
      label="Présents aujourd'hui"
      color="green"
    />
    <KpiCard
      :value="stats.lateToday"
      label="En retard"
      color="orange"
    />
    <KpiCard
      :value="stats.absentToday"
      label="Absents"
      color="red"
    />
    <KpiCard
      :value="stats.pendingAbsences"
      label="Congés à approuver"
      color="blue"
      :clickable="true"
      @click="router.visit('/absences?status=pending')"
    />
  </div>

  <!-- Tableau pointages du jour -->
  <DataTable
    :value="todayAttendance"
    :auto-refresh="300"   <!-- refresh toutes les 5 minutes -->
    title="Pointages d'aujourd'hui"
  >
    <Column field="employee.full_name" header="Employé" sortable />
    <Column field="check_in" header="Arrivée" sortable>
      <template #body="{ data }">
        <span :class="data.status === 'late' ? 'text-red-600 font-bold' : ''">
          {{ formatTime(data.check_in) }}
        </span>
      </template>
    </Column>
    <Column field="status" header="Statut">
      <template #body="{ data }">
        <StatusBadge :status="data.status" />
      </template>
    </Column>
  </DataTable>

  <!-- Alertes : absences en attente -->
  <div v-if="pendingAbsences.length > 0" class="mt-6">
    <h3 class="text-lg font-semibold mb-3">Demandes de congés en attente</h3>
    <div v-for="absence in pendingAbsences" :key="absence.id" class="...">
      <!-- Carte avec bouton Approuver / Refuser inline -->
    </div>
  </div>
</template>
```

---

## RBAC DANS L'UI — RÈGLE OBLIGATOIRE

```vue
<!-- Masquer les actions selon le rôle — récupéré depuis $page.props.auth.user -->
<script setup>
const { auth } = usePage().props;
const isManagerPrincipal = computed(() => auth.user.manager_role === 'principal');
const isRH = computed(() => ['principal', 'rh'].includes(auth.user.manager_role));
const isComptable = computed(() => ['principal', 'comptable'].includes(auth.user.manager_role));
</script>

<template>
  <!-- Bouton visible seulement pour Principal et RH -->
  <Button
    v-if="isRH"
    label="Créer un employé"
    @click="router.visit('/employees/create')"
  />

  <!-- Bouton paie visible seulement pour Comptable et Principal -->
  <Button
    v-if="isComptable"
    label="Générer la paie"
    @click="router.visit('/payroll/generate')"
  />
</template>
```

---

## DATATABLES PRIMEVUE — PATTERN STANDARD

```vue
<!-- Pattern à utiliser sur TOUTES les pages de liste -->
<DataTable
  :value="employees"
  :paginator="true"
  :rows="20"
  :loading="loading"
  v-model:filters="filters"
  filterDisplay="menu"
  :globalFilterFields="['matricule', 'first_name', 'last_name', 'email', 'department.name']"
  sortMode="multiple"
  responsiveLayout="scroll"
  :rowHover="true"
  @row-click="(e) => router.visit(`/employees/${e.data.id}`)"
>
  <template #header>
    <div class="flex justify-between items-center">
      <span class="text-xl font-bold">{{ title }}</span>
      <span class="p-input-icon-left">
        <i class="pi pi-search" />
        <InputText v-model="filters['global'].value" placeholder="Rechercher..." />
      </span>
    </div>
  </template>

  <template #empty>
    <EmptyState message="Aucun employé trouvé" />
  </template>
</DataTable>
```

---

## FORMULAIRE CRÉATION EMPLOYÉ — CHAMPS OBLIGATOIRES

```vue
<!-- resources/js/Pages/Employees/Create.vue -->
<!-- Grouper les champs en sections logiques -->

<!-- Section 1 : Identité -->
<FormField label="Prénom" :error="errors.first_name">
  <InputText v-model="form.first_name" class="w-full" />
</FormField>
<!-- first_name, last_name, email, phone, national_id -->

<!-- Section 2 : Poste -->
<!-- department_id (dropdown), position_id (dropdown filtré par département) -->
<!-- schedule_id (dropdown), site_id (dropdown optionnel) -->
<!-- manager_id (dropdown optionnel — superviseur direct) -->

<!-- Section 3 : Contrat -->
<!-- hire_date, contract_type (cdi/cdd/interimaire), contract_end_date si cdd -->
<!-- salary_base, currency (auto depuis pays de l'entreprise) -->

<!-- Section 4 : Accès -->
<!-- role (employee/manager), manager_role (si manager) -->
<!-- password (optionnel — si vide, email de bienvenue envoyé) -->
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS DÉPLOIEMENT FRONTEND

```
[ ] Dashboard : 4 KPIs affichés correctement avec données réelles
[ ] Dashboard : tableau pointages se rafraîchit toutes les 5 minutes
[ ] Liste employés : pagination + filtres + recherche globale fonctionnent
[ ] Création employé : formulaire validé côté client avant soumission
[ ] RBAC : Manager Dept ne voit pas le bouton "Créer un employé"
[ ] Approbation absence depuis le dashboard fonctionne
[ ] Génération paie : job dispatché, statut polling visible
[ ] RTL arabe : sidebar et tables mirrorés
[ ] Responsive : toutes les pages lisibles sur tablette (768px)
[ ] 0 erreur console JavaScript en production
```

---

## COMMIT

```
feat: add Vue.js dashboard with KPIs, attendance table, and absence approvals
feat: add employees module with DataTable, creation form, and employee profile
feat: add attendance calendar view with daily and monthly modes
feat: add RBAC controls hiding actions based on manager_role
feat: add payroll generation with async job status polling
feat: add shared components (DataTable, StatusBadge, PageHeader, EmptyState)
```
