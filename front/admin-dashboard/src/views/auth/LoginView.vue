<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-950 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans text-slate-200">
    <!-- Animated Background -->
    <div class="absolute inset-0 z-0">
      <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-brand-600/20 rounded-full blur-[120px]"></div>
      <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/20 rounded-full blur-[120px]"></div>
      <div class="absolute top-[20%] right-[10%] w-[30%] h-[30%] bg-cyan-500/10 rounded-full blur-[100px]"></div>
    </div>

    <!-- Grid Pattern overlay -->
    <div class="absolute inset-0 z-0 opacity-20" style="background-image: radial-gradient(#14b8a6 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>

    <div class="max-w-md w-full space-y-10 relative z-10">
      <div class="text-center">
        <div class="mx-auto h-24 w-24 flex items-center justify-center rounded-3xl bg-white/5 dark:bg-slate-900/5 backdrop-blur-2xl border border-white/20 shadow-glass overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-brand-500/20 to-emerald-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <span class="text-4xl font-black text-white tracking-tighter relative z-10">LRH</span>
        </div>
        <h1 class="mt-8 text-center text-5xl font-black tracking-tight text-white uppercase italic">
          Leopardo <span class="text-brand-500 not-italic font-black">RH</span>
        </h1>
        <p class="mt-4 text-center text-slate-400 font-bold tracking-[0.15em] uppercase text-xs">
          Platform Administration • v{{ backendVersion || '4.24' }}
        </p>
        <p class="mt-2 text-center text-brand-400 font-black uppercase tracking-widest text-[10px]">
          {{ t('auth.login_subtitle', 'Connectez-vous à votre espace') }}
        </p>
      </div>

      <div class="glass-card p-1 pb-1 overflow-hidden shadow-premium">
        <div class="bg-slate-900/40 backdrop-blur-3xl p-8 rounded-[1.4rem]">
          <form novalidate class="space-y-6" @submit.prevent="handleLogin">
            <div class="space-y-5">
              <FormField
                id="email"
                :label="t('auth.email_label', 'Adresse email')"
                required
                :error="fieldErrors.email"
                v-slot="{ ariaInvalid, describedBy }"
              >
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <EnvelopeIcon class="h-5 w-5 text-slate-500" />
                  </div>
                  <input
                    id="email"
                    v-model="form.email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    autofocus
                    :aria-invalid="ariaInvalid"
                    :aria-describedby="describedBy"
                    class="block w-full rounded-2xl border-0 bg-white/5 dark:bg-slate-900/5 backdrop-blur-xl py-4 pl-12 pr-4 text-white ring-1 ring-inset ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 text-sm font-bold transition-all duration-300 outline-none"
                    :placeholder="t('auth.login_placeholder_email', 'admin@leopardo-rh.com')"
                  />
                </div>
              </FormField>

              <FormField
                id="password"
                :label="t('auth.access_key_label', 'Clé d\'Accès')"
                required
                :error="fieldErrors.password"
                v-slot="{ ariaInvalid, describedBy }"
              >
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <LockClosedIcon class="h-5 w-5 text-slate-500" />
                  </div>
                  <input
                    id="password"
                    v-model="form.password"
                    name="password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                    :aria-invalid="ariaInvalid"
                    :aria-describedby="describedBy"
                    class="block w-full rounded-2xl border-0 bg-white/5 dark:bg-slate-900/5 backdrop-blur-xl py-4 pl-12 pr-12 text-white ring-1 ring-inset ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 text-sm font-bold transition-all duration-300 outline-none"
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 hover:text-white transition-colors"
                    @click="showPassword = !showPassword"
                    :aria-label="showPassword ? t('auth.hide_password', 'Masquer le mot de passe') : t('auth.show_password', 'Afficher le mot de passe')"
                  >
                    <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                    <EyeIcon v-else class="h-5 w-5" />
                  </button>
                </div>
              </FormField>

              <FormField
                v-if="requiresTwoFactor"
                id="two-factor-code"
                :label="t('auth.two_fa_label', 'Code 2FA')"
                required
                :error="fieldErrors.twoFactorCode"
                v-slot="{ ariaInvalid, describedBy }"
              >
                <input
                  id="two-factor-code"
                  v-model="form.twoFactorCode"
                  name="two-factor-code"
                  type="text"
                  inputmode="numeric"
                  required
                  :aria-invalid="ariaInvalid"
                  :aria-describedby="describedBy"
                  class="block w-full rounded-2xl border-0 bg-white/5 dark:bg-slate-900/5 backdrop-blur-xl py-4 px-4 text-white ring-1 ring-inset ring-amber-500/30 focus:ring-2 focus:ring-inset focus:ring-brand-500 text-center text-2xl font-black tracking-[0.5em] transition-all duration-300 outline-none"
                  placeholder="000000"
                />
              </FormField>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <input
                  id="remember-me"
                  v-model="form.remember"
                  name="remember-me"
                  type="checkbox"
                  class="h-4 w-4 rounded border-white/10 bg-white/5 dark:bg-slate-900/5 backdrop-blur-xl text-brand-600 focus:ring-brand-500 transition-all duration-300"
                />
                <label for="remember-me" class="ml-2 block text-[10px] font-black uppercase tracking-widest text-slate-400">
                  {{ t('auth.remember_me', 'Se souvenir de moi') }}
                </label>
              </div>

              <div class="text-xs font-bold">
                <span class="text-xs font-semibold text-slate-500">Support technique : support@leopardo-rh.com</span>
              </div>
            </div>

            <div v-if="error" role="alert" class="rounded-2xl bg-red-500/10 border border-red-500/20 p-4">
              <div class="flex items-center gap-3">
                <ExclamationTriangleIcon class="h-5 w-5 text-red-400 shrink-0" />
                <div class="space-y-1">
                  <h3 class="text-xs font-black uppercase tracking-wider text-red-400">{{ t('auth.connection_error', 'Erreur de connexion') }}</h3>
                  <p class="text-[10px] font-bold text-red-300/80 leading-tight">{{ error }}</p>
                </div>
              </div>
            </div>

            <div class="space-y-3 pt-2">
              <button
                type="submit"
                :disabled="isLoading"
                class="group relative flex w-full justify-center rounded-2xl bg-brand-600 py-4 px-4 text-sm font-black uppercase tracking-widest text-white shadow-lg hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:opacity-50 transition-all duration-300"
              >
                <span v-if="isLoading" class="mr-2">
                  <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </span>
                {{ isLoading ? t('auth.loading', 'Authentification…') : t('auth.login_submit', 'Se connecter') }}
              </button>


            </div>
          </form>
        </div>
      </div>

      <!-- Legal & Info Footer -->
      <div class="flex items-center justify-between px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">
        <span>© 2026 Leopardo Systems</span>
        <div class="flex items-center gap-4">
          <span class="hover:text-slate-400 transition-colors cursor-not-allowed">Sécurité</span>
          <span class="hover:text-slate-400 transition-colors cursor-not-allowed">Support</span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  LockClosedIcon,
  EnvelopeIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  EyeSlashIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'
import api from '@/services/api'
import FormField from '@/components/common/FormField.vue'

const router = useRouter()
const authStore = useAuthStore()
const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

// #4953 (audit 2026-08-17) : le label de version était codé en dur (v4.16).
// Désormais alimenté depuis GET /api/v1/health (champ `version` du backend),
// avec fallback statique si l'API est injoignable (page de login doit rester
// fonctionnelle hors-ligne / API down).
const backendVersion = ref('')

api.get('/health')
  .then((res) => {
    const v = res?.data?.version ?? res?.version
    if (typeof v === 'string' && v.trim() !== '') {
      backendVersion.value = v.replace(/^v/i, '')
    }
  })
  .catch(() => {
    // fallback silencieux : le label statique s'affiche.
  })

const form = reactive({
  email: '',
  password: '',
  twoFactorCode: '',
  remember: false,
})

const isLoading = ref(false)
const error = ref('')
const requiresTwoFactor = ref(false)
const showPassword = ref(false)
const attempted = ref(false)

// S-6 (#1666) : feedback inline par champ (aria-invalid + aria-describedby).
const fieldErrors = computed(() => {
  if (!attempted.value) return {}
  const errors = {}
  if (!form.email) {
    errors.email = t('auth.email_required', "L'adresse email est requise.")
  } else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) {
    errors.email = t('auth.email_invalid', "Le format de l'adresse email est invalide.")
  }
  if (!form.password) {
    errors.password = t('auth.access_key_required', "La clé d'accès est requise.")
  }
  if (requiresTwoFactor.value && !form.twoFactorCode) {
    errors.twoFactorCode = t('auth.two_factor_required', 'Le code 2FA est requis.')
  }
  return errors
})

async function handleLogin() {
  if (isLoading.value) return

  error.value = ''
  attempted.value = true
  if (Object.keys(fieldErrors.value).length > 0) {
    isLoading.value = false
    return
  }
  isLoading.value = true

  try {
    const result = await authStore.login({
      email: form.email,
      password: form.password,
      remember: form.remember,
      two_fa_code: form.twoFactorCode || undefined,
    })

    if (result.success) {
      requiresTwoFactor.value = false
      form.twoFactorCode = ''
      router.push('/')
    } else if (result.requiresTwoFactor) {
      requiresTwoFactor.value = true
      error.value = result.message || t('auth.two_fa_required_msg', 'Un code de vérification est requis.')
    } else {
      error.value = result.message || t('auth.connection_error', 'Erreur de connexion.')
    }
  } catch (err) {
    error.value = t('auth.unexpected_error', 'Une erreur inattendue est survenue.')
    console.error('Login error:', err)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
@reference '../../style.css';
input:focus {
  @apply ring-brand-500 border-brand-500 shadow-[0_0_15px_rgba(20,184,166,0.1)];
}
</style>

