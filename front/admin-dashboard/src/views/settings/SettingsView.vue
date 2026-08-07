<template>
  <div class="space-y-8 animate-fade-in max-w-3xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Mon compte</h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        GÃ©rez vos informations, votre mot de passe et la sÃ©curitÃ© de votre compte super-administrateur.
      </p>
    </div>

    <!-- Profile -->
    <div class="card animate-slide-up">
      <div class="card-header">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Informations du profil</h2>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">Nom et adresse email utilisÃ©s pour vous connecter.</p>
      </div>
      <form class="card-body space-y-5" @submit.prevent="submitProfile">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="profile-name">Nom complet</label>
          <input
            id="profile-name"
            v-model="profileForm.name"
            type="text"
            class="form-input"
            maxlength="100"
            required
          >
        </div>
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="profile-email">Adresse email</label>
          <input
            id="profile-email"
            v-model="profileForm.email"
            type="email"
            class="form-input"
            maxlength="150"
            required
          >
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="isSavingProfile">
            <ArrowPathIcon v-if="isSavingProfile" class="mr-2 h-4 w-4 animate-spin" />
            Enregistrer les modifications
          </button>
        </div>
      </form>
    </div>

    <!-- Password -->
    <div class="card animate-slide-up" style="animation-delay: 0.05s">
      <div class="card-header">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Mot de passe</h2>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
          Changer votre mot de passe dÃ©connectera automatiquement toutes vos autres sessions actives.
        </p>
      </div>
      <form class="card-body space-y-5" @submit.prevent="submitPassword">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="current-password">Mot de passe actuel</label>
          <input
            id="current-password"
            v-model="passwordForm.current_password"
            type="password"
            class="form-input"
            autocomplete="current-password"
            required
          >
        </div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="new-password">Nouveau mot de passe</label>
            <input
              id="new-password"
              v-model="passwordForm.new_password"
              type="password"
              class="form-input"
              autocomplete="new-password"
              minlength="8"
              required
            >
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="new-password-confirm">Confirmer le mot de passe</label>
            <input
              id="new-password-confirm"
              v-model="passwordForm.new_password_confirmation"
              type="password"
              class="form-input"
              autocomplete="new-password"
              minlength="8"
              required
            >
          </div>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Minimum 8 caractÃ¨res.</p>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="isSavingPassword">
            <ArrowPathIcon v-if="isSavingPassword" class="mr-2 h-4 w-4 animate-spin" />
            Mettre Ã  jour le mot de passe
          </button>
        </div>
      </form>
    </div>

    <!-- 2FA -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="card-header flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Authentification Ã  deux facteurs (2FA)</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
            Ajoutez une couche de sÃ©curitÃ© supplÃ©mentaire Ã  votre compte de super-administrateur.
          </p>
        </div>
        <span
          :class="[
            'px-3 py-1 text-[11px] font-black uppercase tracking-widest rounded-lg border shrink-0',
            authStore.user?.two_fa_enabled
              ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800'
              : 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
          ]"
        >
          {{ authStore.user?.two_fa_enabled ? 'ActivÃ©' : 'DÃ©sactivÃ©' }}
        </span>
      </div>

      <div class="card-body space-y-6">
        <!-- Disable flow -->
        <div v-if="authStore.user?.two_fa_enabled" class="space-y-4">
          <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
            Le 2FA est actif. Pour le dÃ©sactiver, confirmez votre mot de passe.
          </p>
          <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitDisable2fa">
            <div class="flex-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="disable-2fa-password">Mot de passe</label>
              <input
                id="disable-2fa-password"
                v-model="disableForm.password"
                type="password"
                class="form-input"
                autocomplete="current-password"
                required
              >
            </div>
            <button type="submit" class="btn-secondary text-red-600 dark:text-red-400" :disabled="isDisabling2fa">
              <ArrowPathIcon v-if="isDisabling2fa" class="mr-2 h-4 w-4 animate-spin" />
              DÃ©sactiver le 2FA
            </button>
          </form>
        </div>

        <!-- Enable flow -->
        <div v-else class="space-y-5">
          <div v-if="!pendingSecret">
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300 mb-4">
              GÃ©nÃ©rez un secret et scannez-le avec une application d'authentification (Google Authenticator, Authy, 1Password...).
            </p>
            <button class="btn-primary" :disabled="isGeneratingSecret" @click="generateSecret">
              <ArrowPathIcon v-if="isGeneratingSecret" class="mr-2 h-4 w-4 animate-spin" />
              GÃ©nÃ©rer un secret 2FA
            </button>
          </div>

          <div v-else class="space-y-4">
            <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-slate-50/50 dark:bg-slate-900/40 p-5">
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">1. Scannez ce lien / secret dans votre application 2FA :</p>
              <p class="break-all text-xs font-mono text-slate-500 dark:text-slate-400 mb-3">{{ pendingSecret.qr_code_url }}</p>
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Secret manuel : <span class="font-mono text-brand-600 dark:text-brand-400">{{ pendingSecret.secret }}</span></p>
            </div>
            <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitEnable2fa">
              <div class="flex-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="enable-2fa-code">2. Entrez le code Ã  6 chiffres gÃ©nÃ©rÃ©</label>
                <input
                  id="enable-2fa-code"
                  v-model="enableForm.code"
                  type="text"
                  inputmode="numeric"
                  maxlength="6"
                  class="form-input"
                  required
                >
              </div>
              <div class="flex gap-2">
                <button type="submit" class="btn-primary" :disabled="isEnabling2fa">
                  <ArrowPathIcon v-if="isEnabling2fa" class="mr-2 h-4 w-4 animate-spin" />
                  Activer le 2FA
                </button>
                <button type="button" class="btn-secondary" @click="pendingSecret = null">Annuler</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const toast = useToast()

const profileForm = reactive({
  name: authStore.user?.name || '',
  email: authStore.user?.email || ''
})

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const disableForm = reactive({ password: '' })
const enableForm = reactive({ code: '' })

const isSavingProfile = ref(false)
const isSavingPassword = ref(false)
const isGeneratingSecret = ref(false)
const isEnabling2fa = ref(false)
const isDisabling2fa = ref(false)
const pendingSecret = ref(null)

async function submitProfile() {
  isSavingProfile.value = true
  try {
    const result = await authStore.updateProfile({
      name: profileForm.name,
      email: profileForm.email
    })

    if (result.success) {
      toast.success('Profil mis Ã  jour avec succÃ¨s.')
    } else {
      toast.error(result.message)
    }
  } finally {
    isSavingProfile.value = false
  }
}

async function submitPassword() {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.error('Les mots de passe ne correspondent pas.')
    return
  }

  isSavingPassword.value = true
  try {
    const result = await authStore.changePassword({ ...passwordForm })

    if (result.success) {
      toast.success('Mot de passe mis Ã  jour avec succÃ¨s.')
      passwordForm.current_password = ''
      passwordForm.new_password = ''
      passwordForm.new_password_confirmation = ''
    } else {
      toast.error(result.message)
    }
  } finally {
    isSavingPassword.value = false
  }
}

async function generateSecret() {
  isGeneratingSecret.value = true
  try {
    const result = await authStore.setup2fa()

    if (result.success) {
      pendingSecret.value = result.data
    } else {
      toast.error(result.message)
    }
  } finally {
    isGeneratingSecret.value = false
  }
}

async function submitEnable2fa() {
  isEnabling2fa.value = true
  try {
    const result = await authStore.enable2fa(enableForm.code)

    if (result.success) {
      toast.success('2FA activÃ© avec succÃ¨s.')
      pendingSecret.value = null
      enableForm.code = ''
    } else {
      toast.error(result.message)
    }
  } finally {
    isEnabling2fa.value = false
  }
}

async function submitDisable2fa() {
  isDisabling2fa.value = true
  try {
    const result = await authStore.disable2fa(disableForm.password)

    if (result.success) {
      toast.success('2FA dÃ©sactivÃ©.')
      disableForm.password = ''
    } else {
      toast.error(result.message)
    }
  } finally {
    isDisabling2fa.value = false
  }
}
</script>

<style scoped>
@reference '../../style.css';
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 bg-white/50 dark:bg-slate-900/50 px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
</style>

