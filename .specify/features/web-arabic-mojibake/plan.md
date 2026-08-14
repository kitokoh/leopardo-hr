## Plan technique
1. Pour chaque fichier touché : réparer les chaînes arabes (décoder proprement — utiliser python `encode('latin-1').decode('utf-8')` sur le contenu des chaînes ou les réécrire).
2. Vérifier aussi les autres locales pour du mojibake visible (FR/EN/TR).
3. Ajouter un garde : script `scripts/check-mojibake.mjs` (node) ou test Jest qui scanne `src/**` pour les patterns interdits ; le brancher sur la CI web (web-marketing-ci.yml) ou au minimum dans package.json.
4. Lint + build. CHANGELOG.
