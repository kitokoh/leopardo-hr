<template>
  <div class="forbidden">
    <div class="forbidden__card">
      <p class="forbidden__code">403</p>
      <h1 class="forbidden__title">{{ t('navigation.forbiddenTitle') }}</h1>
      <p class="forbidden__text">{{ t('navigation.forbiddenText') }}</p>
      <div class="forbidden__actions">
        <RouterLink to="/" class="forbidden__primary">
          {{ t('navigation.forbiddenBack') }}
        </RouterLink>
        <RouterLink to="/logout" class="forbidden__secondary">
          {{ t('navigation.logout') }}
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

/**
 * Écran de refus d'accès (`/forbidden`).
 *
 * ## Pourquoi une page et pas une redirection silencieuse
 *
 * Le guard renvoie ici quand `meta.permission` n'est pas satisfaite. Rediriger
 * vers `/` sans explication ferait croire à un lien cassé : un admin qui suit
 * un favori vers un écran interdit doit comprendre qu'il n'a pas le droit, et
 * savoir quoi faire (demander l'accès, revenir au tableau de bord).
 *
 * ## Ce que cette page ne fait pas
 *
 * Elle n'est pas une frontière de sécurité. L'API reste autoritaire et répond
 * 403 ; cette page évite seulement de monter un écran qu'on sait déjà interdit.
 */
const localeStore = useLocaleStore()

const t = (key) => translate(localeStore.current, key, key)
</script>

<style scoped>
.forbidden {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 2rem;
  background: #f8fafc;
}

.forbidden__card {
  max-width: 32rem;
  padding: 2.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  background: #ffffff;
  text-align: center;
}

.forbidden__code {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: #b45309;
}

.forbidden__title {
  margin: 0.5rem 0 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
}

.forbidden__text {
  margin: 0.75rem 0 0;
  font-size: 0.95rem;
  line-height: 1.6;
  color: #475569;
}

.forbidden__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: center;
  margin-top: 1.75rem;
}

.forbidden__primary,
.forbidden__secondary {
  padding: 0.6rem 1.1rem;
  border-radius: 0.6rem;
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
}

.forbidden__primary {
  background: #0f172a;
  color: #ffffff;
}

.forbidden__secondary {
  border: 1px solid #cbd5e1;
  color: #334155;
}
</style>
