# Mini-spécification — Issue #3001

## Objectif

Empêcher le cockpit super-admin de présenter des zéros, des listes vides ou des rapports vides comme des données fiables lorsque la base de données, Redis ou une table source est indisponible.

## Constat

Les helpers de `PlatformAdminDashboardController`, `MetricsController` et `PlatformHrReportController` capturaient `Throwable` sans variable ni log, puis renvoyaient `0`, `[]` ou `null`. Une panne de datasource devenait donc invisible pour le cockpit et impossible à diagnostiquer.

## Contrat d’erreur

Chaque catch ciblé journalise l’opération et l’exception avec `Log::error`, puis remonte une `HttpException(503)` explicite. Les endpoints restent capables de distinguer une réponse métier vide d’une indisponibilité technique. Aucun message interne d’exception n’est exposé au client.

## Périmètre

- Toutes les branches de `PlatformAdminDashboardController`, y compris stats, activités, alertes, Redis et requêtes cross-tenant.
- Les compteurs de `MetricsController`.
- Les cinq rapports de `PlatformHrReportController`.

## Critères d’acceptation

1. Aucun `catch (\\Throwable)` sans variable ne subsiste dans les trois contrôleurs.
2. Chaque catch ciblé écrit un événement `error` avec l’opération et l’exception.
3. Une défaillance de datasource produit HTTP 503 au lieu d’un faux zéro, d’une liste vide ou d’un rapport vide.
4. Les réponses nominales et les formats JSON existants restent inchangés.
5. Les fichiers modifiés passent `php -l` et `git diff --check`.

## Plan de retour arrière

Réversion du commit. Le changement est limité aux chemins d’erreur et n’altère aucun schéma ni donnée persistée.

## Trace Spec Kit

Issue : #3001  
Branche : `fix/3001-admin-catch-logging`  
Date : 2026-08-15
