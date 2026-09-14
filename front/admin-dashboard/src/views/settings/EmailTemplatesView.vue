<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white">{{ t('emailsAdmin.title') }}</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ t('emailsAdmin.subtitle') }}</p>
      </div>
      <span
        class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-black uppercase tracking-widest text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
      >
        {{ t('emailsAdmin.bodyHint') }}
      </span>
    </header>

    <p v-if="loadError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      {{ t('emailsAdmin.loadFailed') }}
    </p>

    <div class="grid gap-6 lg:grid-cols-4">
      <!-- Liste des modèles -->
      <aside class="rounded-2xl border border-slate-200 bg-white p-3 lg:col-span-1 dark:border-slate-800 dark:bg-slate-900">
        <button
          v-for="template in templates"
          :key="template.key"
          type="button"
          class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors"
          :class="
            template.key === selectedKey
              ? 'bg-brand-500 text-white'
              : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
          "
          @click="select(template.key)"
        >
          <span class="truncate">{{ labelFor(template) }}</span>
          <span
            v-if="isCustomised(template)"
            class="shrink-0 rounded-full bg-amber-400/90 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-950"
          >
            {{ t('emailsAdmin.overridden') }}
          </span>
        </button>
      </aside>

      <!-- Éditeur -->
      <section v-if="current" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-3 dark:border-slate-800 dark:bg-slate-900">
        <!-- Langues -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="code in locales"
            :key="code"
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-black uppercase tracking-wider transition-colors"
            :class="
              code === locale
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
            "
            @click="setLocale(code)"
          >
            {{ code }}
          </button>
        </div>

        <label class="block">
          <span class="mb-1 block text-xs font-black uppercase tracking-widest text-slate-500">{{ t('emailsAdmin.subject') }}</span>
          <input
            v-model="form.subject"
            type="text"
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-black uppercase tracking-widest text-slate-500">{{ t('emailsAdmin.heading') }}</span>
          <input
            v-model="form.heading"
            type="text"
            maxlength="255"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
          />
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-black uppercase tracking-widest text-slate-500">{{ t('emailsAdmin.body') }}</span>
          <textarea
            v-model="form.body"
            rows="6"
            maxlength="5000"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-6 dark:border-slate-700 dark:bg-slate-950"
          ></textarea>
        </label>

        <label class="block">
          <span class="mb-1 block text-xs font-black uppercase tracking-widest text-slate-500">{{ t('emailsAdmin.ctaLabel') }}</span>
          <input
            v-model="form.cta_label"
            type="text"
            maxlength="160"
            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
          />
        </label>

        <div>
          <span class="mb-1 block text-xs font-black uppercase tracking-widest text-slate-500">{{ t('emailsAdmin.variables') }}</span>
          <div class="flex flex-wrap gap-2">
            <code
              v-for="variable in current.variables"
              :key="variable"
              class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
              {{ variable }}
            </code>
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            type="button"
            class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-white transition-opacity disabled:opacity-50"
            :disabled="saving"
            @click="save"
          >
            {{ t('emailsAdmin.save') }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200"
            :disabled="saving"
            @click="reset"
          >
            {{ t('emailsAdmin.reset') }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200"
            @click="preview"
          >
            {{ t('emailsAdmin.preview') }}
          </button>
        </div>

        <!-- Aperçu rendu dans le VRAI layout de l'e-mail -->
        <div v-if="previewHtml" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
          <iframe
            :srcdoc="previewHtml"
            sandbox=""
            class="h-[520px] w-full bg-white"
            :title="t('emailsAdmin.preview')"
          ></iframe>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { translate } from '@/i18n/index.js'
import { useLocaleStore } from '@/stores/locale.js'

const localeStore = useLocaleStore()
const t = (key) => translate(localeStore.current, key) || key
const toast = useToast()

const templates = ref([])
const meta = ref({ locales: ['fr', 'en', 'ar', 'tr'] })
const selectedKey = ref(null)
const locale = ref('fr')
const saving = ref(false)
const loadError = ref(false)
const previewHtml = ref('')

const form = reactive({ subject: '', heading: '', body: '', cta_label: '' })

const locales = computed(() => meta.value.locales || ['fr', 'en', 'ar', 'tr'])
const current = computed(() => templates.value.find((item) => item.key === selectedKey.value) || null)
const effective = computed(() => current.value?.locales?.[locale.value] || null)

/** Libellé lisible : l'objet par défaut du modèle, dans la langue courante. */
function labelFor(template) {
  const entry = template.locales?.[localeStore.current] || template.locales?.fr || template.locales?.en
  return entry?.subject || template.key
}

/** Vrai si AU MOINS une langue a été personnalisée (badge de la liste). */
function isCustomised(template) {
  return Object.values(template.locales || {}).some((entry) =>
    Object.values(entry?.overridden || {}).some(Boolean)
  )
}

/**
 * Le formulaire affiche la valeur EFFECTIVE (surcharge sinon défaut) : si une
 * valeur est écrite en dur dans l'e-mail, la remplacer est le geste naturel.
 */
function syncForm() {
  form.subject = effective.value?.subject || ''
  form.heading = effective.value?.heading || ''
  form.body = effective.value?.body || ''
  form.cta_label = effective.value?.cta_label || ''
  previewHtml.value = ''
}

watch([selectedKey, locale], syncForm, { immediate: true })

function select(key) {
  selectedKey.value = key
}

function setLocale(value) {
  locale.value = value
}

async function load() {
  try {
    const { data } = await api.get('/admin/email-templates')
    templates.value = data?.data || []
    meta.value = data?.meta || meta.value
    if (!selectedKey.value && templates.value.length) {
      selectedKey.value = templates.value[0].key
    }
    syncForm()
  } catch (e) {
    loadError.value = true
    console.warn('[admin] email templates load failed', e)
  }
}

async function save() {
  if (!current.value) return
  saving.value = true
  try {
    const { data } = await api.put('/admin/email-templates', {
      template_key: current.value.key,
      locale: locale.value,
      subject: form.subject,
      heading: form.heading,
      body: form.body,
      cta_label: form.cta_label,
    })
    applyResolved(data?.data)
    toast.success(t('emailsAdmin.saved'))
  } catch (e) {
    // Les erreurs API sont déjà notifiées par l'intercepteur global.
    console.warn('[admin] email template save failed', e)
  } finally {
    saving.value = false
  }
}

async function reset() {
  if (!current.value) return
  saving.value = true
  try {
    const { data } = await api.delete('/admin/email-templates', {
      data: { template_key: current.value.key, locale: locale.value },
    })
    applyResolved(data?.data)
    toast.success(t('emailsAdmin.resetDone'))
  } catch (e) {
    console.warn('[admin] email template reset failed', e)
  } finally {
    saving.value = false
  }
}

async function preview() {
  try {
    const { data } = await api.post('/admin/email-templates/preview', {
      locale: locale.value,
      heading: form.heading,
      body: form.body,
      cta_label: form.cta_label,
    })
    previewHtml.value = data?.data?.html || ''
  } catch (e) {
    previewHtml.value = ''
    toast.error(t('emailsAdmin.previewFailed'))
    console.warn('[admin] email template preview failed', e)
  }
}

/** Met à jour la ligne locale concernée avec le contenu résolu renvoyé par l'API. */
function applyResolved(resolved) {
  if (!resolved?.locale || !current.value) return
  current.value.locales[resolved.locale] = resolved
  syncForm()
}

onMounted(load)
</script>
