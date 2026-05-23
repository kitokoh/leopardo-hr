<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-lg bg-indigo-600">
          <span class="text-xl font-bold text-white">LRH</span>
        </div>
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">
          Administration Leopardo RH
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Connectez-vous a votre espace d'administration
        </p>
      </div>

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
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                :class="[
                  'relative block w-full border-0 py-1.5 pr-12 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6',
                  requiresTwoFactor ? '' : 'rounded-b-md'
                ]"
                placeholder="Mot de passe"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 transition hover:text-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                :title="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                @click="showPassword = !showPassword"
              >
                <EyeSlashIcon v-if="showPassword" class="h-5 w-5" aria-hidden="true" />
                <EyeIcon v-else class="h-5 w-5" aria-hidden="true" />
              </button>
            </div>
          </div>
          <div v-if="requiresTwoFactor">
            <label for="two-factor-code" class="sr-only">Code de verification</label>
            <input
              id="two-factor-code"
              v-model="form.twoFactorCode"
              name="two-factor-code"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
              class="relative block w-full rounded-b-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
              placeholder="Code de verification a 6 chiffres"
            />
          </div>
        </div>

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
              Mot de passe oublie ?
            </a>
          </div>
        </div>

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

        <div class="mt-4 border-t border-gray-200 pt-4">
          <button
            type="button"
            class="group relative flex w-full justify-center rounded-md bg-emerald-600 py-2 px-3 text-sm font-semibold text-white hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
            @click="showDemoModal = true"
          >
            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
              <UserGroupIcon class="h-5 w-5 group-hover:text-emerald-300" aria-hidden="true" />
            </span>
            Acces Demo
          </button>
        </div>

        <!-- Demo users modal -->
        <Teleport to="body">
          <div
            v-if="showDemoModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showDemoModal = false"
          >
            <div class="w-full max-w-lg max-h-[80vh] overflow-y-auto rounded-xl bg-white shadow-2xl">
              <div class="sticky top-0 flex items-center justify-between border-b bg-white px-6 py-4 rounded-t-xl">
                <h3 class="text-lg font-bold text-gray-900">Choisir un compte demo</h3>
                <button
                  type="button"
                  class="rounded-md text-gray-400 hover:text-gray-600"
                  @click="showDemoModal = false"
                >
                  <XMarkIcon class="h-6 w-6" />
                </button>
              </div>
              <div class="p-6 space-y-4">
                <!-- Super Admin -->
                <div>
                  <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Super Admin</h4>
                  <button
                    type="button"
                    class="w-full text-left rounded-lg border border-gray-200 p-3 hover:border-indigo-400 hover:bg-indigo-50 transition"
                    @click="selectDemoUser('admin@leopardo-rh.com', 'password123')"
                  >
                    <div class="font-medium text-gray-900">Super Administrateur</div>
                    <div class="text-sm text-gray-500">admin@leopardo-rh.com</div>
                  </button>
                </div>

                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                  Cet espace est reserve aux administrateurs plateforme. Les comptes RH et employes de demonstration se connectent depuis le portail client ou l'application mobile.
                </p>
              </div>
            </div>
          </div>
        </Teleport>

        <div class="mt-6 border-t border-gray-200 pt-6">
          <div class="text-center">
            <p class="text-xs text-gray-500">Statut du systeme</p>
            <div class="mt-2 flex items-center justify-center space-x-4 text-xs">
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                <span class="text-gray-600">API</span>
              </div>
              <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-green-400 mr-1"></div>
                <span class="text-gray-600">Base de donnees</span>
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
      error.value = result.message || 'Un code de verification est requis.'
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
