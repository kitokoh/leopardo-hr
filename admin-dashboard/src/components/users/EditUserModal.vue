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
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <PencilIcon class="h-6 w-6 text-blue-600" />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Modifier l'utilisateur
                </h3>
                <div class="mt-4 space-y-4">
                  <!-- Avatar -->
                  <div class="flex items-center space-x-4">
                    <img 
                      :src="user?.avatar" 
                      :alt="user?.name"
                      class="h-16 w-16 rounded-full"
                    />
                    <div>
                      <button
                        type="button"
                        class="text-sm text-indigo-600 hover:text-indigo-500"
                      >
                        Changer l'avatar
                      </button>
                    </div>
                  </div>

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
                      <option value="">Aucune entreprise</option>
                      <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                      </option>
                    </select>
                  </div>

                  <!-- Status -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Statut
                    </label>
                    <select
                      v-model="form.status"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="active">Actif</option>
                      <option value="inactive">Inactif</option>
                      <option value="suspended">Suspendu</option>
                      <option value="pending">En attente</option>
                    </select>
                  </div>

                  <!-- Segment -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Segment
                    </label>
                    <select
                      v-model="form.segment"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                      <option value="champions">Champions</option>
                      <option value="loyal">Loyaux</option>
                      <option value="potential">Potentiels</option>
                      <option value="new">Nouveaux</option>
                      <option value="at-risk">À risque</option>
                    </select>
                  </div>

                  <!-- Password reset -->
                  <div class="border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between">
                      <label class="block text-sm font-medium text-gray-700">
                        Mot de passe
                      </label>
                      <button
                        type="button"
                        @click="resetPassword"
                        class="text-sm text-indigo-600 hover:text-indigo-500"
                      >
                        Réinitialiser
                      </button>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                      Dernière modification: {{ formatDate(user?.passwordUpdatedAt || user?.createdAt) }}
                    </p>
                  </div>

                  <!-- Account actions -->
                  <div class="border-t border-gray-200 pt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Actions du compte
                    </label>
                    <div class="space-y-2">
                      <button
                        type="button"
                        @click="sendWelcomeEmail"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md border border-gray-300"
                      >
                        Renvoyer l'email de bienvenue
                      </button>
                      <button
                        type="button"
                        @click="forceLogout"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md border border-gray-300"
                      >
                        Forcer la déconnexion
                      </button>
                    </div>
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
                Mise à jour...
              </span>
              <span v-else>Mettre à jour</span>
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
import { ref, reactive, onMounted, watch } from 'vue'
import { PencilIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'

const props = defineProps({
  user: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'updated'])
const toast = useToast()

const isLoading = ref(false)
const companies = ref([])

// Form data
const form = reactive({
  name: '',
  email: '',
  role: '',
  companyId: '',
  status: '',
  segment: ''
})

onMounted(async () => {
  await loadCompanies()
  populateForm()
})

watch(() => props.user, () => {
  populateForm()
}, { deep: true })

// Methods
async function loadCompanies() {
  companies.value = [
    { id: 1, name: 'Acme Corp' },
    { id: 2, name: 'TechStart Inc' },
    { id: 3, name: 'Global Solutions' },
    { id: 4, name: 'Innovation Labs' },
    { id: 5, name: 'Digital Dynamics' }
  ]
}

function populateForm() {
  if (props.user) {
    form.name = props.user.name
    form.email = props.user.email
    form.role = props.user.role
    form.companyId = props.user.company?.id || ''
    form.status = props.user.status
    form.segment = props.user.segment
  }
}

async function handleSubmit() {
  isLoading.value = true
  
  try {
    // Validate form
    if (!form.name || !form.email || !form.role) {
      toast.error('Veuillez remplir tous les champs obligatoires')
      return
    }
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500))
    
    // Create updated user object
    const updatedUser = {
      ...props.user,
      name: form.name,
      email: form.email,
      role: form.role,
      status: form.status,
      segment: form.segment,
      company: companies.value.find(c => c.id === parseInt(form.companyId)) || null,
      updatedAt: new Date()
    }
    
    toast.success('Utilisateur mis à jour avec succès')
    emit('updated', updatedUser)
    
  } catch (error) {
    console.error('Failed to update user:', error)
    toast.error('Erreur lors de la mise à jour de l\'utilisateur')
  } finally {
    isLoading.value = false
  }
}

async function resetPassword() {
  try {
    await new Promise(resolve => setTimeout(resolve, 1000))
    toast.success('Mot de passe réinitialisé • Email envoyé à l\'utilisateur')
  } catch (error) {
    toast.error('Erreur lors de la réinitialisation du mot de passe')
  }
}

async function sendWelcomeEmail() {
  try {
    await new Promise(resolve => setTimeout(resolve, 1000))
    toast.success('Email de bienvenue envoyé')
  } catch (error) {
    toast.error('Erreur lors de l\'envoi de l\'email')
  }
}

async function forceLogout() {
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    toast.success('Utilisateur déconnecté de toutes ses sessions')
  } catch (error) {
    toast.error('Erreur lors de la déconnexion forcée')
  }
}

function formatDate(date) {
  if (!date) return 'Jamais'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>