<template>
  <div class="animate-pulse" :class="containerClass">
    <!-- Card skeleton -->
    <template v-if="variant === 'card'">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center gap-4 mb-4">
          <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-xl" />
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-2/3" />
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/3" />
          </div>
        </div>
        <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-2" />
        <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4" />
      </div>
    </template>

    <!-- Table skeleton -->
    <template v-else-if="variant === 'table'">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded w-1/4" />
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div v-for="i in rows" :key="i" class="px-6 py-4 flex items-center gap-4">
            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full" />
            <div class="flex-1 space-y-2">
              <div class="h-3.5 bg-gray-200 dark:bg-gray-700 rounded" :style="{ width: `${60 + (i * 7) % 30}%` }" />
            </div>
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16" />
            <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-16" />
          </div>
        </div>
      </div>
    </template>

    <!-- Chart skeleton -->
    <template v-else-if="variant === 'chart'">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded w-1/3 mb-6" />
        <div class="flex items-end gap-2 h-40">
          <div
            v-for="i in 12"
            :key="i"
            class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-t"
            :style="{ height: `${20 + (i * 13) % 80}%` }"
          />
        </div>
      </div>
    </template>

    <!-- KPI grid skeleton -->
    <template v-else-if="variant === 'kpi-grid'">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <div class="flex items-center justify-between mb-3">
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20" />
            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-lg" />
          </div>
          <div class="h-7 bg-gray-200 dark:bg-gray-700 rounded w-16 mb-1" />
          <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24" />
        </div>
      </div>
    </template>

    <!-- Form skeleton -->
    <template v-else-if="variant === 'form'">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-5">
        <div v-for="i in rows" :key="i" class="space-y-2">
          <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24" />
          <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg w-full" />
        </div>
        <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg w-32 mt-4" />
      </div>
    </template>

    <!-- Line / text skeleton (default) -->
    <template v-else>
      <div class="space-y-3">
        <div v-for="i in rows" :key="i" class="h-3.5 bg-gray-200 dark:bg-gray-700 rounded" :style="{ width: `${70 + (i * 11) % 30}%` }" />
      </div>
    </template>
  </div>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'text',
    validator: (v) => ['text', 'card', 'table', 'chart', 'kpi-grid', 'form'].includes(v),
  },
  rows: {
    type: Number,
    default: 5,
  },
  containerClass: {
    type: String,
    default: '',
  },
})
</script>
