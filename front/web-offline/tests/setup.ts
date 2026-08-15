import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// globals:false → l'auto-cleanup de RTL n'est pas enregistré par Vitest ;
// sans cleanup entre les tests, les composants précédents restent montés
// (doublons de textes/rôles, états fantômes).
afterEach(() => {
  cleanup();
});
