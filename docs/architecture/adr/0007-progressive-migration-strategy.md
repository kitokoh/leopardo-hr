# ADR 0007 - Stratégie de migration progressive

## Statut

Acceptee.

**Date** : 2026-06-27

## Contexte

On ne peut pas migrer 100 000 lignes de code en un seul déploiement sans risque.

## Décision

Migration en 3 étapes :

### Étape 1 — Skeleton (FAIT ✅)
Créer la nouvelle structure sans supprimer les anciens fichiers.
- Copier les fichiers dans les nouveaux emplacements
- Mettre à jour les namespaces dans les copies
- Les originaux restent fonctionnels

### Étape 2 — Câblage ✅ FAIT
Toutes les routes `routes/modules/*.php` et `routes/api.php` utilisent les namespaces modules.
Zéro import `App\Http\Controllers\Api\V1\*` dans les routes.

### Étape 3 — Nettoyage ✅ FAIT (PR #824 — 2026-07-01)
- 90 controllers `app/Http/Controllers/Api/V1/` supprimés
- 26 services `app/Services/` supprimés
- 51 fichiers consommateurs mis à jour
- Restent intentionnellement : `EdgeController`, `EdgeDownloadController`, `SSO/SSOController`

## Ce qui reste

Voir `api/ARCHITECTURE.md` section **TODO restants** pour la liste priorisée.
Principalement : migration `app/Models/` (75 doublons) et `app/Shared/` (Traits/Enums).
