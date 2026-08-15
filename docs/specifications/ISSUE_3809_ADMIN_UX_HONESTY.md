# Mini-spec — Issue #3809

## Problème

Audit 360° 2026-08-15 (expert QA), cockpit `front/admin-dashboard` :

1. `LogoutView.vue` — barre de progression factice : `setTimeout(() => progress = 100, 100)`
   indépendant de l'état réel du logout, puis redirection après un délai fixe
   arbitraire de 3,2 s (`router.push('/login')`). L'utilisateur voit une
   « sécurisation » simulée même si la session n'a pas été purgée.
2. `ChatView.vue` — le composer est désactivé avec un état « indisponible »
   (contrat backend 501 `ADMIN_CHAT_UNAVAILABLE`, #3390) mais `chatUnavailable`
   restait `ref(false)` : l'état honnête n'était JAMAIS affiché, le composer
   semblait simplement cassé sans explication.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Progression LogoutView | 35 % pendant la purge locale → 100 % uniquement après purge réelle |
| Redirection | Événementielle (fin de purge + court settle visuel), plus de 3,2 s fixes ; `router.replace` (pas de retour historique dans l'app) |
| ChatView | Bannière « Chat IA plateforme indisponible » visible par défaut (501) |
| `npm run lint` admin | 0 warning |
| `npm run build` admin | vert |

## Correctif

- `LogoutView.vue` : progression pilotée par l'état réel (`finally` de
  `authStore.logout()` → 100 %), redirection `replace('/login')` après 350 ms de
  settle, libellé selon l'état (`Fermeture de session...` → `Session sécurisée`).
- `ChatView.vue` : `chatUnavailable = ref(true)` (le contrat backend est 501
  tant que le service n'est pas branché) — la bannière honnête s'affiche.

## Validation

`npm run lint` et `npm run build` admin verts localement ; CI `Web CI - Leopardo
Vitrine` (admin-dashboard) en garde.

Closes #3809
