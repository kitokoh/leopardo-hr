// Minimal Vue ESLint flat config for CI signal without legacy bulk cleanup.
// Migrated from .eslintrc.cjs for ESLint v10 (flat config only).
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import babelParser from '@babel/eslint-parser'

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
        // Navigateur (Web APIs) — avec no-undef réactivé, tout usage doit
        // être déclaré ici ou importé (issue #2481).
        window: 'readonly',
        document: 'readonly',
        navigator: 'readonly',
        console: 'readonly',
        localStorage: 'readonly',
        sessionStorage: 'readonly',
        confirm: 'readonly',
        alert: 'readonly',
        prompt: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        requestAnimationFrame: 'readonly',
        URL: 'readonly',
        Blob: 'readonly',
        EventSource: 'readonly',
        CustomEvent: 'readonly',
        // Environnement de build
        process: 'readonly',
        module: 'readonly',
        require: 'readonly',
        __dirname: 'readonly',
        // Macros compilateur Vue (<script setup>) — auto-importées par le
        // compilateur, jamais déclarées dans le script source.
        defineProps: 'readonly',
        defineEmits: 'readonly',
        defineExpose: 'readonly',
        withDefaults: 'readonly',
        defineModel: 'readonly',
        defineOptions: 'readonly',
        defineSlots: 'readonly',
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
      // 'no-undef' réactivé (issue #2481)
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
      },
    },
  },
]
