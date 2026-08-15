# Mini-spécification — Issue #3286

## Objectif

Empêcher qu’une réponse perdue provoque une seconde exécution d’une mutation mobile déjà acceptée par le serveur : création de compte, Google Sign-In, génération IA, transcription/synthèse vocale ou publication marketing.

## Correction

`ApiClient.requestWithRetry` ne réessaie automatiquement que les méthodes HTTP idempotentes (`GET`, `HEAD`, `OPTIONS`, `PUT`, `DELETE`). Les mutations (`POST`, `PATCH` et toute méthode inconnue) effectuent une seule tentative par défaut. Les appels sensibles explicitement identifiés ajoutent également `maxRetriesOverride: 0` comme défense locale.

Les lectures GET conservent les retries de cold start/réseau. Les appels `PUT` et `DELETE` conservent leur retry car leur sémantique HTTP est idempotente ; un appelant peut toujours fournir une politique explicite lorsque le contrat serveur garantit l’idempotence.

## Critères d’acceptation

1. Un POST/PATCH ne retente plus automatiquement après 502/503/504 ou timeout.
2. Register et Google Sign-In ont une protection explicite contre les retries.
3. AI chat, AI Voice et publication marketing ont une protection explicite contre les retries.
4. Les lectures GET gardent la politique de retry existante.
5. `git diff --check` et l’audit statique des call-sites passent.

## Trace Spec Kit

Issue : #3286  
Branche : `fix/3286-no-retry-non-idempotent`  
Date : 2026-08-15
