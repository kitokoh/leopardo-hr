# ADR 0002 - Contrats API OpenAPI

## Statut

Acceptee.

## Contexte

Les surfaces backend sont consommees par le mobile Flutter, l'admin-dashboard, le web manager, les integrateurs futurs et les workflows de monitoring. Sans contrat canonique, les clients peuvent reintroduire des endpoints fictifs ou divergents.

## Decision

`api/openapi.yaml` est la specification canonique des endpoints exposes. Elle est validee par `OpenAPI CI` et publiee par le backend sur :

- `/docs` pour Swagger UI ;
- `/docs/openapi.yaml` pour la spec source.

## Consequences

Le backend ne doit pas dupliquer la specification dans un asset statique separe. Les changements d'endpoint doivent mettre a jour `api/openapi.yaml`, les scenarios de test API et, si necessaire, les tests Feature.

## Regles operationnelles

- Toute nouvelle route publique ou partenaire doit etre documentee dans OpenAPI.
- Les clients frontend doivent consommer les contrats reels, pas des routes inventees.
- La CI OpenAPI peut garder des warnings historiques, mais elle doit rester valide.
