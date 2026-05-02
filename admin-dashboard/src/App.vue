<template>
  <div id="app" class="min-h-screen bg-gray-50">
    <!-- Loading global -->
    <div v-if="isLoading" class="fixed inset-0 bg-white z-50 flex items-center justify-center">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
        <p class="mt-4 text-gray-600">Chargement de l'administration...</p>
      </div>
    </div>

    <!-- Application principale -->
    <router-view v-else />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const isLoading = ref(true)
const authStore = useAuthStore()
const router = useRouter()

onMounted(async () => {
  try {
    // Vérifier l'authentification au démarrage
    if (authStore.token) {
      await authStore.checkAuth()
    }
    
    // Rediriger vers login si non authentifié et pas déjà sur la page login
    if (!authStore.isAuthenticated && router.currentRoute.value.name !== 'login') {
      router.push('/login')
    }
  } catch (error) {
    console.error('Erreur lors de l\'initialisation:', error)
  } finally {
    isLoading.value = false
  }
})
</script>

<style>
/* Import NProgress styles */
@import 'nprogress/nprogress.css';

/* Custom NProgress styling */
#nprogress .bar {
  background: #3B82F6 !important;
  height: 3px !important;
}

#nprogress .peg {
  box-shadow: 0 0 10px #3B82F6, 0 0 5px #3B82F6 !important;
}

#nprogress .spinner-icon {
  border-top-color: #3B82F6 !important;
  border-left-color: #3B82F6 !important;
}

/* Global styles */
html {
  scroll-behavior: smooth;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Focus styles */
.focus\:ring-2:focus {
  outline: 2px solid transparent;
  outline-offset: 2px;
}

/* Animation utilities */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { 
    opacity: 0;
    transform: translateY(10px);
  }
  to { 
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

.animate-slide-up {
  animation: slideUp 0.3s ease-out;
}

/* Print styles */
@media print {
  .no-print {
    display: none !important;
  }
}
</style>