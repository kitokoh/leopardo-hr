<template>
  <div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="card p-8 relative overflow-hidden">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>

      <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white mb-2">
            {{ $t('equityInterests.title') }}
          </h1>
          <p class="text-slate-500 dark:text-slate-400 font-medium">
            {{ $t('equityInterests.subtitle') }}
          </p>
        </div>

        <div class="flex items-center gap-4">
          <button @click="load" :disabled="isLoading" class="btn-secondary py-2.5">
            <ArrowPathIcon :class="['h-5 w-5 mr-2', isLoading ? 'animate-spin' : '']" />
            {{ $t('equityInterests.refresh') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Onglets de statut (compteurs scopés au type equity_interest) -->
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="tab in statusTabs"
        :key="tab.value ?? 'all'"
        @click="selectStatus(tab.value)"
        :class="[
          'px-4 py-2 rounded-xl text-sm font-bold transition-colors border',
          activeStatus === tab.value
            ? 'bg-brand-500 text-white border-brand-500'
            : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-brand-400',
        ]"
      >
        {{ $t(tab.labelKey) }}
        <span class="ml-2 text-xs opacity-80">{{ tabCount(tab.value) }}</span>
      </button>

      <div class="flex-1"></div>

      <input
        v-model="search"
        type="search"
        :placeholder="$t('equityInterests.searchPlaceholder')"
        class="form-input py-2 w-64"
        @input="debouncedLoad"
      />
    </div>

    <!-- Bannière d'erreur + retry -->
    <div
      v-if="errorMessage"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
      role="alert"
    >
      {{ errorMessage }}
      <button class="ml-3 underline font-bold" @click="load">{{ $t('equityInterests.retry') }}</button>
    </div>

    <div v-if="isLoading" class="py-12 text-center text-sm font-bold text-slate-400 uppercase tracking-widest">
      {{ $t('equityInterests.loading') }}
    </div>

    <template v-else>
      <div v-if="leads.length === 0" class="card p-12 text-center">
        <InformationCircleIcon class="mx-auto h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-medium text-slate-500">{{ $t('equityInterests.empty') }}</p>
      </div>

      <div v-else class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-widest text-slate-400 border-b border-slate-200/60 dark:border-slate-800/60">
              <th class="px-6 py-4 font-bold">{{ $t('equityInterests.columnDate') }}</th>
              <th class="px-6 py-4 font-bold">{{ $t('equityInterests.columnName') }}</th>
              <th class="px-6 py-4 font-bold">{{ $t('equityInterests.columnContact') }}</th>
              <th class="px-6 py-4 font-bold">{{ $t('equityInterests.columnBracket') }}</th>
              <th class="px-6 py-4 font-bold">{{ $t('equityInterests.columnStatus') }}</th>
              <th class="px-6 py-4 font-bold sr-only">{{ $t('equityInterests.columnActions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200/50 dark:divide-slate-800/50">
            <template v-for="lead in leads" :key="lead.id">
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40 cursor-pointer" @click="toggleExpanded(lead.id)">
                <td class="px-6 py-4 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ formatDate(lead.created_at) }}</td>
                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200">
                  {{ lead.payload?.name || '—' }}
                </td>
                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                  <div>{{ lead.email }}</div>
                  <div v-if="lead.payload?.phone" class="text-xs text-slate-400">{{ lead.payload.phone }}</div>
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                    {{ bracketLabel(lead.payload?.amount_bracket) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold', statusBadgeClass(lead.status)]">
                    {{ $t(`equityInterests.status.${lead.status}`) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <ChevronDownIcon :class="['h-4 w-4 inline text-slate-400 transition-transform', expandedId === lead.id ? 'rotate-180' : '']" />
                </td>
              </tr>

              <!-- Ligne de détail : message, note interne, transitions -->
              <tr v-if="expandedId === lead.id" class="bg-slate-50/70 dark:bg-slate-900/40">
                <td colspan="6" class="px-6 py-5">
                  <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                      <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">{{ $t('equityInterests.messageLabel') }}</p>
                      <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ lead.payload?.message || '—' }}</p>
                      <p v-if="lead.country" class="mt-2 text-xs text-slate-400">{{ $t('equityInterests.countryLabel') }} : {{ lead.country }}</p>
                      <p v-if="lead.locale" class="text-xs text-slate-400">Locale : {{ lead.locale }}</p>
                    </div>
                    <div class="space-y-4">
                      <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">{{ $t('equityInterests.transitionLabel') }}</p>
                        <div class="flex flex-wrap gap-2">
                          <button
                            v-for="next in transitionTargets(lead.status)"
                            :key="next"
                            @click.stop="transition(lead, next)"
                            :disabled="isSaving"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-brand-400 transition-colors"
                          >
                            {{ $t(`equityInterests.status.${next}`) }}
                          </button>
                        </div>
                      </div>
                      <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">{{ $t('equityInterests.noteLabel') }}</p>
                        <textarea
                          v-model="noteDraft"
                          rows="2"
                          class="form-input w-full text-sm"
                          :placeholder="$t('equityInterests.notePlaceholder')"
                        ></textarea>
                        <button
                          @click.stop="saveNote(lead)"
                          :disabled="isSaving"
                          class="btn-secondary mt-2 py-2 text-xs"
                        >
                          {{ $t('equityInterests.saveNote') }}
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="flex items-center justify-between px-6 py-4 border-t border-slate-200/60 dark:border-slate-800/60">
          <p class="text-xs text-slate-400">{{ meta.total }} · {{ $t('equityInterests.pageLabel') }} {{ meta.current_page }}/{{ meta.last_page }}</p>
          <div class="flex gap-2">
            <button :disabled="meta.current_page <= 1 || isLoading" @click="goToPage(meta.current_page - 1)" class="btn-secondary py-1.5 px-3 text-xs">‹</button>
            <button :disabled="meta.current_page >= meta.last_page || isLoading" @click="goToPage(meta.current_page + 1)" class="btn-secondary py-1.5 px-3 text-xs">›</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
// #8440 — suivi des manifestations d'intérêt « prendre des parts » (page
// vitrine /investir, entrée footer). Données : leads marketing_leads de type
// `equity_interest` via GET /admin/marketing/leads?type=equity_interest ;
// qualification par PATCH /admin/marketing/leads/{id} (statut + note interne).
// Même garde que le pipeline CRM (`crm.view`).
import { computed, onMounted, ref } from 'vue'
import { ArrowPathIcon, ChevronDownIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const LEAD_TYPE = 'equity_interest'
const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'rejected']

const leads = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, status_counts: {}, type_status_counts: null })
const activeStatus = ref(null)
const search = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const expandedId = ref(null)
const noteDraft = ref('')

const statusTabs = computed(() => [
  { value: null, labelKey: 'equityInterests.tabAll' },
  ...STATUSES.map((status) => ({ value: status, labelKey: `equityInterests.status.${status}` })),
])

const BRACKET_LABEL_KEYS = {
  lt_1k: 'equityInterests.brackets.lt_1k',
  '1k_10k': 'equityInterests.brackets.1k_10k',
  '10k_50k': 'equityInterests.brackets.10k_50k',
  gt_50k: 'equityInterests.brackets.gt_50k',
  discuss: 'equityInterests.brackets.discuss',
}

function bracketLabel(bracket) {
  const key = BRACKET_LABEL_KEYS[bracket]
  return key ? t(key) : '—'
}

function tabCount(status) {
  if (status === null) {
    return Object.values(meta.value.type_status_counts ?? {}).reduce((sum, count) => sum + count, 0)
  }
  return meta.value.type_status_counts?.[status] ?? 0
}

function statusBadgeClass(status) {
  const classes = {
    new: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    contacted: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
    qualified: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    converted: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
    rejected: 'bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400',
  }
  return classes[status] ?? classes.new
}

function transitionTargets(current) {
  return STATUSES.filter((status) => status !== current)
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

function toggleExpanded(id) {
  if (expandedId.value === id) {
    expandedId.value = null
    return
  }
  expandedId.value = id
  const lead = leads.value.find((item) => item.id === id)
  noteDraft.value = lead?.note ?? ''
}

function selectStatus(status) {
  activeStatus.value = status
  goToPage(1)
}

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => goToPage(1), 350)
}

function goToPage(page) {
  load(page)
}

async function load(page = meta.value.current_page || 1) {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params = { type: LEAD_TYPE, page, per_page: 25 }
    if (activeStatus.value) params.status = activeStatus.value
    if (search.value.trim() !== '') params.search = search.value.trim()
    const res = await api.get('/admin/marketing/leads', { params, _skipToast: true })
    leads.value = res.data?.data ?? []
    meta.value = res.data?.meta ?? meta.value
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isLoading.value = false
  }
}

function applyUpdated(updated) {
  const index = leads.value.findIndex((item) => item.id === updated.id)
  if (index !== -1) leads.value[index] = updated
}

async function transition(lead, status) {
  isSaving.value = true
  errorMessage.value = ''
  try {
    const res = await api.patch(`/admin/marketing/leads/${lead.id}`, { status }, { _skipToast: true })
    if (res.data?.data) applyUpdated(res.data.data)
    await load()
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isSaving.value = false
  }
}

async function saveNote(lead) {
  isSaving.value = true
  errorMessage.value = ''
  try {
    const res = await api.patch(`/admin/marketing/leads/${lead.id}`, { note: noteDraft.value }, { _skipToast: true })
    if (res.data?.data) applyUpdated(res.data.data)
  } catch (e) {
    errorMessage.value = e?.response?.data?.localized_message || e?.message || 'Erreur'
  } finally {
    isSaving.value = false
  }
}

onMounted(() => load(1))
</script>
