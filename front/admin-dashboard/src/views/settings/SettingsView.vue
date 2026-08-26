<template>
  <div class="space-y-8 animate-fade-in max-w-3xl">
    <div>
      <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $t('settingsPage.title') }}</h1>
      <p class="mt-1 text-slate-500 dark:text-slate-400 font-medium text-lg">
        {{ $t('settingsPage.subtitle') }}
      </p>
    </div>

    <!-- Profile -->
    <div class="card animate-slide-up">
      <div class="card-header">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('settingsPage.profileTitle') }}</h2>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('settingsPage.profileSubtitle') }}</p>
      </div>
      <form class="card-body space-y-5" @submit.prevent="submitProfile">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="profile-name">{{ $t('settingsPage.fullName') }}</label>
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
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="profile-email">{{ $t('settingsPage.email') }}</label>
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
            {{ $t('settingsPage.saveChanges') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Password -->
    <div class="card animate-slide-up" style="animation-delay: 0.05s">
      <div class="card-header">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('settingsPage.passwordTitle') }}</h2>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
          {{ $t('settingsPage.passwordSubtitle') }}
        </p>
      </div>
      <form class="card-body space-y-5" @submit.prevent="submitPassword">
        <div>
          <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="current-password">{{ $t('settingsPage.currentPassword') }}</label>
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
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="new-password">{{ $t('settingsPage.newPassword') }}</label>
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
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="new-password-confirm">{{ $t('settingsPage.confirmPassword') }}</label>
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
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $t('settingsPage.minLengthHint') }}</p>
        <div class="flex justify-end">
          <button type="submit" class="btn-primary" :disabled="isSavingPassword">
            <ArrowPathIcon v-if="isSavingPassword" class="mr-2 h-4 w-4 animate-spin" />
            {{ $t('settingsPage.updatePassword') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Coordonnées bancaires SEPA (#5613) -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s">
      <div class="card-header flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ t('settingsPage.bankingTitle', 'Coordonnées Bancaires SEPA') }}</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ t('settingsPage.bankingSubtitle', 'IBAN et BIC de votre entreprise requis pour l\'export SEPA des virements.') }}
          </p>
        </div>
        <span
          :class="[
            'px-3 py-1 text-[11px] font-black uppercase tracking-widest rounded-lg border shrink-0',
            bankingData.sepa_ready
              ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800'
              : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800'
          ]"
        >
          {{ bankingData.sepa_ready ? t('settingsPage.sepaReady', 'SEPA prêt') : t('settingsPage.sepaNotReady', 'SEPA non configuré') }}
        </span>
      </div>

      <div class="card-body space-y-5">
        <!-- Alerte IBAN manquant -->
        <div
          v-if="!bankingData.sepa_ready && !isBankingLoading"
          class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/30 dark:bg-amber-950/20"
        >
          <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
          <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
            {{ t('settingsPage.bankingWarning', 'L\'export SEPA retournera une erreur 422 tant que l\'IBAN de l\'entreprise n\'est pas configuré.') }}
          </p>
        </div>

        <div v-if="isBankingLoading" class="flex h-16 items-center justify-center">
          <div class="h-6 w-6 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>
        </div>

        <form v-else class="space-y-5" @submit.prevent="submitBanking">
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="company-iban">
              {{ t('settingsPage.companyIban', 'IBAN de l\'entreprise') }}
            </label>
            <input
              id="company-iban"
              v-model="bankingForm.company_iban"
              type="text"
              class="form-input font-mono tracking-widest"
              maxlength="50"
              autocomplete="off"
              :placeholder="bankingData.country === 'DZ' ? t('settingsPage.ibanPlaceholderDZ', 'IBAN ou RIB algérien (20 chiffres)') : 'FR76 3000 4000 0100 0000 0000 000'"
            >
            <p class="mt-1.5 text-xs font-medium text-slate-400">
              {{ t('settingsPage.ibanHint', 'Format IBAN ISO 13616 (majuscules, sans espaces). Les espaces sont ignorés automatiquement.') }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="company-bic">
              {{ t('settingsPage.companyBic', 'BIC / SWIFT (optionnel)') }}
            </label>
            <input
              id="company-bic"
              v-model="bankingForm.company_bic"
              type="text"
              class="form-input font-mono tracking-widest uppercase"
              maxlength="11"
              autocomplete="off"
              placeholder="BNPAFRPP"
            >
          </div>
          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="isSavingBanking">
              <ArrowPathIcon v-if="isSavingBanking" class="mr-2 h-4 w-4 animate-spin" />
              {{ t('settingsPage.saveBanking', 'Enregistrer les coordonnées') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 2FA -->
    <div class="card animate-slide-up" style="animation-delay: 0.15s">
      <div class="card-header flex items-center justify-between">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('settingsPage.twoFactorTitle') }}</h2>
          <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ $t('settingsPage.twoFactorSubtitle') }}
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
          {{ authStore.user?.two_fa_enabled ? $t('settingsPage.enabled') : $t('settingsPage.disabled') }}
        </span>
      </div>

      <div class="card-body space-y-6">
        <!-- Disable flow -->
        <div v-if="authStore.user?.two_fa_enabled" class="space-y-4">
          <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
            {{ $t('settingsPage.twoFactorActiveHint') }}
          </p>
          <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitDisable2fa">
            <div class="flex-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="disable-2fa-password">{{ $t('settingsPage.password') }}</label>
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
              {{ $t('settingsPage.disable2fa') }}
            </button>
          </form>
        </div>

        <!-- Enable flow -->
        <div v-else class="space-y-5">
          <div v-if="!pendingSecret">
            <p class="text-sm font-medium text-slate-600 dark:text-slate-300 mb-4">
              {{ $t('settingsPage.generateSecretHint') }}
            </p>
            <button class="btn-primary" :disabled="isGeneratingSecret" @click="generateSecret">
              <ArrowPathIcon v-if="isGeneratingSecret" class="mr-2 h-4 w-4 animate-spin" />
              {{ $t('settingsPage.generateSecret') }}
            </button>
          </div>

          <div v-else class="space-y-4">
            <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800/60 bg-slate-50/50 dark:bg-slate-900/40 p-5">
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">{{ $t('settingsPage.scanStep') }}</p>
              <p class="break-all text-xs font-mono text-slate-500 dark:text-slate-400 mb-3">{{ pendingSecret.qr_code_url }}</p>
              <p class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('settingsPage.manualSecret') }} <span class="font-mono text-brand-600 dark:text-brand-400">{{ pendingSecret.secret }}</span></p>
            </div>
            <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitEnable2fa">
              <div class="flex-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="enable-2fa-code">{{ $t('settingsPage.enterCodeStep') }}</label>
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
                  {{ $t('settingsPage.enable2fa') }}
                </button>
                <button type="button" class="btn-secondary" @click="pendingSecret = null">{{ $t('settingsPage.cancel') }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { ArrowPathIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import api from '@/services/api.js'

const authStore = useAuthStore()
const toast = useToast()
const localeStore = useLocaleStore()

function t(key, fallback = '') {
  return translate(localeStore.current, key, fallback)
}

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

// ---- Coordonnées bancaires SEPA (#5613) ----
const isBankingLoading = ref(true)
const isSavingBanking = ref(false)
const bankingData = reactive({ company_iban: null, company_bic: null, sepa_ready: false, country: null })
const bankingForm = reactive({ company_iban: '', company_bic: '' })

async function loadBanking() {
  isBankingLoading.value = true
  try {
    const res = await api.get('/company/banking')
    const data = res.data?.data || {}
    bankingData.company_iban = data.company_iban ?? null
    bankingData.company_bic = data.company_bic ?? null
    bankingData.sepa_ready = !!data.sepa_ready
    bankingData.country = data.country ?? null
    bankingForm.company_iban = data.company_iban ?? ''
    bankingForm.company_bic = data.company_bic ?? ''
  } catch (err) {
    // Endpoint optionnel — ne pas bloquer la page si indisponible
    console.warn('[settings] banking load failed', err)
  } finally {
    isBankingLoading.value = false
  }
}

async function submitBanking() {
  isSavingBanking.value = true
  try {
    const res = await api.patch('/company/banking', {
      company_iban: bankingForm.company_iban.trim().toUpperCase().replace(/\s/g, '') || null,
      company_bic: bankingForm.company_bic.trim().toUpperCase() || null,
    })
    const data = res.data?.data || {}
    bankingData.company_iban = data.company_iban ?? null
    bankingData.company_bic = data.company_bic ?? null
    bankingData.sepa_ready = !!data.sepa_ready
    toast.success(t('settingsPage.bankingUpdated', 'Coordonnées bancaires enregistrées.'))
  } catch (err) {
    const msg = err?.response?.data?.message || err?.response?.data?.errors?.company_iban?.[0] || t('settingsPage.bankingError', 'Erreur lors de l\'enregistrement des coordonnées bancaires.')
    toast.error(msg)
  } finally {
    isSavingBanking.value = false
  }
}

onMounted(() => {
  loadBanking()
})

async function submitProfile() {
  isSavingProfile.value = true
  try {
    const result = await authStore.updateProfile({
      name: profileForm.name,
      email: profileForm.email
    })

    if (result.success) {
      toast.success(t('settingsPage.profileUpdated'))
    } else {
      toast.error(result.message)
    }
  } finally {
    isSavingProfile.value = false
  }
}

async function submitPassword() {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.error(t('settingsPage.passwordsMismatch'))
    return
  }

  isSavingPassword.value = true
  try {
    const result = await authStore.changePassword({ ...passwordForm })

    if (result.success) {
      toast.success(t('settingsPage.passwordUpdated'))
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
      toast.success(t('settingsPage.twoFactorEnabled'))
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
      toast.success(t('settingsPage.twoFactorDisabled'))
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
  @apply block w-full rounded-2xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
</style>

