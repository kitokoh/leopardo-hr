<template>
  <div class="flex items-center justify-between">
    <!-- Results info -->
    <div class="flex flex-1 justify-between sm:hidden">
      <button
        @click="previousPage"
        :disabled="currentPage <= 1"
        class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Précédent
      </button>
      <button
        @click="nextPage"
        :disabled="currentPage >= totalPages"
        class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Suivant
      </button>
    </div>

    <!-- Desktop pagination -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div class="flex items-center space-x-4">
        <!-- Results per page -->
        <div class="flex items-center space-x-3">
          <label class="text-sm font-semibold text-slate-500 dark:text-slate-400">Afficher:</label>
          <select
            :value="perPage"
            @change="$emit('per-page-change', parseInt($event.target.value))"
            class="rounded-xl border-slate-200/50 dark:border-slate-700/50 bg-slate-50 dark:bg-slate-800 text-sm focus:border-brand-500 focus:ring-brand-500 transition-all duration-200"
          >
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <!-- Results info -->
        <div class="text-sm text-slate-500 dark:text-slate-400">
          Affichage de
          <span class="font-bold text-slate-900 dark:text-white">{{ startItem }}</span>
          à
          <span class="font-bold text-slate-900 dark:text-white">{{ endItem }}</span>
          sur
          <span class="font-bold text-slate-900 dark:text-white">{{ totalItems }}</span>
        </div>
      </div>

      <!-- Page navigation -->
      <div>
        <nav class="isolate inline-flex -space-x-1 rounded-xl" aria-label="Pagination">
          <!-- Previous button -->
          <button
            @click="previousPage"
            :disabled="currentPage <= 1"
            class="relative inline-flex items-center rounded-l-xl px-3 py-2 text-slate-400 hover:text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
          >
            <ChevronLeftIcon class="h-5 w-5" />
          </button>

          <!-- Page numbers -->
          <template v-for="page in visiblePages" :key="page">
            <button
              v-if="page !== '...'"
              @click="goToPage(page)"
              :class="[
                'relative inline-flex items-center px-4 py-2 text-sm font-bold rounded-lg transition-all duration-200 mx-0.5',
                page === currentPage
                  ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/30'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              {{ page }}
            </button>
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 text-sm font-bold text-slate-400"
            >
              ...
            </span>
          </template>

          <!-- Next button -->
          <button
            @click="nextPage"
            :disabled="currentPage >= totalPages"
            class="relative inline-flex items-center rounded-r-xl px-3 py-2 text-slate-400 hover:text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all duration-200 disabled:opacity-30 disabled:cursor-not-allowed"
          >
            <ChevronRightIcon class="h-5 w-5" />
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  totalPages: {
    type: Number,
    required: true
  },
  totalItems: {
    type: Number,
    required: true
  },
  perPage: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['page-change', 'per-page-change'])

// Computed properties
const startItem = computed(() => {
  return (props.currentPage - 1) * props.perPage + 1
})

const endItem = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.totalItems)
})

const visiblePages = computed(() => {
  const pages = []
  const total = props.totalPages
  const current = props.currentPage

  if (total <= 7) {
    // Show all pages if total is 7 or less
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)

    if (current <= 4) {
      // Show pages 2-5 and ellipsis
      for (let i = 2; i <= 5; i++) {
        pages.push(i)
      }
      pages.push('...')
    } else if (current >= total - 3) {
      // Show ellipsis and last 4 pages
      pages.push('...')
      for (let i = total - 4; i <= total - 1; i++) {
        pages.push(i)
      }
    } else {
      // Show ellipsis, current page area, and ellipsis
      pages.push('...')
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i)
      }
      pages.push('...')
    }

    // Always show last page (if not already shown)
    if (total > 1 && pages[pages.length - 1] !== total) {
      pages.push(total)
    }
  }

  return pages
})

// Methods
function goToPage(page) {
  if (page !== props.currentPage && page >= 1 && page <= props.totalPages) {
    emit('page-change', page)
  }
}

function previousPage() {
  if (props.currentPage > 1) {
    emit('page-change', props.currentPage - 1)
  }
}

function nextPage() {
  if (props.currentPage < props.totalPages) {
    emit('page-change', props.currentPage + 1)
  }
}
</script>