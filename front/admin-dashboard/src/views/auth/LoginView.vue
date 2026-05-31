<template>
  <div class="min-h-screen w-full flex items-center justify-center bg-[#030712] relative overflow-hidden font-sans selection:bg-brand-500/30">
    <!-- Sophisticated Mesh Background -->
    <div class="absolute inset-0 z-0 overflow-hidden">
      <div class="absolute top-[-20%] left-[-10%] w-[70%] h-[70%] bg-brand-600/20 rounded-full blur-[120px] animate-pulse-slow"></div>
      <div class="absolute bottom-[-20%] right-[-10%] w-[70%] h-[70%] bg-emerald-600/10 rounded-full blur-[120px] animate-pulse-slow" style="animation-delay: 2s"></div>
      <div class="absolute top-[20%] right-[10%] w-[40%] h-[40%] bg-violet-600/10 rounded-full blur-[100px] animate-float"></div>

      <!-- Grid & Noise Pattern -->
      <div class="absolute inset-0 opacity-[0.15]" style="background-image: url('https://grainy-gradients.vercel.app/noise.svg'); filter: contrast(150%) brightness(100%);"></div>
      <div class="absolute inset-0" style="background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 60px 60px;"></div>
      <div class="absolute inset-0 bg-gradient-to-t from-[#030712] via-transparent to-transparent"></div>
    </div>

    <div class="container max-w-7xl mx-auto px-6 z-10">
      <div class="flex flex-col lg:flex-row items-center justify-between gap-16 lg:gap-24">

        <!-- Premium Branding Side -->
        <div class="flex-1 text-center lg:text-left space-y-10 animate-fade-in max-w-2xl">
          <div class="inline-flex items-center space-x-3 px-4 py-1.5 rounded-full bg-brand-500/10 border border-brand-500/20 backdrop-blur-md">
            <div class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-brand-500"></span>
            </div>
            <span class="text-[10px] font-black text-brand-200 tracking-[0.3em] uppercase">Enterprise Core v4.1</span>
          </div>

          <div class="space-y-6">
            <div class="text-7xl lg:text-9xl font-black text-white tracking-tightest leading-[0.85] drop-shadow-2xl">
              Leo<span class="text-transparent bg-clip-text bg-gradient-to-br from-brand-400 via-brand-500 to-emerald-400">pardo</span>
            </div>
            <p class="text-2xl text-zinc-400 font-light leading-relaxed tracking-wide">
              Orchestrez votre capital humain avec une <span class="text-white font-medium">précision chirurgicale</span> propulsée par l'intelligence artificielle.
            </p>
          </div>

          <div class="grid grid-cols-2 gap-8 pt-8 border-t border-white/5 max-w-md mx-auto lg:mx-0">
            <div>
              <div class="text-3xl font-black text-white italic">99.9%</div>
              <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-1">Uptime Garanti</div>
            </div>
            <div>
              <div class="text-3xl font-black text-white italic">256-bit</div>
              <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-1">Chiffrement AES</div>
            </div>
          </div>
        </div>

        <!-- High-End Login Card -->
        <div class="w-full max-w-[500px] animate-slide-up" style="animation-delay: 0.15s">
          <div class="relative">
            <!-- Multiple Layered Glows -->
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-brand-500/20 rounded-full blur-[80px]"></div>
            <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-[80px]"></div>

            <div class="relative bg-zinc-900/40 backdrop-blur-[40px] border border-white/10 rounded-[3rem] p-12 shadow-[0_32px_64px_-12px_rgba(0,0,0,0.5)] overflow-hidden group">
              <!-- Animated Inner Border Shine -->
              <div class="absolute inset-0 rounded-[3rem] border border-white/5 group-hover:border-white/20 transition-colors duration-700"></div>

              <div class="relative z-10">
                <div class="flex items-center justify-between mb-12">
                  <div>
                    <h2 class="text-3xl font-black text-white tracking-tighter">Leopardo RH</h2>
                    <p class="text-zinc-500 text-sm mt-1 font-semibold uppercase tracking-widest">Connectez-vous a votre espace</p>
                  </div>
                  <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 p-0.5 shadow-lg rotate-3 group-hover:rotate-0 transition-transform duration-500">
                    <div class="h-full w-full rounded-[14px] bg-[#030712] flex items-center justify-center font-black text-white tracking-tighter text-xs">LRH</div>
                  </div>
                </div>

                <form class="space-y-8" @submit.prevent="handleLogin">
                  <div class="space-y-6">
                    <div class="relative group/input">
                      <label for="email" class="absolute -top-2.5 left-4 px-2 bg-[#0d0d12] text-[10px] font-black text-brand-400 uppercase tracking-[0.2em] rounded-md border border-white/5">Adresse email</label>
                      <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        class="w-full bg-white/[0.03] border border-white/10 rounded-2xl py-5 px-6 text-white placeholder:text-zinc-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:border-brand-500/50 transition-all duration-300"
                        placeholder="nom@entreprise.com"
                      />
                    </div>

                    <div class="relative group/input">
                      <label for="password" class="absolute -top-2.5 left-4 px-2 bg-[#0d0d12] text-[10px] font-black text-brand-400 uppercase tracking-[0.2em] rounded-md border border-white/5">Mot de passe</label>
                      <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        class="w-full bg-white/[0.03] border border-white/10 rounded-2xl py-5 pl-6 pr-16 text-white placeholder:text-zinc-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:border-brand-500/50 transition-all duration-300"
                        placeholder="••••••••••••"
                      />
                      <button
                        type="button"
                        class="absolute right-5 top-1/2 -translate-y-1/2 p-2 rounded-xl text-zinc-600 hover:text-white transition-colors"
                        :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                        :title="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                        @click="showPassword = !showPassword"
                      >
                        <EyeSlashIcon v-if="showPassword" class="h-6 w-6" aria-hidden="true" />
                        <EyeIcon v-else class="h-6 w-6" aria-hidden="true" />
                      </button>
                    </div>
                  </div>

                  <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer group/check">
                      <div class="relative">
                        <input
                          id="remember-me"
                          v-model="form.remember"
                          type="checkbox"
                          class="sr-only"
                        />
                        <div class="w-11 h-6 bg-white/5 rounded-full border border-white/10 transition-all group-hover/check:bg-white/10"></div>
                        <div class="dot absolute left-1 top-1 bg-zinc-600 w-4 h-4 rounded-full transition-all duration-300" :class="{'translate-x-5 bg-brand-500 shadow-[0_0_10px_rgba(139,92,246,0.5)]': form.remember}"></div>
                      </div>
                      <span class="ml-3 text-xs text-zinc-500 font-bold uppercase tracking-widest group-hover/check:text-zinc-300 transition-colors">Se souvenir de moi</span>
                    </label>
                    <a href="#" class="text-[10px] font-black text-zinc-500 hover:text-brand-400 uppercase tracking-widest transition-colors">Mot de passe oublie ?</a>
                  </div>

                  <div v-if="error" class="animate-shake p-4 rounded-2xl bg-red-500/5 border border-red-500/20 flex items-center space-x-3">
                    <div class="h-2 w-2 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]"></div>
                    <div class="flex flex-col">
                      <h3 class="text-[10px] font-black text-red-200 uppercase tracking-widest">Erreur de connexion</h3>
                      <span class="text-[9px] text-red-300/60 font-bold">{{ error }}</span>
                    </div>
                  </div>

                  <div class="space-y-4 pt-4">
                    <button
                      type="submit"
                      :disabled="isLoading"
                      class="relative w-full group/btn overflow-hidden rounded-2xl py-5 bg-white text-[#030712] font-black uppercase tracking-[0.3em] text-xs transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50"
                    >
                      <div class="absolute inset-0 bg-gradient-to-r from-brand-400 via-brand-600 to-emerald-400 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-500"></div>
                      <span class="relative z-10 group-hover/btn:text-white transition-colors duration-300">{{ isLoading ? 'Vérification...' : 'Se connecter' }}</span>
                    </button>

                    <button
                      type="button"
                      class="w-full flex items-center justify-center py-5 rounded-2xl border border-white/5 bg-white/5 text-[10px] font-black text-zinc-500 uppercase tracking-[0.2em] hover:bg-white/[0.08] hover:text-zinc-300 transition-all"
                      @click="showDemoModal = true"
                    >
                      Acces Demo
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Terminal Badge -->
            <div class="mt-8 mx-12 px-6 py-3 rounded-2xl bg-black/40 border border-white/5 backdrop-blur-md flex items-center justify-between">
              <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                  <div class="h-1 w-1 rounded-full bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,1)] animate-pulse"></div>
                  <span class="text-[9px] font-bold text-zinc-600 uppercase tracking-tighter italic">Nodes Online</span>
                </div>
              </div>
              <div class="text-[9px] font-mono text-zinc-700 font-black uppercase">Secure Link Active</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Ultra-Premium Modal -->
    <Teleport to="body">
      <div v-if="showDemoModal" class="fixed inset-0 z-50 flex items-center justify-center p-6">
        <div class="absolute inset-0 bg-[#030712]/90 backdrop-blur-2xl animate-fade-in" @click="showDemoModal = false"></div>
        <div class="relative w-full max-w-2xl bg-[#0d0d12] border border-white/10 rounded-[3.5rem] shadow-[0_64px_128px_-24px_rgba(0,0,0,0.8)] overflow-hidden animate-scale-in">
          <div class="p-12 border-b border-white/5 flex items-center justify-between">
            <div class="space-y-1">
              <h3 class="text-4xl font-black text-white tracking-tightest">Bac à sable</h3>
              <p class="text-zinc-500 text-sm font-bold uppercase tracking-[0.2em]">Environnement de test éphémère</p>
            </div>
            <button @click="showDemoModal = false" class="h-16 w-16 rounded-3xl bg-white/5 text-zinc-500 hover:text-white hover:bg-white/10 transition-all flex items-center justify-center group">
              <XMarkIcon class="h-8 w-8 group-hover:rotate-90 transition-transform duration-500" aria-hidden="true" />
            </button>
          </div>

          <div class="p-12 space-y-8">
            <div
              class="group/item cursor-pointer relative p-8 rounded-[2.5rem] bg-white/[0.02] border border-white/5 hover:border-brand-500/50 hover:bg-brand-500/[0.05] transition-all duration-500"
              @click="selectDemoUser('admin@leopardo-rh.com', 'password123')"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-8">
                  <div class="h-20 w-20 rounded-[2rem] bg-brand-500/10 flex items-center justify-center group-hover/item:scale-110 group-hover/item:bg-brand-500/20 transition-all duration-500">
                    <UserIcon class="h-10 w-10 text-brand-400" aria-hidden="true" />
                  </div>
                  <div class="space-y-1">
                    <div class="text-2xl font-black text-white group-hover/item:text-brand-400 transition-colors">Platform Admin</div>
                    <div class="text-zinc-500 font-mono text-sm tracking-tighter italic">admin@leopardo-rh.com</div>
                  </div>
                </div>
                <div class="h-12 w-12 rounded-2xl border border-white/10 flex items-center justify-center group-hover/item:bg-brand-500 group-hover/item:border-brand-500 group-hover/item:translate-x-2 transition-all duration-500">
                  <ArrowRightIcon class="h-6 w-6 text-white" aria-hidden="true" />
                </div>
              </div>
            </div>

            <div class="p-8 rounded-3xl bg-brand-500/5 border border-brand-500/10 flex items-start space-x-6">
              <div class="h-12 w-12 rounded-2xl bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0">
                <ShieldCheckIcon class="h-6 w-6" aria-hidden="true" />
              </div>
              <div class="space-y-1">
                <p class="text-sm text-brand-200 font-black uppercase tracking-widest">Protocol de sécurité</p>
                <p class="text-xs text-brand-200/60 leading-relaxed font-medium">
                  Cet espace est reserve aux administrateurs plateforme. Les comptes RH et employes se connectent depuis le portail client ou l'application mobile.
                </p>
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
  XMarkIcon,
  UserIcon,
  ArrowRightIcon,
  ShieldCheckIcon,
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
  0% { transform: scale(0.9); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

.animate-scale-in {
  animation: scale-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.tracking-tightest {
  letter-spacing: -0.05em;
}
</style>
