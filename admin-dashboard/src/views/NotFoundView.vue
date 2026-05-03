<template>
  <div class="min-h-screen bg-white px-4 py-16 sm:px-6 sm:py-24 md:grid md:place-items-center lg:px-8">
    <div class="mx-auto max-w-max">
      <main class="sm:flex">
        <p class="text-4xl font-bold tracking-tight text-indigo-600 sm:text-5xl">404</p>
        <div class="sm:ml-6">
          <div class="sm:border-l sm:border-gray-200 sm:pl-6">
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
              Page non trouvée
            </h1>
            <p class="mt-1 text-base text-gray-500">
              Désolé, nous n'avons pas pu trouver la page que vous recherchez.
            </p>
          </div>
          <div class="mt-10 flex space-x-3 sm:border-l sm:border-transparent sm:pl-6">
            <router-link
              to="/"
              class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            >
              <ArrowLeftIcon class="-ml-0.5 mr-2 h-4 w-4" />
              Retour au tableau de bord
            </router-link>
            <button
              @click="goBack"
              class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            >
              Page précédente
            </button>
          </div>
        </div>
      </main>
      
      <!-- Additional help -->
      <div class="mt-16 border-t border-gray-200 pt-8">
        <h2 class="text-lg font-medium text-gray-900">Liens utiles</h2>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <router-link
            to="/users"
            class="group relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm hover:border-gray-400"
          >
            <div>
              <span class="absolute inset-0" aria-hidden="true"></span>
              <div class="flex items-center">
                <UsersIcon class="h-6 w-6 text-gray-400 group-hover:text-gray-500" />
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-900">Utilisateurs</p>
                  <p class="text-sm text-gray-500">Gérer les comptes utilisateurs</p>
                </div>
              </div>
            </div>
          </router-link>
          
          <router-link
            to="/companies"
            class="group relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm hover:border-gray-400"
          >
            <div>
              <span class="absolute inset-0" aria-hidden="true"></span>
              <div class="flex items-center">
                <BuildingOfficeIcon class="h-6 w-6 text-gray-400 group-hover:text-gray-500" />
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-900">Entreprises</p>
                  <p class="text-sm text-gray-500">Gérer les entreprises</p>
                </div>
              </div>
            </div>
          </router-link>
          
          <router-link
            to="/support"
            class="group relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm hover:border-gray-400"
          >
            <div>
              <span class="absolute inset-0" aria-hidden="true"></span>
              <div class="flex items-center">
                <ChatBubbleLeftRightIcon class="h-6 w-6 text-gray-400 group-hover:text-gray-500" />
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-900">Support</p>
                  <p class="text-sm text-gray-500">Tickets de support</p>
                </div>
              </div>
            </div>
          </router-link>
          
          <router-link
            to="/system"
            class="group relative rounded-lg border border-gray-300 bg-white px-6 py-5 shadow-sm hover:border-gray-400"
          >
            <div>
              <span class="absolute inset-0" aria-hidden="true"></span>
              <div class="flex items-center">
                <CogIcon class="h-6 w-6 text-gray-400 group-hover:text-gray-500" />
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-900">Système</p>
                  <p class="text-sm text-gray-500">Configuration système</p>
                </div>
              </div>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  UsersIcon,
  BuildingOfficeIcon,
  ChatBubbleLeftRightIcon,
  CogIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()

function goBack() {
  if (window.history.length > 1) {
    router.go(-1)
  } else {
    router.push('/')
  }
}
</script>