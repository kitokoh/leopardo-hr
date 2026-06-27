# ADR-003 — Stratégie de migration progressive

**Date** : 2026-06-27
**Statut** : Accepted

## Audit de l'Application Layer (Juin 2026)

Un audit de la couche application a révélé plusieurs incohérences critiques dans les actions (`Application/Actions`) :
- **Appels de méthodes inexistantes** : Plusieurs actions appelaient des méthodes sur les services d'infrastructure qui n'existaient pas ou avaient des noms différents (ex: `SyncZKTeco` appelait `syncDevice` au lieu de `pushUsers`).
- **Incohérences de signatures** : Les paramètres passés par les actions ne correspondaient pas aux signatures réelles des services (arguments manquants, types incorrects).
- **Mélange de types (Models/DTOs)** : Utilisation de modèles/DTOs de modules là où les services d'infrastructure (copies de legacy) attendent des types `App\Models` ou `App\DTOs`.

**Correction apportée** : 11 actions ont été refactorisées pour être fonctionnellement correctes et alignées avec les contrats de services réels. Le namespace du `CheckInDTO` a également été corrigé pour assurer la cohérence interne du module Attendance.

## Contexte

On ne peut pas migrer 100 000 lignes de code en un seul déploiement sans risque.

## Décision

Migration en 3 étapes :

### Étape 1 — Skeleton (FAIT ✅)
Créer la nouvelle structure sans supprimer les anciens fichiers.
- Copier les fichiers dans les nouveaux emplacements
- Mettre à jour les namespaces dans les copies
- Les originaux restent fonctionnels

### Étape 2 — Câblage (À FAIRE)
Faire pointer les routes vers les nouveaux controllers.
- Modifier `api/routes/modules/*.php` pour utiliser les nouveaux namespaces
- Écrire les tests unitaires pour chaque module
- Valider CI vert

### Étape 3 — Nettoyage (APRÈS CI VERT)
Supprimer les fichiers originaux une fois les tests validés.
- Utiliser `scripts/cleanup-legacy.sh --module HR`
- Un module à la fois, CI validé après chaque suppression

## Règle de sécurité

**Jamais de suppression sans CI vert.**
Le script `cleanup-legacy.sh --dry-run` doit être exécuté avant tout `git rm`.
