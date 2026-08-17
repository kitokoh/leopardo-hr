# Spec Kit — Synchronisation des catalogues I18N générés

## Contexte

Le workflow `I18N Enterprise` échouait sur `main` lors de l’étape `git diff --exit-code`. Les catalogues source partagés étaient valides, mais les quatre fichiers Flutter générés de `leopardo_core` n’étaient plus synchronisés avec la source après l’ajout de clés de rapports, notifications et employés.

## Objectif

Rétablir une génération déterministe et versionnée des catalogues `app_{ar,en,fr,tr}.arb`, sans changer le catalogue source ni introduire de chaîne anglaise non traduite dans les locales FR, TR ou AR.

## Périmètre

- Régénérer `front/mobile_apps/leopardo_core/lib/l10n/app_ar.arb`.
- Régénérer `front/mobile_apps/leopardo_core/lib/l10n/app_en.arb`.
- Régénérer `front/mobile_apps/leopardo_core/lib/l10n/app_fr.arb`.
- Régénérer `front/mobile_apps/leopardo_core/lib/l10n/app_tr.arb`.
- Ne modifier aucun code d’interface ou logique métier.

## Critères d’acceptation

- `node shared/i18n/validators/validate.js` retourne `I18N_VALIDATION_OK (4 locales)`.
- Les scripts `sync-mobile.js`, `sync-web.js` et `sync-backend.js` réussissent.
- Une seconde exécution des scripts ne produit aucun diff.
- Les quatre fichiers ARB sont des JSON valides et contiennent chacun 834 entrées.
- Le workflow `I18N Enterprise` termine avec `git diff --exit-code` vert.

## Validation locale

Validation et synchronisation exécutées localement sur le commit courant ; `git diff --check` est vert. La validation distante GitHub reste nécessaire avant fusion.
