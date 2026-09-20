# Spec Kit — Budget cold start du smoke observability

## Contexte

Le smoke `Launch Observability Smoke` utilisait un budget de 2500 ms par probe. Render répondait correctement avec HTTP 200, mais les cold starts de l’API et de l’administration dépassaient parfois ce seuil, produisant des faux rouges et masquant le véritable statut fonctionnel.

## Objectif

Conserver une assertion HTTP stricte tout en donnant au service Render un budget explicite de cold start. La latence reste enregistrée dans le rapport JSON et peut toujours être abaissée manuellement avec l’input `max_latency_ms`.

## Changement

Le budget par défaut passe à 10000 ms dans le workflow et le script. Les commentaires précisent que ce budget inclut le cold start Render. Une réponse non-200 continue d’échouer immédiatement, indépendamment de la tolérance de latence.

## Critères d’acceptation

- Le script passe en syntaxe Bash.
- Les sept probes publiques retournent HTTP 200 et `failed: 0` dans le budget de 10000 ms.
- Le rapport conserve `max_latency_ms` et `latency_ms` pour chaque probe.
- Un dépassement explicite fourni par `max_latency_ms` continue de faire échouer le smoke.
- Aucun secret, URL de production ou variable Render n’est modifié.

## Validation réalisée

Le smoke public a passé 7/7 checks avec des latences API observées entre 2,75 s et 5,93 s et une latence admin de 2,82 s.
