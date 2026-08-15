# Mini-spec — Issue #3733

## Problème

`front/web/src/modules/vitrine/lib/seo-metadata.ts` n’avait aucun import dans `src/` et dupliquait des métadonnées déjà fournies par `seo.ts`, avec un risque de divergence SEO silencieuse.

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Import de `seo-metadata` dans `front/web/src` | Aucun résultat |
| Source de `pageMetadata` | `modules/vitrine/lib/seo.ts` uniquement |
| Build/TypeScript | Aucun import cassé après suppression |
| URLs canoniques/OG | Inchangées dans la source active |

## Correctif

Le fichier mort `seo-metadata.ts` est supprimé après vérification de l’absence de référence. `seo.ts` reste la source unique active pour les helpers et entrées SEO.

## Validation

`rg 'seo-metadata' front/web/src` ne retourne aucune référence et `git diff --check` passe localement. La CI frontend validera le build complet.
