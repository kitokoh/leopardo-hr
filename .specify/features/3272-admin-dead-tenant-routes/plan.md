# Plan — #3272

1. `src/router/index.js`
   - Retirer les blocs route `/payroll`, `/leaves`, `/contracts`,
     `/recruitment`, `/reports`, `/predictions`, `/audit`.
   - Retirer `requiresTenant: true` des metas `/training`, `/chat`, `/webhooks`.
2. `src/components/layout/Sidebar.vue` — retirer les 7 entrées nav mortes +
   icônes heroicons devenues orphelines.
3. `src/components/common/CommandPalette.vue` — retirer l'entrée
   `/predictions`, actualiser les commentaires de garde.
4. `src/composables/useKeyboardShortcuts.js` — retirer le raccourci Alt+R
   (recrutement) et son aide.
5. Supprimer `src/views/{payroll,leaves,contracts,recruitment,reports,predictions,audit}/`.
6. CHANGELOG [Unreleased] + doc `docs/specifications/ISSUE_3272.md`.

Vérification : lint/build `web-ci.yml` (GitHub Actions source de vérité) ;
aucune référence résiduelle (`rg`) aux vues supprimées.
