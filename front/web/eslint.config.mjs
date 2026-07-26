import tseslint from "typescript-eslint";
import nextCoreWebVitals from "eslint-config-next/core-web-vitals";

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
      "e2e/**",
      "public/**",
    ],
  },
  // eslint-config-next is declared as a dependency but was previously never
  // imported here, so Next.js/React hooks/accessibility rules were silently
  // never applied despite `npm run lint` reporting 0 errors/warnings.
  // eslint-config-next@16+ ships ready-to-use flat config arrays, so no
  // FlatCompat shim is needed.
  ...nextCoreWebVitals,
  {
    // eslint-plugin-react (pulled in transitively by eslint-config-next) reads
    // the installed React version to enable/disable some legacy-API rules.
    // Its "detect" mode crashes under ESLint 10's flat-config RuleContext
    // (no context.getFilename()), so pin the version explicitly instead.
    settings: {
      react: { version: "19.2.8" },
    },
  },
  {
    // eslint-plugin-react-hooks v7 (pulled in by eslint-config-next@16) adds a
    // new family of React Compiler "readiness" diagnostics (purity,
    // immutability, set-state-in-effect, incompatible-library, etc.) and
    // defaults them to "error". These flag long-standing, otherwise correct
    // Next.js patterns across this codebase (data-fetching useEffect +
    // setState, `window.location.href = ...` navigation, `Math.random()` for
    // non-crypto DOM ids, React Hook Form's `watch()`, etc.). Fixing all of
    // them would require a much larger, dedicated refactor unrelated to this
    // issue's scope (wiring up eslint-config-next itself), so per the
    // fallback documented in issue #1306 this is a deliberate, explicit
    // decision to disable only these specific React-Compiler-readiness
    // rules for now rather than let them silently block `npm run lint`
    // (which runs with --max-warnings 0). `rules-of-hooks` and
    // `exhaustive-deps` — the rules that catch real bugs — remain enabled at
    // their configured severity. Revisit as a dedicated follow-up if/when
    // the codebase adopts the React Compiler.
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
  {
    files: ["src/**/*.{ts,tsx}"],
    rules: {
      "@typescript-eslint/no-explicit-any": "off",
      "@typescript-eslint/no-unused-vars": "off",
      "@typescript-eslint/no-require-imports": "off",
    },
  },
];
