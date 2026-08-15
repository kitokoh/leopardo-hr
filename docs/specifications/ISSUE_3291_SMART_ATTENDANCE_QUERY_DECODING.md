# Mini-spécification — Issue #3291

## Objectif

Rendre le chargement des sessions Smart Attendance robuste face au contrat Laravel paginé et séparer le chemin HTTP des paramètres de requête.

## Correction

Les repositories manager et HR appellent `/smart-attendance/sessions` avec `queryParameters` (`status=pending_validation`, `per_page=50`) et décodent la réponse avec `extractDataList`, qui gère l’enveloppe `data` et les formes de pagination communes. Le cast direct de `raw['data'] as List` est supprimé.

## Critères d’acceptation

1. Le path ne contient plus la query string.
2. Les deux filtres sont transmis par `queryParameters`.
3. Le décodage accepte une enveloppe paginée sans cast direct fragile.
4. `git diff --check` et l’audit statique passent.

## Trace Spec Kit

Issue : #3291  
Branche : `fix/3291-smart-attendance-query-decoding`  
Date : 2026-08-15
