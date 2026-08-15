## Plan technique
1. Lire `src/app/sitemap.ts` et le flag `env.enableBlog` (`src/modules/vitrine/lib/env.ts`).
2. Conditionner les entrées blog du sitemap sur `env.enableBlog` (et les posts via `getBlogPosts` seulement si activé).
3. Vérifier robots.txt.
4. Vérifier manuellement le sitemap généré (npm run build + start, fetch /sitemap.xml). Lint + build. CHANGELOG.
