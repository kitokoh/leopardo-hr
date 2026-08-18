# ONBOARDING GUIDÉ — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## DESCRIPTION

L'onboarding guidé est le **premier écran que voit un client après inscription**.
Il conditionne directement le taux de conversion Trial → Payant.
Il s'affiche automatiquement si `company_settings.onboarding_completed = false`.

---

## 4 ÉTAPES OBLIGATOIRES

```
ÉTAPE 1 : Ajouter vos premiers employés (CSV ou manuel)
ÉTAPE 2 : Configurer votre planning de travail
ÉTAPE 3 : Télécharger l'app mobile (QR code)
ÉTAPE 4 : Faire un premier pointage de test
```

---

## API — Endpoints onboarding

> **Mise à jour #4929 (2026-08-17) — contrat réel.** L'ancien contrat
> (`GET /onboarding/status`, `POST /onboarding/complete-step`,
> `POST /onboarding/skip`, colonnes `company_settings.onboarding_step_*`)
> **n'a jamais été implémenté** et est retiré de cette documentation. Le
> contrat canonique, consommé par le wizard web (Next.js) et les apps
> mobiles Flutter, est piloté par la table `onboarding_steps` (10 étapes) :

### GET /onboarding-setup/checklist
Retourne la checklist pilotée par la table `onboarding_steps` (seedée au
provisioning, seed paresseux si absente). Shape canonique :
`data{ completed_steps, total_steps, progress_percent, progress, go_live_ready, next_actions, steps }`.

```json
{
  "data": {
    "completed_steps": 0,
    "total_steps": 10,
    "progress_percent": 0,
    "progress": 0,
    "go_live_ready": false,
    "next_actions": [
      { "key": "company_info", "label": "Renseigner les informations entreprise" }
    ],
    "steps": [
      { "step_key": "company_info", "title": "Renseigner les informations entreprise", "order": 1, "required": true, "status": "pending" }
    ]
  }
}
```

### PATCH /onboarding-setup/{stepKey}/complete
Marque une étape comme complétée (manager uniquement). Résilient : si la
société n'a aucune étape seedée, le seed est déclenché avant la résolution
(#4929). Une clé inconnue répond 404.

### PATCH /onboarding-setup/{stepKey}/skip
Saute une étape **optionnelle** (`required=false`). Une étape requise répond
422.

### GET /onboarding/checklist — DÉPRÉCIÉ (lecture seule)
Checklist calculée de « go-live readiness » (8 étapes auto-détectées) :
`company_created, manager_active, employees_added, employees_active,
payroll_ready, geofence_configured, biometrics_ready, kiosk_connected`.
Conservée pour les clients existants ; le contrat canonique est
`/onboarding-setup/checklist`.

---

## LOGIQUE BACKEND

```php
// #4929 : la source de vérité est la table onboarding_steps (10 étapes)
// seedée par SeedDefaultSteps (action canonique) :
//   company_info, first_department, first_employee, first_attendance,
//   invite_manager, configure_schedules, first_report, configure_payroll,
//   install_kiosk, activate_geofence
// Appelée au provisioning (CompanyProvisioningService) + paresseusement par
// GET/PATCH /onboarding-setup/*. Les anciens champs
// company_settings.onboarding_step_{1..4}_done N'EXISTENT PAS (jamais
// migrés) — ne pas les utiliser.

// AUTO-COMPLETION de la checklist calculée (8 étapes, DÉPRÉCIÉE) :
// company_created : company existe
// manager_active  : manager principal actif
// employees_added : Employee::count() >= 2
// employees_active: comptes employés activés
// payroll_ready   : bases de paie renseignées
// geofence_configured / biometrics_ready / kiosk_connected
```

## COMPOSANT VUE.JS — OnboardingWizard.vue

```vue
<template>
  <div v-if="!onboarding.completed" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-2xl font-bold text-gray-900">Bienvenue sur Leopardo RH 🐆</h2>
          <span class="text-sm text-amber-600 font-medium">
            Trial : {{ onboarding.trial_days_remaining }} jours restants
          </span>
        </div>
        <p class="text-gray-500">Configurez votre espace en 4 étapes rapides</p>
      </div>

      <!-- Progress bar -->
      <div class="flex gap-2 mb-8">
        <div v-for="step in onboarding.steps" :key="step.id"
             class="flex-1 h-2 rounded-full transition-colors"
             :class="step.completed ? 'bg-emerald-500' : 'bg-gray-200'" />
      </div>

      <!-- Étape courante -->
      <component :is="currentStepComponent" @completed="markStepDone" />

      <!-- Actions -->
      <div class="flex justify-between mt-8">
        <button @click="skipOnboarding" class="text-sm text-gray-400 hover:text-gray-600">
          Passer (configurer plus tard)
        </button>
        <button v-if="canProceed" @click="nextStep"
                class="px-6 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600">
          {{ isLastStep ? 'Terminer' : 'Étape suivante →' }}
        </button>
      </div>
    </div>
  </div>
</template>
```

---

## DÉCLENCHEMENT

```php
// Middleware ou Inertia shared data :
Inertia::share([
    'onboarding' => function () {
        if (!auth()->check()) return null;
        $settings = CompanySetting::first();
        if ($settings?->onboarding_completed) return null;
        return app(OnboardingService::class)->getStatus();
    }
]);
```

---

## EMAILS AUTOMATIQUES (séquence Trial)

| Déclencheur | Email envoyé |
|-------------|-------------|
| J+0 (inscription) | Bienvenue + lien onboarding |
| J+1 (si étape 1 non faite) | "Ajoutez votre premier employé en 2 min" |
| J+7 (si onboarding incomplet) | "Astuce : configurez le pointage mobile" |
| J+12 | "Votre trial se termine dans 2 jours" |
| J+14 (expiration) | "Votre compte est suspendu — vos données sont conservées 30 jours" |

---

## MODE QUICK START (< 15 employes)

### Detection a l'inscription
```
Question : "Combien d'employes avez-vous ?"
- Moins de 15  -> onboarding.mode = quickstart
- 15 a 50      -> onboarding.mode = standard
- Plus de 50   -> onboarding.mode = enterprise
```

### Parcours quickstart (3 etapes)
```
Etape 1 : Ajouter un employe minimal (prenom, nom, taux journalier/horaire)
Etape 2 : Installer mobile et realiser un pointage test
Etape 3 : Activer le suivi "ce que je dois aujourd'hui"
```

### Regle de fallback
- Si `onboarding.mode = quickstart`, appliquer automatiquement un planning par defaut `08:00-17:00`, `Lun-Sam`.
- Le passage vers onboarding standard reste possible a tout moment depuis Parametres.
