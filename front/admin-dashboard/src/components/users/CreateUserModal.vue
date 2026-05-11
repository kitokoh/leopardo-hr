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
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                <UserPlusIcon class="h-6 w-6 text-indigo-600" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Créer un nouvel utilisateur
                </h3>
                <div class="mt-4 space-y-4">
                  <!-- Name -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Nom complet *
                    </label>
                    <input
                      v-model="form.name"
                      type="text"
                      required
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Jean Dupont"
                    />
                  </div>

                  <!-- Email -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Adresse email *
                    </label>
                    <input
                      v-model="form.email"
                      type="email"
                      required
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="jean.dupont@example.com"
                    />
                  </div>

                  <!-- Role -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Rôle *
                    </label>
                    <select
                      v-model="form.role"
                      required
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="">Sélectionner un rôle</option>
                      <option value="admin">Administrateur</option>
                      <option value="manager">Manager</option>
                      <option value="employee">Employé</option>
                      <option value="hr">RH</option>
                    </select>
                  </div>

                  <!-- Company -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Entreprise
                    </label>
                    <select
                      v-model="form.companyId"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="">Sélectionner une entreprise</option>
                      <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                      </option>
                    </select>
                  </div>

                  <!-- Status -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Statut initial
                    </label>
                    <select
                      v-model="form.status"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="active">Actif</option>
                      <option value="pending">En attente</option>
                      <option value="inactive">Inactif</option>
                    </select>
                  </div>

                  <!-- Send invitation -->
                  <div class="flex items-center">
                    <input
                      v-model="form.sendInvitation"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label class="ml-2 block text-sm text-gray-700">
                      Envoyer un email d'invitation
                    </label>
                  </div>

                  <!-- Generate password -->
                  <div class="flex items-center">
                    <input
                      v-model="form.generatePassword"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label class="ml-2 block text-sm text-gray-700">
                      Générer un mot de passe temporaire
                    </label>
                  </div>

                  <!-- Custom password -->
                  <div v-if="!form.generatePassword">
                    <label class="block text-sm font-medium text-gray-700">
                      Mot de passe {{ form.generatePassword ? '' : '*' }}
                    </label>
                    <input
                      v-model="form.password"
                      type="password"
                      :required="!form.generatePassword"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                      placeholder="Mot de passe sécurisé"
                    />
                  </div>
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
import { ref, reactive, onMounted } from 'vue'
import { UserPlusIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

const emit = defineEmits(['close', 'created'])
const toast = useToast()

const isLoading = ref(false)
const companies = ref([])

// Form data
const form = reactive({
  name: '',
  email: '',
  role: '',
  companyId: '',
  status: 'active',
  password: '',
  sendInvitation: true,
  generatePassword: true
})

onMounted(async () => {
  await loadCompanies()
})

// Methods
async function loadCompanies() {
  // Mock companies data
  companies.value = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'TechStart Inc' },
    { id: 3, name: 'Global Solutions' },
    { id: 4, name: 'Innovation Labs' },
    { id: 5, name: 'Digital Dynamics' }
  ]
}

async function handleSubmit() {
  isLoading.value = true

  try {
    // Validate form
    if (!form.name || !form.email || !form.role) {
      toast.error('Veuillez remplir tous les champs obligatoires')
      return
    }

    if (!form.generatePassword && !form.password) {
      toast.error('Veuillez saisir un mot de passe ou activer la génération automatique')
      return
    }

    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 2000))

    // Create user object
    const newUser = {
      id: Date.now(),
      name: form.name,
      email: form.email,
      role: form.role,
      status: form.status,
      company: companies.value.find(c => c.id === parseInt(form.companyId)),
      createdAt: new Date(),
      lastLoginAt: null,
      avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(form.name)}&background=random`
    }

    // Success feedback
    let message = 'Utilisateur créé avec succès'
    if (form.sendInvitation) {
      message += ' • Email d\'invitation envoyé'
    }
    if (form.generatePassword) {
      message += ' • Mot de passe temporaire généré'
    }

    toast.success(message)
    emit('created', newUser)

  } catch (error) {
    console.error('Failed to create user:', error)
    toast.error('Erreur lors de la création de l\'utilisateur')
  } finally {
    isLoading.value = false
  }
}
</script>