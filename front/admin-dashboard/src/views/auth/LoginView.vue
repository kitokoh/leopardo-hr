<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-950 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans">
    <!-- Animated Background -->
    <div class="absolute inset-0 z-0">
      <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-brand-600/20 rounded-full blur-[120px] animate-pulse-slow"></div>
      <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/20 rounded-full blur-[120px] animate-pulse-slow" style="animation-delay: 1.5s"></div>
      <div class="absolute top-[20%] right-[10%] w-[30%] h-[30%] bg-cyan-500/10 rounded-full blur-[100px] animate-pulse-slow" style="animation-delay: 3s"></div>
    </div>

    <!-- Grid Pattern overlay -->
    <div class="absolute inset-0 z-0 opacity-20" style="background-image: radial-gradient(#14b8a6 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>

    <div class="max-w-md w-full space-y-10 relative z-10 animate-fade-in">
      <div class="text-center">
        <div class="mx-auto h-24 w-24 flex items-center justify-center rounded-3xl bg-white/5 backdrop-blur-2xl border border-white/20 shadow-glass animate-bounce-slow overflow-hidden group">
          <div class="absolute inset-0 bg-gradient-to-br from-brand-500/20 to-emerald-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
          <span class="text-4xl font-black text-white tracking-tighter relative z-10">L</span>
          <span class="text-4xl font-black text-brand-400 tracking-tighter relative z-10">R</span>
        </div>
        <h2 class="mt-8 text-center text-5xl font-black tracking-tight text-white uppercase italic">
          Leopardo <span class="text-brand-500 not-italic font-black">RH</span>
        </h2>
        <p class="mt-4 text-center text-slate-400 font-bold tracking-[0.15em] uppercase text-xs">
          Platform Administration • v4.16
        </p>
      </div>

      <div class="glass-card p-1 pb-1 overflow-hidden shadow-premium">
        <div class="bg-slate-900/40 backdrop-blur-3xl p-8 rounded-[1.4rem]">
          <form class="space-y-6" @submit.prevent="handleLogin">
            <div class="space-y-5">
              <div>
                <label for="email" class="block text-[10px] font-black uppercase tracking-widest text-brand-400 mb-2 ml-1">Identifiant Admin</label>
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
                    class="block w-full rounded-2xl border-0 bg-white/5 py-4 pl-12 pr-4 text-white ring-1 ring-inset ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 text-sm font-bold transition-all duration-300"
                    placeholder="admin@leopardo-rh.com"
                  />
                </div>
              </div>

              <div>
                <label for="password" class="block text-[10px] font-black uppercase tracking-widest text-brand-400 mb-2 ml-1">Clé d'Accès</label>
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
                    class="block w-full rounded-2xl border-0 bg-white/5 py-4 pl-12 pr-12 text-white ring-1 ring-inset ring-white/10 placeholder:text-slate-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 text-sm font-bold transition-all duration-300"
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 hover:text-white transition-colors"
                    @click="showPassword = !showPassword"
                  >
                    <EyeSlashIcon v-if="showPassword" class="h-5 w-5" />
                    <EyeIcon v-else class="h-5 w-5" />
                  </button>
                </div>
              </div>

              <div v-if="requiresTwoFactor" class="animate-slide-up">
                <label for="two-factor-code" class="block text-[10px] font-black uppercase tracking-widest text-amber-400 mb-2 ml-1">Code 2FA</label>
                <input
                  id="two-factor-code"
                  v-model="form.twoFactorCode"
                  name="two-factor-code"
                  type="text"
                  inputmode="numeric"
                  required
                  class="block w-full rounded-2xl border-0 bg-white/5 py-4 px-4 text-white ring-1 ring-inset ring-amber-500/30 focus:ring-2 focus:ring-inset focus:ring-amber-500 text-center text-2xl font-black tracking-[0.5em] transition-all duration-300"
                  placeholder="000000"
                />
              </div>
            </div>

            <div v-if="error" class="rounded-2xl bg-red-500/10 border border-red-500/20 p-4 animate-shake">
              <div class="flex items-center gap-3">
                <ExclamationTriangleIcon class="h-5 w-5 text-red-400 shrink-0" />
                <p class="text-xs font-bold text-red-300 leading-tight">{{ error }}</p>
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
                {{ isLoading ? 'Authentification...' : 'Accéder au Cockpit' }}
              </button>

              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-white/5"></div>
                </div>
                <div class="relative flex justify-center text-xs font-black uppercase tracking-widest">
                  <span class="bg-slate-900/40 px-4 text-slate-600">Ou tester</span>
                </div>
              </div>

              <button
                type="button"
                :disabled="isLoading"
                class="w-full flex items-center justify-center gap-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 py-4 px-4 text-sm font-black uppercase tracking-widest text-emerald-400 hover:bg-emerald-500/20 transition-all duration-300 group"
                @click="loginWithDemo"
              >
                <SparklesIcon class="h-5 w-5 group-hover:animate-pulse" />
                Démonstration Express
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Legal & Info Footer -->
      <div class="flex items-center justify-between px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">
        <span>© 2026 Leopardo Systems</span>
        <div class="flex items-center gap-4">
          <a href="#" class="hover:text-slate-400 transition-colors">Sécurité</a>
          <a href="#" class="hover:text-slate-400 transition-colors">Support</a>
        </div>
      </div>
    </div>

    <!-- Legacy Demo Modal kept for specific selection if needed, but the main button is now direct -->
    <Teleport to="body">
      <div v-if="showDemoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-xl" @click="showDemoModal = false"></div>
        <div class="w-full max-w-sm relative glass-card p-8 text-center animate-scale-in">
           <h3 class="text-xl font-black text-white uppercase tracking-tight">Accès Démo</h3>
           <p class="mt-4 text-slate-400 text-sm font-medium">Choisissez un compte pour explorer l'administration.</p>
           <button
             class="mt-8 w-full btn-primary py-4 uppercase font-black tracking-widest"
             @click="selectDemoUser('admin@leopardo-rh.com', 'password123')"
           >
             Super Admin Test
           </button>
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
  EnvelopeIcon,
  ExclamationTriangleIcon,
  EyeIcon,
  EyeSlashIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
  twoFactorCode: '',
  remember: true,
})

const isLoading = ref(false)
const error = ref('')
const requiresTwoFactor = ref(false)
const showPassword = ref(false)
const showDemoModal = ref(false)

async function loginWithDemo() {
  form.email = 'admin@leopardo-rh.com'
  form.password = 'password123'
  await nextTick()
  await handleLogin()
}

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

input:focus {
  @apply ring-brand-500 border-brand-500 shadow-[0_0_15px_rgba(20,184,166,0.1)];
}
</style>
