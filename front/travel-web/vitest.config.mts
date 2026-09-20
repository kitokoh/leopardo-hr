import path from "node:path";

import { defineConfig } from "vitest/config";

// Issue #7841 — première suite de tests unitaires de travel-web (les route
// handlers d'auth posent/suppriment le cookie httpOnly de session : logique
// sensible qui doit être verrouillée). Environnement node : on teste des
// route handlers Next, pas de DOM.
export default defineConfig({
  test: {
    environment: "node",
    include: ["src/**/*.test.ts"],
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
});
