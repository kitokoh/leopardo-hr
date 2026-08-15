# Mini-spec — Issue #3932

## Problème

`realtimeStore.pushUnavailable` est référencé dans 3 templates
(`SystemAlertsOverlay.vue:45`, `Header.vue:41,45`) mais **jamais défini** dans
`stores/realtime.js` (grep : zéro affectation) → toujours `undefined`/falsy.
Résultat : l'état neutre « push non configuré » (gris, #3269) est
inatteignable ; dès que `isConnected=false` (cas par défaut d'un super-admin
sans serveur WS), la bannière rouge « Connexion temps réel perdue » s'affiche
en permanence alors que le fallback polling (PA2-COMM-013) fonctionne.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| `connect_error` (WS injoignable) | `pushUnavailable=true` → état neutre gris, pas de fausse alerte |
| Grace timeout 8 s sans connexion | `pushUnavailable=true` + polling de secours |
| Connexion WS établie | `pushUnavailable=false`, `isConnected=true` |
| Nouvel essai de connexion | `pushUnavailable` réinitialisé (state unknown) |
| lint + build admin | 0 erreur |

## Correctif

`realtime.js` : nouvel état `pushUnavailable` (ref false), réinitialisé à
chaque `connect()`, passé à `true` sur `connect_error` et sur le grace timeout,
repasé à `false` à la connexion ; exporté dans le store. Templates inchangés.

## Validation

`npm run lint` + `npm run build` verts (admin-dashboard). Comportement WS
réel : vérification manuelle en runtime (pas de serveur WS dans la sandbox).

Closes #3932
