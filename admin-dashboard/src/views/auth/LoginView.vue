<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <!-- Logo and title -->
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-lg bg-indigo-600">
          <span class="text-xl font-bold text-white">LRH</span>
        </div>
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
          Administration Leopardo RH
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Connectez-vous à votre espace d'administration
        </p>
      </div>

      <!-- Login form -->
      <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">Adresse email</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              class="relative block w-full rounded-t-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
              placeholder="Adresse email"
            />
          </div>
          <div>
            <label for="password" class="sr-only">Mot de passe</label>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              autocomplete="current-password"
              required
              class="relative block w-full rounded-b-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
              placeholder="Mot de passe"
            />
          </div>
        </div>

        <!-- Remember me and forgot password -->
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input
              id="remember-me"
              v-model="form.remember"
              name="remember-me"
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
            />
            <label for="remember-me" class="ml-2 block text-sm text-gray-900">
              Se souvenir de moi
            </label>
          </div>

          <div class="text-sm">
            <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500">
              Mot de passe oublié ?
            </a>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="error" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">
                Erreur de connexion
              </h3>
              <div class="mt-2 text-sm text-red-700">
                {{ error }}
              </div>
            </div>
          </div>
        </div>

        <!-- Submit button -->
        <div>
          <button
            type="submit"
            :disabled="isLoading"
            class="group relative flex w-full justify-center rounded-md bg-indigo-600 py-2 px-3 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
              <LockClosedIcon
                :class="[
                  'h-5 w-5',
                  isLoading ? 'animate-spin' : 'group-hover:text-indigo-400'
                ]"
                aria-hidden="true"
              />
            </span>
            {{ isLoading ? 'Connexion...' : 'Se connecter' }}
          </button>
        </div>

        <!-- System status -->
        <div class="mt-6 border-t border-gray-200 pt-6">
          <div class="text-center">
            <p class="text-xs text-gray-500">Statut du système</p>
            <div class="mt-2 flex items-center justify-center space-x-4 text-xs">
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                <span class="text-gray-600">API</span>
              </div>
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                <span class="text-gray-600">Base de données</span>
              </div>
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                <span class="text-gray-600">WebSocket</span>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { LockClosedIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
  remember: false
})

const isLoading = ref(false)
const error = ref('')

async function handleLogin() {
  if (isLoading.value) return

  error.value = ''
  isLoading.value = true

  try {
    const result = await authStore.login({
      email: form.email,
      password: form.password,
      remember: form.remember
    })

    if (result.success) {
      // Redirect to dashboard
      router.push('/')
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