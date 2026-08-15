# Mini-spécification — Issue #3257

## Objectif

Éviter de présenter Leopardo Desktop Windows/macOS comme un produit téléchargeable alors qu’aucun client desktop ni installateur public n’existe dans le dépôt.

## Correction

La page `/download` conserve un point d’entrée commercial, mais le transforme en **demande d’accès pilote**. Les titres, sous-titres, cartes Windows/macOS, FAQ et messages d’installation précisent qu’aucun installateur public n’est encore disponible. Les applications Android/iOS et le kiosk existant restent inchangés.

## Critères d’acceptation

1. Aucune locale ne promet un client Windows/macOS public ou inclus dans tous les plans.
2. Les CTA desktop conduisent à une demande de contact pilote explicite.
3. Les FAQ FR/EN/TR/AR décrivent honnêtement la disponibilité.
4. Les liens mobiles restent inchangés.
5. Le front passe lint/build et `git diff --check`.

## Plan de retour arrière

Réversion du commit ; aucun fichier binaire ni route publique n’est supprimé.

## Trace Spec Kit

Issue : #3257  
Branche : `fix/3257-honest-desktop-download-copy`  
Date : 2026-08-15
