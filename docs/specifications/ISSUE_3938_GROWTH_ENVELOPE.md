# Mini-spec — Issue #3938

## Problème

`GrowthDashboardView.vue` déballe l'enveloppe API de trois façons dans la même
fonction : `.data.data` pour `/platform/growth/partners` et `/payouts`, mais
`.data.audit_logs` pour `/history` (contrat réel `GrowthAdminController` :
`{commissions, audit_logs}`). Le code actuel est correct pour le contrat
d'aujourd'hui, mais fragile : si le backend uniformise l'enveloppe,
`auditLogs` devient `undefined` → TypeError au rendu (`v-for` + `.length === 0`).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| 3 tabs (partenaires/payouts/audit) | Rendus sans erreur avec la vraie réponse API |
| Contrat backend modifié (enveloppe standard) | Plus de crash — liste vide propre |
| lint + build admin | 0 erreur |

## Correctif

Déballage normalisé avec garde `Array.isArray(...) ?? []` sur les trois appels ;
commentaire documentant le contrat réel (GrowthAdminController).

## Validation

`npm run lint` + `npm run build` verts (admin-dashboard).

Closes #3938
