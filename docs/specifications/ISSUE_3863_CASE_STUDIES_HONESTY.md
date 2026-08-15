# Mini-spécification — Issue #3863

## Objectif

Rendre honnêtes et localisées les études de cas de la vitrine : les 3 case-studies (TechCorp Algérie, Atlas Industries, LogiTrans Express) sont des exemples illustratifs publiés comme de vrais clients, avec une métrique non sourcée (« Rejoignez 500+ entreprises »).

## Correction

1. **Bandeau de transparence** : bannière « Étude illustrative — données fictives » au-dessus des case-studies, libellée via `vitrine-locale.ts` (4 locales FR/EN/TR/AR).
2. **Badge par carte** : badge « Étude illustrative » à côté du badge secteur sur chaque carte.
3. **Métrique inventée supprimée** : le CTA final « Rejoignez 500+ entreprises qui ont choisi Leopardo RH » est remplacé par une formulation vérifiable (« Découvrez Leopardo RH avec un essai gratuit de 14 jours »).
4. **Coquille de page localisée** : hero et CTA passent par le catalogue (`copy.caseStudies`) — aucune nouvelle chaîne FR en dur (`check-i18n-diff.js` vert).
5. Contenu des études (company, challenge, solution, résultats) : inchangé (dette #3248 suivie séparément).

## Critères d'acceptation

1. Mention démo visible sur `/case-studies` dans les 4 locales.
2. Plus aucune métrique de clientèle non sourcée.
3. `npm run lint`, `npx tsc --noEmit`, `jest` vitrine et `check-i18n-diff.js` verts.

## Trace Spec Kit

Issue : #3863
Branche : `fix/3863-case-studies-honesty`
Date : 2026-08-15
