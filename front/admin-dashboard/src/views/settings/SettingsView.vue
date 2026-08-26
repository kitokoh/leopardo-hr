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

    <!-- 2FA -->
    <div class="card animate-slide-up" style="animation-delay: 0.1s">
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

    <!-- Coordonnées bancaires (SEPA, issue #5613) -->
    <div v-if="canManageBankDetails" class="card animate-slide-up" style="animation-delay: 0.15s">
      <div class="card-header">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('settingsPage.bankTitle') }}</h2>
        <p class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('settingsPage.bankSubtitle') }}</p>
      </div>
      <div class="card-body space-y-5">
        <div v-if="!bankDetailsLoaded" class="text-sm text-slate-400">…</div>
        <template v-else>
          <div
            v-if="!bankDetails.iban"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-300"
            role="alert"
          >
            {{ $t('settingsPage.bankMissingWarning') }}
          </div>

          <form class="flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="submitBankDetails">
            <div class="flex-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="company-iban">{{ $t('settingsPage.bankIbanLabel') }}</label>
              <input
                id="company-iban"
                v-model="bankDetails.iban"
                type="text"
                class="form-input font-mono"
                :placeholder="$t('settingsPage.bankIbanPlaceholder')"
                maxlength="34"
              >
              <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $t('settingsPage.bankDzHint') }}</p>
            </div>
            <div class="flex-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5" for="company-bic">{{ $t('settingsPage.bankBicLabel') }}</label>
              <input
                id="company-bic"
                v-model="bankDetails.bic"
                type="text"
                class="form-input font-mono"
                :placeholder="$t('settingsPage.bankBicPlaceholder')"
                maxlength="11"
              >
            </div>
            <button type="submit" class="btn-primary shrink-0" :disabled="isSavingBank">
              <ArrowPathIcon v-if="isSavingBank" class="mr-2 h-4 w-4 animate-spin" />
              {{ isSavingBank ? $t('settingsPage.bankSaving') : $t('settingsPage.bankSave') }}
            </button>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

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

// Issue #5613 — coordonnées bancaires entreprise (débiteur SEPA).
const canManageBankDetails = computed(() =>
  ['principal', 'rh'].includes(authStore.user?.manager_role)
)
const bankDetails = reactive({ iban: '', bic: '' })
const bankDetailsLoaded = ref(false)
const isSavingBank = ref(false)

async function loadBankDetails() {
  bankDetailsLoaded.value = false
  try {
    const { data } = await api.get('/company/bank-details')
    bankDetails.iban = data?.data?.iban || ''
    bankDetails.bic = data?.data?.bic || ''
  } catch {
    // Lecture non bloquante : la section reste éditable, le warning s'affiche.
  } finally {
    bankDetailsLoaded.value = true
  }
}

async function submitBankDetails() {
  isSavingBank.value = true
  try {
    await api.patch('/company/bank-details', {
      company_iban: bankDetails.iban.trim() || null,
      company_bic: bankDetails.bic.trim() || null,
    })
    toast.success(t('settingsPage.bankSaved'))
    await loadBankDetails()
  } catch (e) {
    const errors = e?.response?.data?.errors
    const first = errors ? Object.values(errors).flat()[0] : null
    toast.error(first || t('settingsPage.bankSaveError'))
  } finally {
    isSavingBank.value = false
  }
}

onMounted(() => {
  if (canManageBankDetails.value) {
    void loadBankDetails()
  }
})
</script>

<style scoped>
@reference '../../style.css';
.form-input {
  @apply block w-full rounded-2xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-800 dark:bg-slate-950/50 dark:text-white backdrop-blur-sm placeholder:text-slate-400 font-medium;
}
</style>

