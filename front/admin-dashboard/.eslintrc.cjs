// Minimal Vue ESLint setup for CI signal without legacy bulk cleanup.
module.exports = {
  root: true,
  env: {
    browser: true,
    es2022: true,
    node: true,
  },
  ignorePatterns: ['dist/', 'node_modules/', 'playwright-report/', 'test-results/'],
  extends: ['eslint:recommended', 'plugin:vue/vue3-essential'],
  parser: 'vue-eslint-parser',
  parserOptions: {
    parser: '@babel/eslint-parser',
    ecmaVersion: 'latest',
    sourceType: 'module',
    requireConfigFile: false,
  },
  overrides: [
    {
      files: ['e2e/**/*.js', 'playwright.config.js'],
      env: {
        node: true,
      },
    },
  ],
  rules: {
    'no-console': 'off',
    'no-eval': 'error',
    'no-implied-eval': 'error',
    'no-new-func': 'error',
    'no-script-url': 'error',
    'no-undef': 'off',
    'no-unused-vars': 'warn',
    'vue/no-mutating-props': 'warn',
    'vue/no-v-html': 'error',
    'vue/no-side-effects-in-computed-properties': 'warn',
    'vue/require-toggle-inside-transition': 'warn',
    'vue/multi-word-component-names': 'off',
  },
}
