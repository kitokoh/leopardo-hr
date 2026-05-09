<template>
  <div class="min-h-screen flex items-center justify-center bg-zinc-50/50 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background dynamic elements -->
    <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-brand-50 blur-[120px] opacity-60"></div>
    <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] rounded-full bg-indigo-50 blur-[120px] opacity-60"></div>

    <div class="max-w-md w-full space-y-10 relative z-10 animate-fade-in">
      <div class="text-center">
        <div class="mx-auto h-16 w-16 flex items-center justify-center rounded-2xl brand-gradient shadow-2xl shadow-brand-200">
          <span class="text-2xl font-black text-white tracking-tighter">L</span>
        </div>
        <h2 class="mt-8 text-3xl font-extrabold tracking-tight text-zinc-900">
          Leopardo RH
        </h2>
        <p class="mt-2 text-sm font-medium text-zinc-500 uppercase tracking-widest">
          Console d'Administration
        </p>
      </div>

      <div class="card p-8 bg-white/80 backdrop-blur-xl shadow-2xl border-white/40">
        <form class="space-y-6" @submit.prevent="handleLogin">
          <div class="space-y-4">
            <div>
              <label for="email" class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5 px-1">Identifiant Email</label>
              <div class="relative group">
                <EnvelopeIcon class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 group-focus-within:text-brand-500 transition-colors" />
                <input
                  id="email"
                  v-model="form.email"
                  name="email"
                  type="email"
                  autocomplete="email"
                  required
                  class="block w-full pl-11 pr-4 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-medium focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
                  placeholder="nom@leopardo.io"
                />
              </div>
            </div>

            <div>
              <label for="password" class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5 px-1">Mot de passe</label>
              <div class="relative group">
                <LockClosedIcon class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400 group-focus-within:text-brand-500 transition-colors" />
                <input
                  id="password"
                  v-model="form.password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  class="block w-full pl-11 pr-12 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-medium focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex items-center px-4 text-zinc-400 hover:text-brand-600 transition-colors"
                  @click="showPassword = !showPassword"
                >
                  <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                  <EyeIcon v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div v-if="requiresTwoFactor">
              <label for="two-factor-code" class="block text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5 px-1">Code MFA</label>
              <input
                id="two-factor-code"
                v-model="form.twoFactorCode"
                name="two-factor-code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                class="block w-full px-4 py-3 rounded-2xl border-zinc-200 bg-zinc-50/50 text-sm font-bold text-center tracking-[0.5em] focus:bg-white focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all"
                placeholder="000000"
              />
            </div>
          </div>

          <div class="flex items-center justify-between px-1">
            <div class="flex items-center">
              <input
                id="remember-me"
                v-model="form.remember"
                name="remember-me"
                type="checkbox"
                class="h-4 w-4 rounded-lg border-zinc-300 text-brand-600 focus:ring-brand-500 transition-all"
              />
              <label for="remember-me" class="ml-2 block text-xs font-bold text-zinc-600">
                Rester connecté
              </label>
            </div>

            <div class="text-xs">
              <a href="#" class="font-bold text-brand-600 hover:text-brand-500 transition-colors">
                Oubli ?
              </a>
            </div>
          </div>

          <div v-if="error" class="rounded-2xl bg-rose-50 p-4 border border-rose-100 animate-slide-up">
            <div class="flex">
              <ExclamationTriangleIcon class="h-5 w-5 text-rose-500 flex-shrink-0" />
              <div class="ml-3">
                <p class="text-xs font-bold text-rose-800">
                  {{ error }}
                </p>
              </div>
            </div>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="group w-full btn-primary py-3 px-4 rounded-2xl shadow-xl shadow-brand-500/20 active:scale-95 transition-all"
          >
            <span class="mr-2" v-if="isLoading">
              <ArrowPathIcon class="h-4 w-4 animate-spin" />
            </span>
            {{ isLoading ? 'Authentification...' : 'Accéder au Dashboard' }}
          </button>

          <div class="pt-6 border-t border-zinc-100">
            <div class="text-center">
              <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-3">Health Status</p>
              <div class="flex items-center justify-center gap-6">
                <div class="flex items-center gap-1.5">
                  <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                  <span class="text-[10px] font-bold text-zinc-500 uppercase">API</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                  <span class="text-[10px] font-bold text-zinc-500 uppercase">Realtime</span>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>

      <p class="text-center text-[10px] font-medium text-zinc-400 uppercase tracking-widest">
        &copy; 2026 Leopardo Technologies. Security Hardened.
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  LockClosedIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  EyeSlashIcon,
  EnvelopeIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

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

async function handleLogin() {
  if (isLoading.value) return

  error.value = ''
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
      error.value = result.message || 'Un code de verification est requis.'
    } else {
      error.value = result.message || 'Identifiants incorrects'
    }
  } catch (err) {
    error.value = 'Service momentanément indisponible'
    console.error('Login error:', err)
  } finally {
    isLoading.value = false
  }
}
</script>
