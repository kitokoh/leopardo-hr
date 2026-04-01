# PATCH FRONTEND — Onboarding Wizard Vue.js + Bannière grâce + User Flows
# À appliquer pendant CU-01
# Réf : 24_ONBOARDING_GUIDE.md + 13_USER_FLOWS_VALIDES.md + 13_CHECK_SUBSCRIPTION_SPEC.md

---

## PATCH 1 — OnboardingWizard.vue (composant modal)

Ce composant s'affiche automatiquement si `$page.props.onboarding !== null`.
Il bloque l'interface jusqu'à ce que les étapes obligatoires (1 et 2) soient terminées.

```vue
<!-- resources/js/Components/Onboarding/OnboardingWizard.vue -->

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page     = usePage();
const onboarding = computed(() => page.props.onboarding);
const isVisible  = computed(() => onboarding.value && !onboarding.value.completed);

const isLoading = ref(false);

const currentStep = computed(() =>
  onboarding.value?.steps?.find(s => !s.completed) ?? onboarding.value?.steps?.at(-1)
);

async function markStepDone(stepId) {
  isLoading.value = true;
  await router.post('/onboarding/complete-step', { step: stepId }, {
    preserveScroll: true,
    onFinish: () => { isLoading.value = false; },
  });
}

async function skip() {
  await router.post('/onboarding/skip', {}, { preserveScroll: true });
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="isVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-8">

          <!-- Header -->
          <div class="flex items-start justify-between mb-6">
            <div>
              <h2 class="text-2xl font-bold text-gray-900">Bienvenue sur Leopardo RH 🐆</h2>
              <p class="text-gray-500 mt-1">Configurez votre espace en quelques étapes</p>
            </div>
            <span v-if="onboarding.trial_days_remaining > 0"
                  class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-sm font-medium">
              Trial : {{ onboarding.trial_days_remaining }}j restants
            </span>
          </div>

          <!-- Barre de progression -->
          <div class="flex gap-2 mb-8">
            <div v-for="step in onboarding.steps" :key="step.id"
                 class="flex-1 h-2 rounded-full transition-all duration-300"
                 :class="step.completed ? 'bg-emerald-500' : 'bg-gray-200'" />
          </div>

          <!-- Contenu de l'étape courante -->
          <div v-if="currentStep" class="min-h-48">
            <h3 class="text-lg font-semibold mb-2">
              Étape {{ currentStep.id }} : {{ currentStep.title }}
              <span v-if="!currentStep.required" class="text-sm text-gray-400 font-normal">(optionnel)</span>
            </h3>

            <!-- Étape 1 : Ajouter des employés -->
            <template v-if="currentStep.id === 1">
              <p class="text-gray-600 mb-4">
                Commencez par ajouter vos premiers employés. Vous pouvez les ajouter un par un
                ou importer un fichier CSV.
              </p>
              <div class="flex gap-3">
                <a :href="route('employees.create')"
                   class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                  Ajouter un employé
                </a>
                <a :href="route('employees.import')"
                   class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                  Importer un CSV
                </a>
              </div>
            </template>

            <!-- Étape 2 : Configurer le planning -->
            <template v-else-if="currentStep.id === 2">
              <p class="text-gray-600 mb-4">
                Définissez les horaires de travail de vos équipes.
                Les employés seront notifiés selon ces horaires.
              </p>
              <a :href="route('settings.schedules')"
                 class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                Configurer les plannings
              </a>
            </template>

            <!-- Étape 3 : Télécharger l'app (optionnel) -->
            <template v-else-if="currentStep.id === 3">
              <p class="text-gray-600 mb-4">
                Partagez ce lien avec vos employés pour qu'ils téléchargent l'application mobile.
              </p>
              <!-- QR Code généré dynamiquement -->
              <img src="/qr-download-app.png" alt="QR Code téléchargement" class="w-40 h-40 mx-auto">
              <p class="text-center text-sm text-gray-500 mt-2">leopardo-rh.com/download</p>
            </template>

            <!-- Étape 4 : Premier pointage (optionnel) -->
            <template v-else-if="currentStep.id === 4">
              <p class="text-gray-600">
                Demandez à un employé de faire son premier pointage depuis l'app mobile.
                Vous verrez son arrivée apparaître en temps réel sur le dashboard.
              </p>
            </template>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-100">
            <button @click="skip" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
              Passer (configurer plus tard)
            </button>
            <button v-if="currentStep?.completed"
                    @click="markStepDone(currentStep.id)"
                    :disabled="isLoading"
                    class="px-6 py-2 bg-amber-500 text-white rounded-lg font-medium
                           hover:bg-amber-600 disabled:opacity-50 transition-colors">
              {{ isLastRequiredStep ? 'Terminer ✓' : 'Étape suivante →' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
```

### Intégrer dans AppLayout.vue

```vue
<!-- resources/js/Layouts/AppLayout.vue -->
<template>
  <div>
    <OnboardingWizard />         <!-- Toujours présent, s'affiche si nécessaire -->
    <SubscriptionGraceBanner />  <!-- Toujours présent, s'affiche si nécessaire -->
    <Navbar />
    <Sidebar />
    <main>
      <slot />
    </main>
  </div>
</template>
```

---

## PATCH 2 — SubscriptionGraceBanner.vue

```vue
<!-- resources/js/Components/UI/SubscriptionGraceBanner.vue -->

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page        = usePage();
const graceHeader = computed(() => page.props.subscriptionGrace);
// subscriptionGrace est injecté par HandleInertiaRequests.php
// si l'API retourne le header X-Subscription-Grace
</script>

<template>
  <div v-if="graceHeader"
       class="bg-orange-50 border-b border-orange-200 px-4 py-2 flex items-center gap-3">
    <span class="text-orange-600">⚠️</span>
    <span class="text-sm text-orange-800 flex-1">
      Abonnement expiré — accès en lecture seule pendant encore
      <strong>{{ graceHeader.daysLeft }} jour(s)</strong>.
      Les modifications sont temporairement désactivées.
    </span>
    <a href="https://leopardo-rh.com/renew" target="_blank"
       class="text-sm font-semibold text-orange-900 hover:underline">
      Renouveler →
    </a>
  </div>
</template>
```

### HandleInertiaRequests — injecter le grace period dans les props

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => ['user' => $request->user()],
        'onboarding' => fn() => $this->getOnboardingData($request),

        // Injecter l'état d'abonnement pour la bannière
        'subscriptionGrace' => function () use ($request) {
            $company = app('current_company');
            if (!$company) return null;

            $diff = now()->diffInDays($company->subscription_end, false);
            if ($diff >= 0) return null;  // Pas expiré

            $graceDaysLeft = max(0, 3 + $diff);
            if ($graceDaysLeft <= 0) return null;  // Hors période de grâce

            return [
                'isActive'  => true,
                'daysLeft'  => $graceDaysLeft,
                'expiresAt' => $company->subscription_end->toDateString(),
            ];
        },
    ]);
}
```

---

## PATCH 3 — Référence User Flows pour Cursor

Réf : `docs/dossierdeConception/11_ux_wireframes/13_USER_FLOWS_VALIDES.md`

Cursor DOIT lire ce document avant de coder les pages de workflow.
Voici les flux critiques à implémenter correctement dans Vue.js :

### Flux 1 — Création d'un employé

```
Manager (principal ou RH) → /employees/create
→ Formulaire 4 sections (Identité, Poste, Contrat, Accès)
→ Validation côté client avant soumission (email unique, dates cohérentes)
→ POST /employees
→ Succès → redirection /employees/{id} + toast "Employé créé"
→ Erreur → afficher erreurs inline dans les champs concernés
→ Email de bienvenue envoyé automatiquement (backend)
```

### Flux 2 — Approbation d'une absence depuis le dashboard

```
Manager → Dashboard → Carte "X absences en attente"
→ Clic → modal de détail (sans navigation)
→ Boutons : Approuver | Refuser (avec champ motif obligatoire si Refuser)
→ PUT /absences/{id}/approve ou /reject
→ Fermeture modal + mise à jour du compteur dashboard sans rechargement complet
→ Notification push envoyée automatiquement à l'employé (backend)
```

### Flux 3 — Génération de la paie

```
Manager Comptable → /payroll/generate
→ Sélection mois/année
→ Aperçu : nombre d'employés, masse salariale estimée
→ Bouton "Générer les bulletins"
→ POST /payroll/validate → retourne job_id
→ Page de statut avec progression (polling GET /payroll/status/{job_id} toutes les 3s)
→ Succès → redirection /payroll + toast "X bulletins générés"
→ Employés notifiés automatiquement (backend)
```

### Flux 4 — Correction manuelle d'un pointage

```
Manager → /attendance → Icône "Modifier" sur une ligne
→ Modal avec champs heure_arrivée et heure_départ
→ Validation : heure_départ > heure_arrivée
→ PUT /attendance/{id}
→ Mise à jour de la ligne sans rechargement
→ Audit log créé automatiquement (backend)
```

### Implémentation correcte — modal sans navigation (Inertia)

```vue
<!-- Pattern pour les modals inline (sans changer de page) -->
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ approved_at: null });

function approve(absenceId) {
  form.put(route('absences.approve', absenceId), {
    preserveScroll: true,  // Ne pas scroller en haut
    onSuccess: () => {
      showModal.value = false;
      // Les props Inertia sont automatiquement rechargées
    },
  });
}
</script>
```

---

## CHECKLIST FRONTEND COMPLÈTE

```
Onboarding :
[ ] OnboardingWizard.vue s'affiche si $page.props.onboarding !== null
[ ] L'étape 1 se marque complète après création d'un employé
[ ] L'étape 2 se marque complète après création d'un planning
[ ] Bouton "Passer" disponible après étape 2 terminée
[ ] Le wizard disparaît définitivement après skip ou completion

Abonnement :
[ ] SubscriptionGraceBanner s'affiche si subscriptionGrace dans les props
[ ] Les boutons d'action sont désactivés en mode lecture seule (période de grâce)
[ ] Page 403 dédiée si subscription expired (pas juste un toast)

Workflows :
[ ] Approbation absence depuis dashboard (modal, sans navigation)
[ ] Génération paie avec polling de statut
[ ] Correction pointage avec validation heure_départ > heure_arrivée
[ ] Toutes les erreurs API affichées inline dans les champs (pas juste un toast)
```
