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

### GET /onboarding/status
Retourne l'état de progression de l'onboarding.

**Response 200 :**
```json
{
  "data": {
    "completed": false,
    "current_step": 1,
    "steps": [
      { "id": 1, "title": "Ajoutez vos employés", "completed": false, "required": true },
      { "id": 2, "title": "Configurez votre planning", "completed": false, "required": true },
      { "id": 3, "title": "Téléchargez l'app mobile", "completed": false, "required": false },
      { "id": 4, "title": "Premier pointage de test", "completed": false, "required": false }
    ],
    "trial_ends_at": "2026-04-14",
    "trial_days_remaining": 13
  }
}
```

### POST /onboarding/complete-step
Marque une étape comme complétée.

**Request :**
```json
{ "step": 2 }
```

**Response 200 :**
```json
{
  "data": {
    "step": 2,
    "completed": true,
    "all_steps_done": false,
    "next_step": 3
  }
}
```

### POST /onboarding/skip
Passe l'onboarding (optionnel après étape 2 complétée).

---

## LOGIQUE BACKEND

```php
// Étape 1 auto-complétée quand : Employee::count() >= 1
// Étape 2 auto-complétée quand : Schedule::where('is_default', true)->exists()
// Étape 3 auto-complétée quand : EmployeeDevice::count() >= 1 (premier FCM enregistré)
// Étape 4 auto-complétée quand : AttendanceLog::count() >= 1 (premier pointage)

// CompanySettings
onboarding_step_1_done  BOOLEAN DEFAULT false
onboarding_step_2_done  BOOLEAN DEFAULT false
onboarding_step_3_done  BOOLEAN DEFAULT false
onboarding_step_4_done  BOOLEAN DEFAULT false
onboarding_completed    BOOLEAN DEFAULT false   -- true quand étapes 1+2 terminées
onboarding_skipped_at   TIMESTAMPTZ NULL
```

---

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
