## Plan technique
1. Corriger les 3 appels API dans `ChatView.vue` (conversations + messages) et `MarketingOAuthView.vue` (PUT oauth-config) vers les chemins `/v1/admin/...`.
2. Vérifier payload/headers attendus par les contrôleurs (lire les contrôleurs backend).
3. Vérifier `normalizeApiPath` (services/api.js) pour les chemins `/v1/admin/...`.
4. Lint + build. CHANGELOG.
