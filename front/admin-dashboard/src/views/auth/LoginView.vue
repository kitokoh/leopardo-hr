<template>
  <div class="min-h-screen flex items-center justify-center bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.22),transparent_34%),linear-gradient(135deg,#020617,#0f172a_45%,#064e3b)] py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Decorations -->
    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-500/20 rounded-full blur-[120px] animate-pulse-slow"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-400/20 rounded-full blur-[120px] animate-pulse-slow"></div>

    <div class="max-w-md w-full space-y-8 relative z-10 animate-fade-in">
      <div class="text-center">
        <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-[1.75rem] bg-white/10 backdrop-blur-xl border border-white/20 shadow-glass">
          <span class="text-3xl font-extrabold text-white tracking-tighter">LRH</span>
        </div>
        <div class="mt-6 inline-flex rounded-full border border-emerald-300/20 bg-emerald-400/10 px-4 py-1.5 text-xs font-black uppercase tracking-[0.22em] text-emerald-200">
          Console platform admin
        </div>
        <h2 class="mt-8 text-center text-4xl font-extrabold tracking-tight text-white drop-shadow-md">
          Leopardo RH
        </h2>
        <p class="mt-3 text-center text-brand-100/80 font-medium">
          Pilotez les clients, abonnements, risques et operations depuis une console securisee.
        </p>
      </div>

      <div class="mt-8 bg-white/10 dark:bg-zinc-900/40 backdrop-blur-2xl border border-white/20 dark:border-white/10 rounded-[2rem] shadow-glass p-8 animate-slide-up">
        <form class="space-y-6" @submit.prevent="handleLogin">
          <div class="space-y-4">
            <div>
              <label for="email" class="block text-sm font-semibold text-brand-50 mb-2 ml-1">Adresse email</label>
              <div class="relative group">
                <input
                  id="email"
                  v-model="form.email"
                  name="email"
                  type="email"
                  autocomplete="email"
                  required
                  autofocus
                  class="block w-full rounded-2xl border-0 bg-white/5 py-3 px-4 text-white ring-1 ring-inset ring-white/20 placeholder:text-brand-200/50 focus:ring-2 focus:ring-inset focus:ring-brand-400 sm:text-sm sm:leading-6 transition-all duration-200"
                  placeholder="admin@leopardo-rh.com"
                />
              </div>
            </div>

            <div>
              <label for="password" class="block text-sm font-semibold text-brand-50 mb-2 ml-1">Mot de passe</label>
              <div class="relative group">
                <input
                  id="password"
                  v-model="form.password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  class="block w-full rounded-2xl border-0 bg-white/5 py-3 pl-4 pr-12 text-white ring-1 ring-inset ring-white/20 placeholder:text-brand-200/50 focus:ring-2 focus:ring-inset focus:ring-brand-400 sm:text-sm sm:leading-6 transition-all duration-200"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 flex items-center px-4 text-brand-200/60 hover:text-white transition-colors"
                  :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                  :title="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                  @click="showPassword = !showPassword"
                >
                  <EyeSlashIcon v-if="showPassword" class="h-5 w-5" aria-hidden="true" />
                  <EyeIcon v-else class="h-5 w-5" aria-hidden="true" />
                </button>
              </div>
            </div>

            <div v-if="requiresTwoFactor" class="animate-fade-in">
              <label for="two-factor-code" class="block text-sm font-semibold text-brand-50 mb-2 ml-1">Code de vérification</label>
              <input
                id="two-factor-code"
                v-model="form.twoFactorCode"
                name="two-factor-code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                required
                class="block w-full rounded-2xl border-0 bg-white/5 py-3 px-4 text-white ring-1 ring-inset ring-white/20 placeholder:text-brand-200/50 focus:ring-2 focus:ring-inset focus:ring-brand-400 sm:text-sm sm:leading-6 transition-all duration-200"
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
                class="h-4 w-4 rounded border-white/20 bg-white/5 text-brand-500 focus:ring-brand-400 focus:ring-offset-zinc-900"
              />
              <label for="remember-me" class="ml-2 block text-sm text-brand-100 hover:text-white cursor-pointer transition-colors">
                Se souvenir de moi
              </label>
            </div>

            <div class="text-sm">
              <a href="#" class="font-semibold text-brand-300 hover:text-brand-200 transition-colors">
                Mot de passe oublie ?
              </a>
            </div>
          </div>

          <div v-if="error" class="rounded-2xl bg-red-500/10 border border-red-500/20 p-4 animate-shake">
            <div class="flex">
              <ExclamationTriangleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-200">
                  Erreur de connexion
                </h3>
                <div class="mt-1 text-xs text-red-300/80">
                  {{ error }}
                </div>
              </div>
            </div>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="group relative flex w-full justify-center rounded-2xl bg-gradient-to-r from-brand-500 to-brand-600 py-3 px-4 text-sm font-bold text-white shadow-lg hover:from-brand-400 hover:to-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
          >
            <span v-if="isLoading" class="absolute inset-y-0 left-0 flex items-center pl-4">
              <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            <LockClosedIcon v-else class="absolute inset-y-0 left-0 flex items-center pl-4 h-12 w-9 text-brand-300/50 group-hover:text-brand-200 transition-colors" aria-hidden="true" />
            {{ isLoading ? 'Connexion en cours...' : 'Se connecter' }}
          </button>

          <button
            type="button"
            :disabled="isLoading"
            class="w-full flex items-center justify-center space-x-2 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 py-3 px-4 text-sm font-bold text-emerald-100 hover:bg-emerald-400/15 disabled:opacity-50 transition-all duration-200"
            @click="selectDemoUser('admin@leopardo-rh.com', 'password123')"
          >
            <UserGroupIcon class="h-5 w-5 text-emerald-300" aria-hidden="true" />
            <span>Utiliser le compte demo super-admin</span>
          </button>

          <button
            type="button"
            class="w-full flex items-center justify-center space-x-2 rounded-2xl border border-white/10 bg-white/5 py-3 px-4 text-sm font-semibold text-white hover:bg-white/10 transition-all duration-200"
            @click="showDemoModal = true"
          >
            <UserGroupIcon class="h-5 w-5 text-emerald-400" aria-hidden="true" />
            <span>Acces Demo</span>
          </button>
        </form>

        <!-- System Status Footer -->
        <div class="mt-8 pt-6 border-t border-white/10">
          <div class="flex flex-col items-center space-y-3">
            <p class="text-[10px] uppercase tracking-widest text-brand-300/40 font-bold">System Integrity</p>
            <div class="flex items-center space-x-4">
              <div class="flex items-center space-x-1.5">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                <span class="text-[10px] text-brand-200/60 font-medium">API</span>
              </div>
              <div class="flex items-center space-x-1.5">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                <span class="text-[10px] text-brand-200/60 font-medium">DB</span>
              </div>
              <div class="flex items-center space-x-1.5">
                <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                <span class="text-[10px] text-brand-200/60 font-medium">WS</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Demo users modal -->
    <Teleport to="body">
      <div
        v-if="showDemoModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="showDemoModal = false"
      >
        <div class="absolute inset-0 bg-zinc-950/60 backdrop-blur-md"></div>
        <div class="w-full max-w-lg relative bg-zinc-900 border border-white/10 rounded-3xl shadow-2xl overflow-hidden animate-scale-in">
          <div class="flex items-center justify-between border-b border-white/5 p-6 bg-white/5">
            <div>
              <h3 class="text-xl font-bold text-white">Comptes de Démonstration</h3>
              <p class="text-sm text-zinc-400 mt-1">Accédez instantanément à l'environnement de test</p>
            </div>
            <button
              type="button"
              class="p-2 rounded-xl bg-white/5 text-zinc-400 hover:text-white hover:bg-white/10 transition-all"
              @click="showDemoModal = false"
            >
              <XMarkIcon class="h-6 w-6" aria-hidden="true" />
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div class="group cursor-pointer" @click="selectDemoUser('admin@leopardo-rh.com', 'password123')">
              <div class="flex items-center p-4 rounded-2xl bg-white/5 border border-white/5 hover:border-brand-500/50 hover:bg-brand-500/5 transition-all duration-300">
                <div class="h-12 w-12 rounded-xl bg-brand-500/20 flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                  <span class="text-brand-400 font-bold">SA</span>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-white">Super Administrateur</div>
                  <div class="text-sm text-zinc-500">admin@leopardo-rh.com</div>
                </div>
                <div class="text-brand-500 opacity-0 group-hover:opacity-100 transition-opacity">
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </div>

            <div class="rounded-2xl bg-amber-500/10 border border-amber-500/20 p-4">
              <div class="flex">
                <div class="shrink-0">
                  <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-xs text-amber-200/80 leading-relaxed">
                    Cet espace est réservé aux administrateurs plateforme. Les comptes RH et employés se connectent depuis le portail client ou l'application mobile.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  LockClosedIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  EyeSlashIcon,
  UserGroupIcon,
  XMarkIcon,
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
const showDemoModal = ref(false)

async function selectDemoUser(email, password) {
  form.email = email
  form.password = password
  showDemoModal.value = false
  await nextTick()
  await handleLogin()
}

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
      error.value = result.message || 'Un code de vérification est requis.'
    } else {
      error.value = result.message || 'Erreur de connexion'
    }
  } catch (err) {
    error.value = 'Une erreur inattendue est survenue'
    console.error('Login error:', err)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-4px); }
  75% { transform: translateX(4px); }
}

.animate-shake {
  animation: shake 0.4s ease-in-out;
}

@keyframes scale-in {
  0% { transform: scale(0.95); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.animate-scale-in {
  animation: scale-in 0.2s ease-out forwards;
}
</style>
