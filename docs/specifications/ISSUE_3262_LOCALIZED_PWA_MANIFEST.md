# Mini-spécification — Issue #3262

## Objectif

Rendre l’expérience PWA cohérente avec les quatre locales de la vitrine et éviter de présenter aux visiteurs un lien Edge local non configuré.

## Correction

Le manifest statique français est remplacé par la route dynamique `/manifest`. Elle choisit la copie FR/EN/TR/AR à partir de `Accept-Language`, renseigne `lang` et `dir`, et localise le nom, la description et les quatre raccourcis PWA. Le layout référence cette route.

`PWAProvider` localise le message de notification de mise à jour avec la préférence utilisateur existante. La page offline n’affiche le bloc Edge que si `NEXT_PUBLIC_EDGE_NODE_URL` est explicitement défini ; le lien `http://leopardo.local` est supprimé.

## Critères d’acceptation

1. `/manifest` répond dynamiquement avec une copie adaptée à `Accept-Language`.
2. Le manifest expose `lang` et `dir`, notamment `dir=rtl` pour l’arabe.
3. Les raccourcis PWA sont localisés en FR, EN, TR et AR.
4. La notification PWA n’est plus française en dur.
5. Le lien `leopardo.local` disparaît et le bloc Edge est masqué sans configuration.
6. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3262  
Branche : `fix/3262-localized-pwa-manifest`  
Date : 2026-08-15
