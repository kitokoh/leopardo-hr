<template>
  <div class="card overflow-hidden animate-slide-up">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-700">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-black uppercase tracking-tight text-slate-900 dark:text-white">
            {{ t('systemPage.infra.title') }}
          </h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ t('systemPage.infra.subtitle') }}
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-xs font-black uppercase tracking-widest text-slate-700 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
          :disabled="loading"
          @click="load"
        >
          {{ loading ? t('systemPage.infra.loading') : t('systemPage.infra.refresh') }}
        </button>
      </div>
    </div>

    <div v-if="error" class="px-6 py-4 text-sm text-red-600 dark:text-red-400" role="alert">
      {{ error }}
    </div>

    <div v-else class="divide-y divide-slate-200 dark:divide-slate-700">
      <section v-for="tier in tiers" :key="tier.tier" class="px-6 py-5">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">
            {{ tier.tier }} — {{ t('systemPage.infra.activeStack', { stack: tier.active_stack || '?' }) }}
          </h3>
          <span v-if="tier.updated_at" class="text-xs text-slate-400">
            {{ t('systemPage.infra.registryUpdated', { date: tier.updated_at }) }}
          </span>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="stack in tier.stacks"
            :key="`${tier.tier}-${stack.id}`"
            class="rounded-xl border p-4"
            :class="stack.active
              ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-700 dark:bg-emerald-900/20'
              : 'border-slate-200 dark:border-slate-700'"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white">
                  {{ t('systemPage.infra.stackLabel', { id: stack.id }) }}
                </p>
                <p class="mt-0.5 text-xs font-semibold"
                   :class="statusClass(stack.status)">
                  {{ stack.status }}
                </p>
              </div>
              <span
                v-if="stack.active"
                class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-white"
              >
                {{ t('systemPage.infra.activeBadge') }}
              </span>
            </div>

            <dl class="mt-3 space-y-1 text-xs text-slate-600 dark:text-slate-300">
              <div class="flex justify-between gap-2">
                <dt>{{ t('systemPage.infra.api') }}</dt>
                <dd class="truncate text-right font-mono">{{ stack.api.service || '—' }}</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt>{{ t('systemPage.infra.region') }}</dt>
                <dd class="text-right">{{ stack.api.region || '—' }}</dd>
              </div>
            </dl>

            <p v-if="stack.status_note" class="mt-3 line-clamp-3 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
              {{ stack.status_note }}
            </p>

            <button
              type="button"
              class="mt-4 w-full rounded-lg px-3 py-2 text-xs font-black uppercase tracking-widest transition-colors disabled:cursor-not-allowed disabled:opacity-40"
              :class="stack.active
                ? 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400'
                : 'bg-slate-900 text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200'"
              :disabled="!canSwitch(tier.tier, stack) || busyKey !== ''"
              :title="switchHint(tier.tier, stack)"
              @click="requestSwitch(tier.tier, stack)"
            >
              <span v-if="busyKey === `${tier.tier}-${stack.id}`">{{ t('systemPage.infra.switching') }}</span>
              <span v-else>{{ t('systemPage.infra.switchTo', { id: stack.id }) }}</span>
            </button>
          </article>
        </div>
      </section>
    </div>

    <p class="border-t border-slate-200 px-6 py-3 text-[11px] text-slate-400 dark:border-slate-700">
      {{ dispatchConfigured ? t('systemPage.infra.hintDispatch') : t('systemPage.infra.hintNoToken') }}
    </p>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const toast = useToast()
const localeStore = useLocaleStore()

function t(key, params = {}) {
  const template = translate(localeStore.current, key, key)
  // Substitution `:clef` — même convention que SystemView / CatalogTranslate.
  return Object.entries(params).reduce(
    (acc, [name, value]) => acc.replaceAll(`:${name}`, () => String(value)),
    template
  )
}

const tiers = ref([])
const loading = ref(false)
const error = ref('')
const busyKey = ref('')
const dispatchConfigured = ref(false)

function statusClass(status) {
  if (status === 'ready' || status === 'active') return 'text-emerald-600 dark:text-emerald-400'
  if (status === 'degraded') return 'text-amber-600 dark:text-amber-400'
  if (status === 'suspended') return 'text-red-600 dark:text-red-400'
  return 'text-slate-500 dark:text-slate-400'
}

function canSwitch(tier, stack) {
  // Le bouton reste actif même si la cible n'est pas « switchable » : le refus
  // vient de l'API avec une raison précise, plus instructive qu'un bouton grisé
  // sans explication. On ne désactive que ce qui n'a aucun sens (déjà active).
  return dispatchConfigured.value && !stack.active && tier !== undefined
}

function switchHint(tier, stack) {
  if (!dispatchConfigured.value) return t('systemPage.infra.hintNoToken')
  if (stack.active) return t('systemPage.infra.alreadyActive')
  return t('systemPage.infra.confirmHint', { tier, id: stack.id })
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/platform/infra/stacks')
    tiers.value = data?.data || []
    dispatchConfigured.value = Boolean(data?.dispatch_configured)
  } catch (err) {
    error.value = err?.response?.status === 403
      ? t('systemPage.infra.forbidden')
      : t('systemPage.infra.loadError')
  } finally {
    loading.value = false
  }
}

async function requestSwitch(tier, stack) {
  const confirmMessage = t('systemPage.infra.confirmMessage', { tier, id: stack.id, active: stack.active ? '' : '' })
  if (typeof window !== 'undefined' && !window.confirm(confirmMessage)) return

  busyKey.value = `${tier}-${stack.id}`
  try {
    await api.post('/platform/infra/stack-switch', { tier, stack: stack.id, confirm: true })
    toast.success(t('systemPage.infra.switchQueued', { tier, id: stack.id }))
  } catch (err) {
    const message = err?.response?.data?.message
    toast.error(message || t('systemPage.infra.switchError'))
  } finally {
    busyKey.value = ''
  }
}

onMounted(load)
</script>
