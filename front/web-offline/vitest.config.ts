import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  test: {
    // jsdom est activé par pragma `// @vitest-environment jsdom` dans les
    // tests de composants (tests/page.test.tsx) — le reste tourne en node.
    environment: 'node',
    include: ['tests/**/*.test.{ts,tsx,js,mjs}'],
    globals: false,
    setupFiles: ['tests/setup.ts'],
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
});
