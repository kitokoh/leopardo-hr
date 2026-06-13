<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-950 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden font-sans">
    <!-- Animated Background -->
    <div class="absolute inset-0 z-0">
      <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-brand-600/20 rounded-full blur-[120px] animate-pulse-slow"></div>
      <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-500/20 rounded-full blur-[120px] animate-pulse-slow" style="animation-delay: 1.5s"></div>
    </div>

    <!-- Grid Pattern overlay -->
    <div class="absolute inset-0 z-0 opacity-10" style="background-image: radial-gradient(#14b8a6 0.5px, transparent 0.5px); background-size: 24px 24px;"></div>

    <div class="max-w-md w-full space-y-8 relative z-10 text-center animate-fade-in">
      <div class="mx-auto h-24 w-24 flex items-center justify-center rounded-3xl bg-white/5 backdrop-blur-2xl border border-white/20 shadow-glass animate-bounce-slow overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-500/20 to-emerald-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
        <ArrowRightOnRectangleIcon class="h-10 w-10 text-brand-400 relative z-10" />
      </div>

      <div class="glass-card p-1 pb-1 overflow-hidden shadow-premium">
        <div class="bg-slate-900/40 backdrop-blur-3xl p-10 rounded-[1.4rem]">
          <h2 class="text-3xl font-black tracking-tight text-white uppercase italic">
            Déconnexion <span class="text-brand-500 not-italic font-black">en cours</span>
          </h2>
          <p class="mt-4 text-slate-400 font-bold tracking-[0.1em] text-sm leading-relaxed">
            Merci de votre visite sur Leopardo RH.<br />
            Nous sécurisons votre session...
          </p>

          <div class="mt-10 relative">
            <div class="overflow-hidden h-1.5 mb-4 text-xs flex rounded-full bg-white/5">
              <div
                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-brand-500 transition-all duration-[3000ms] ease-linear"
                :style="{ width: progress + '%' }"
              ></div>
            </div>
            <div class="flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-500">
              <span class="animate-pulse">Redirection vers le cockpit d'accès</span>
            </div>
          </div>
        </div>
      </div>

      <p class="text-[10px] font-black uppercase tracking-widest text-slate-600">
        © 2026 Leopardo Systems • Sécurité Approuvée
      </p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const progress = ref(0)

onMounted(async () => {
  // Start progress bar
  setTimeout(() => {
    progress.value = 100
  }, 100)

  // Perform logout
  try {
    await authStore.logout()
  } catch (error) {
    console.error('Logout error:', error)
  }

  // Redirect after animation
  setTimeout(() => {
    router.push('/login')
  }, 3200)
})
</script>

<style scoped>
@keyframes fade-in {
  0% { opacity: 0; transform: translateY(20px); }
  100% { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
  animation: fade-in 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
