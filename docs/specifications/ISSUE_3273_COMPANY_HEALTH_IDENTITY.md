# Mini-spécification — Issue #3273

## Objectif

Aligner le contrat de l’endpoint `/platform/companies/{company}/health` avec `CompanyDetailView`, qui affiche le slug et la date de création dans l’identité technique.

## Correction

`PlatformCompanyHealthService` ajoute `company.slug` et `company.created_at` au sous-payload `data.company`. La date est sérialisée en ISO 8601 comme les autres dates API. Le test de contrat health vérifie maintenant les deux valeurs contre la compagnie fixture.

## Critères d’acceptation

1. `data.company.slug` est présent et correspond à la compagnie demandée.
2. `data.company.created_at` est présent en ISO 8601.
3. Les autres métriques health restent inchangées.
4. Les fichiers PHP passent la vérification syntaxique et `git diff --check` passe.

## Trace Spec Kit

Issue : #3273  
Branche : `fix/3273-company-health-identity`  
Date : 2026-08-15
