import tseslint from "typescript-eslint";
import nextCoreWebVitals from "eslint-config-next/core-web-vitals";

// Même socle que front/web (eslint-config-next flat + typescript-eslint),
// sans les assouplissements : le code de cette app est neuf, il reste strict.
export default [
  {
    ignores: [
      ".next/**",
      "out/**",
      "build/**",
      "next-env.d.ts",
      "node_modules/**",
      ".git/**",
      "dist/**",
      "public/**",
    ],
  },
  ...nextCoreWebVitals,
  {
    settings: {
      react: { version: "19.2.8" },
    },
  },
  {
    // eslint-plugin-react-hooks v7 : mêmes diagnostics « React Compiler
    // readiness » désactivés que front/web (décision #1306) — rules-of-hooks
    // et exhaustive-deps restent actifs.
    rules: {
      "react-hooks/purity": "off",
      "react-hooks/immutability": "off",
      "react-hooks/set-state-in-effect": "off",
      "react-hooks/set-state-in-render": "off",
      "react-hooks/gating": "off",
      "react-hooks/refs": "off",
      "react-hooks/config": "off",
      "react-hooks/globals": "off",
      "react-hooks/error-boundaries": "off",
      "react-hooks/static-components": "off",
      "react-hooks/use-memo": "off",
      "react-hooks/preserve-manual-memoization": "off",
      "react-hooks/incompatible-library": "off",
    },
  },
  ...tseslint.configs.recommended,
];
