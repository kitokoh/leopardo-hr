// Configuration jest en CommonJS (setup canonique Next.js).
//
// Elle était en TypeScript (`jest.config.ts`). Jest ne sait lire une config
// `.ts` que via `ts-node` — ou grâce au support TypeScript **natif** de
// Node ≥ 22.18. Le job CI « Frontend — ESLint + TypeScript » tourne sur
// Node 20 : `npx jest` y échouait donc immédiatement avec
// « 'ts-node' is required for the TypeScript configuration files », alors que
// la même commande passait sur Node 24 en local.
//
// Le passage en CJS supprime cette dépendance à la version de Node (et à
// `ts-node`) : la suite s'exécute désormais à l'identique sur Node 20 et 24.
const nextJest = require('next/jest.js');

const createJestConfig = nextJest({
  // Provide the path to your Next.js app to load next.config.js and .env files in your test environment
  dir: './',
});

// Add any custom config to be passed to Jest
/** @type {import('jest').Config} */
const config = {
  coverageProvider: 'v8',
  testEnvironment: 'jsdom',
  // Add more setup options before each test is run
  setupFilesAfterEnv: ['<rootDir>/jest.setup.tsx'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
  testMatch: [
    '**/__tests__/**/*.[jt]s?(x)',
    '**/?(*.)+(spec|test).[jt]s?(x)',
  ],
  testPathIgnorePatterns: [
    '<rootDir>/e2e/',
  ],
  collectCoverageFrom: [
    'src/**/*.{js,jsx,ts,tsx}',
    '!src/**/*.d.ts',
    '!src/**/*.stories.{js,jsx,ts,tsx}',
    '!src/**/__tests__/**',
  ],
};

// createJestConfig is exported this way to ensure that next/jest can load the Next.js config which is async
module.exports = createJestConfig(config);
