<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal panel -->
      <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
        <form novalidate @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                <UserPlusIcon class="h-6 w-6 text-indigo-600" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Créer un nouvel utilisateur
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                  Compte super-admin de la plateforme (API <code class="text-xs">/platform/users</code>).
                </p>
                <div class="mt-4 space-y-4">
                  <!-- Name -->
                  <FormField id="name" label="Nom complet" required :error="fieldErrors.name" v-slot="{ ariaInvalid, describedBy }">
                    <input
                      id="name"
                      v-model="form.name"
                      type="text"
                      required
                      :aria-invalid="ariaInvalid"
                      :aria-describedby="describedBy"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Jean Dupont"
                    />
                  </FormField>

                  <!-- Email -->
                  <FormField id="email" label="Adresse email" required :error="fieldErrors.email" v-slot="{ ariaInvalid, describedBy }">
                    <input
                      id="email"
                      v-model="form.email"
                      type="email"
                      required
                      :aria-invalid="ariaInvalid"
                      :aria-describedby="describedBy"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="jean.dupont@example.com"
                    />
                  </FormField>

                  <!-- Generate password -->
                  <div class="flex items-center">
                    <input
                      id="generatePassword"
                      v-model="form.generatePassword"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="generatePassword" class="ml-2 block text-sm text-gray-700">
                      Générer un mot de passe temporaire
                    </label>
                  </div>

                  <!-- Custom password -->
                  <FormField v-if="!form.generatePassword" id="password" label="Mot de passe (min. 12 caractères)" required :error="fieldErrors.password" v-slot="{ ariaInvalid, describedBy }">
                    <input
                      id="password"
                      v-model="form.password"
                      type="password"
                      :required="!form.generatePassword"
                      minlength="12"
                      :aria-invalid="ariaInvalid"
                      :aria-describedby="describedBy"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Mot de passe sécurisé"
                    />
                  </FormField>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              type="submit"
              :disabled="isLoading"
              class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="isLoading" class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Création...
              </span>
              <span v-else>Créer l'utilisateur</span>
            </button>

            <button
              type="button"
              @click="$emit('close')"
              :disabled="isLoading"
              class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { UserPlusIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import FormField from '@/components/common/FormField.vue'

const emit = defineEmits(['close', 'created'])
const toast = useToast()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

const isLoading = ref(false)
const attempted = ref(false)

// S-6 (#1666) : feedback inline par champ (aria-invalid + aria-describedby).
const fieldErrors = computed(() => {
  if (!attempted.value) return {}
  const errors = {}
  if (!form.name) errors.name = t('users.errors.name_required', 'Le nom complet est requis.')
  if (!form.email) {
    errors.email = t('auth.email_required', "L'adresse email est requise.")
  } else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) {
    errors.email = t('auth.email_invalid', "Le format de l'adresse email est invalide.")
  }
  if (!form.generatePassword && (!form.password || form.password.length < 12)) {
    errors.password = t('users.errors.password_min', 'Le mot de passe doit contenir au moins 12 caractères.')
  }
  return errors
})

// Form data
const form = reactive({
  name: '',
  email: '',
  password: '',
  generatePassword: true
})

async function handleSubmit() {
  attempted.value = true
  if (Object.keys(fieldErrors.value).length > 0) {
    toast.error(t('users.errors.fix_fields', 'Veuillez corriger les champs en rouge'))
    return
  }

  isLoading.value = true

  try {
    // La création réelle est faite par UsersView (POST /platform/users) :
    // la modal émet uniquement le payload — pas de fausse simulation.
    emit('created', {
      name: form.name,
      email: form.email,
      password: form.generatePassword ? '' : form.password
    })
  } finally {
    isLoading.value = false
  }
}
</script>
