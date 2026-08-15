# Mini-spec — Issue #3735

## Problème

Le sélecteur de langue de la vitrine mettait à jour `localStorage`, `<html lang/dir>` et le contexte client, mais pas l’URL. Une page copiée ou partagée perdait donc la langue choisie, malgré les alternates et la lecture SSR déjà prévues autour de `?lang=`.

## Contrat

| Action | Résultat |
|---|---|
| Changer de langue sur desktop | `?lang=<locale>` est remplacé dans l’URL sans rechargement ni perte des autres paramètres |
| Changer de langue sur mobile | même contrat que desktop |
| Paramètre `lang` déjà présent | sa valeur est remplacée, sans doublon |
| Autres paramètres | conservés dans leur ordre logique par `URLSearchParams` |
| RTL | le changement continue de passer par `useVitrineLocale().setLocale`, qui applique `dir=rtl` pour `ar` |

## Implémentation

`Navbar.tsx` utilise l’App Router Next.js et `router.replace(..., { scroll: false })`. Le helper `buildLocaleUrl` centralise la construction sûre de l’URL et est partagé par les deux sélecteurs desktop/mobile.

## Validation

Un test Vitest couvre le remplacement de `lang`, l’ajout initial du paramètre et la conservation des query params. La vérification TypeScript locale est limitée par l’absence de `vitest` dans les dépendances installées du sandbox ; la CI du dépôt installe les dépendances verrouillées.
