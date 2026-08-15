# Mini-spec — Issue #3717

## Intention
Rétablir le polling du guided trial en acceptant les `provisioning_token` réellement émis par Laravel `Str::random(64)`.

## Contrat
Le token est une chaîne de **64 caractères base62 sensibles à la casse** : `A-Z`, `a-z` et `0-9`. Les valeurs vides, de longueur différente ou contenant un caractère hors alphabet sont rejetées avant tout appel backend.

## Implémentation
La validation est centralisée dans `src/app/api/forms/_lib/provisioning-token.ts` et utilisée par la route same-origin `src/app/api/forms/trial-status/route.ts`. Le regex hexadécimal minuscule qui rejetait presque tous les tokens réels est supprimé.

## Critères d’acceptation

| Scénario | Résultat attendu |
|---|---|
| Token base62 de 64 caractères | Accepté et transmis au backend |
| Token de 63 ou 65 caractères | Rejeté avec le contrat `PROVISIONING_TOKEN_INVALID` |
| Token contenant `-`, espace ou autre caractère non base62 | Rejeté |
| Token vide | Rejeté |
| Réponse backend | Contrat et comportement de proxy inchangés |

## Validation

Le test Jest dédié passe avec 3 scénarios et `npx tsc --noEmit` passe sans erreur.
