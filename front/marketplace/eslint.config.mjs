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
      "public/**",
    ],
  },
  // eslint-config-next@16 fournit des flat configs prêtes à l'emploi.
  ...nextCoreWebVitals,
  {
    // eslint-plugin-react en mode "detect" plante sous ESLint 10 flat config
    // (pas de context.getFilename()) — version épinglée explicitement,
    // même décision que front/web.
    settings: {
      react: { version: "19.2.8" },
    },
  },
];
