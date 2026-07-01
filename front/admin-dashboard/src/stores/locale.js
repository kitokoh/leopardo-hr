/**
 * stores/locale.js — Gestion de la locale active pour l'admin dashboard.
 *
 * - Persiste dans localStorage sous la clé `admin_locale`
 * - Synchronise le header Accept-Language des requêtes API
 * - Applique la direction du document (RTL/LTR) pour l'arabe
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { normalizeLocale, resolveDirection, supportedLocales } from '@/i18n/index.js'

const STORAGE_KEY = 'admin_locale'

export const useLocaleStore = defineStore('locale', () => {
  /* ── état ──────────────────────────────────────────────────── */
  const _raw = localStorage.getItem(STORAGE_KEY) || navigator.language || 'fr'
  const current = ref(normalizeLocale(_raw))

  /* ── dérivés ────────────────────────────────────────────────── */
  const direction = computed(() => resolveDirection(current.value))
  const isRtl = computed(() => direction.value === 'rtl')
  const supported = supportedLocales

  /* ── actions ────────────────────────────────────────────────── */
  function setLocale(code) {
    const normalized = normalizeLocale(code)
    current.value = normalized
    localStorage.setItem(STORAGE_KEY, normalized)
    _applyToDocument(normalized)
  }

  function initFromUser(user) {
    const lang = user?.language ?? user?.preferred_language ?? null
    if (lang) setLocale(lang)
  }

  /* ── effet immédiat au chargement ──────────────────────────── */
  _applyToDocument(current.value)

  return { current, direction, isRtl, supported, setLocale, initFromUser }
})

/* ── helpers internes ────────────────────────────────────────── */
function _applyToDocument(locale) {
  if (typeof document === 'undefined') return
  document.documentElement.lang = locale
  document.documentElement.dir = resolveDirection(locale)
}
