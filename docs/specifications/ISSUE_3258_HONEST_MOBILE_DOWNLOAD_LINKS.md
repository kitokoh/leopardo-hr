# Mini-spécification — Issue #3258

## Objectif

Éviter que les boutons Google Play/App Store redirigent silencieusement vers `/signup` tout en affichant un libellé de store, et fournir un fallback explicite pour iOS comme pour Android.

## Contrat de résolution

`mobileDownloadTarget` conserve l’ordre : URL store configurée, lien Firebase App Distribution pour testeurs, puis `/signup?source=download_*`. `mobileDownloadLabel` expose le statut réel : libellé store configuré, installation de la version test ou inscription à la liste des testeurs.

## Correction

Les `aria-label` de la page Download utilisent désormais le libellé résolu plutôt que « Google Play » ou « App Store » en dur. Le texte visible et l’accessibilité indiquent donc honnêtement lorsqu’un lien store n’est pas encore configuré, y compris pour iOS.

## Critères d’acceptation

1. Aucun fallback `/signup` n’est annoncé comme un store réel.
2. Android et iOS partagent le même contrat de fallback.
3. Les liens Firebase sont identifiés comme builds testeurs.
4. Les URL store configurées continuent d’utiliser leur libellé d’origine.
5. Lint, build et `git diff --check` passent.

## Trace Spec Kit

Issue : #3258  
Branche : `fix/3258-honest-mobile-download-links`  
Date : 2026-08-15
