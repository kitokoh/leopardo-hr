// Minimal Vue ESLint flat config for CI signal without legacy bulk cleanup.
// Migrated from .eslintrc.cjs for ESLint v10 (flat config only).
//
// Issue #2481 : `no-undef` était désactivé → des refs non déclarées dans
// <script setup> (SystemView.vue : stats, healthCheck, loadBalancerNodes…)
// passaient lint + build et ne cassaient qu'AU RUNTIME (ReferenceError).
// Réactivé avec les globals navigateur (package `globals`) — toute ref non
// déclarée fait désormais échouer le lint.
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import babelParser from '@babel/eslint-parser'
import globals from 'globals'

export default [
  {
    ignores: ['dist/**', 'node_modules/**', 'playwright-report/**', 'test-results/**'],
  },
  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        // #2481 : globals navigateur complets (window/document/navigator/
        // localStorage/setTimeout/URL/Blob/EventSource… inclus).
        ...globals.browser,
        // Node/bundler résiduels explicitement autorisés.
        process: 'readonly',
        module: 'readonly',
        require: 'readonly',
        __dirname: 'readonly',
      },
      parserOptions: {
        parser: babelParser,
        requireConfigFile: false,
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
    rules: {
      'no-console': 'off',
      'no-eval': 'error',
      'no-implied-eval': 'error',
      'no-new-func': 'error',
      'no-script-url': 'error',
      // #2481 : refs non déclarées = erreur (SystemView/UsersView corrigés).
      'no-undef': 'error',
      'no-unused-vars': 'warn',
      'vue/no-mutating-props': 'warn',
      'vue/no-v-html': 'error',
      'vue/no-side-effects-in-computed-properties': 'warn',
      'vue/require-toggle-inside-transition': 'warn',
      'vue/multi-word-component-names': 'off',
    },
  },
  {
    files: ['e2e/**/*.js', 'playwright.config.js'],
    languageOptions: {
      globals: {
        process: 'readonly',
        require: 'readonly',
        module: 'readonly',
        __dirname: 'readonly',
        // Playwright s'exécute dans un navigateur réel.
        ...globals.browser,
      },
    },
  },
]
