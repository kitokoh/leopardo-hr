# Architecture Decision Records

Ce dossier capture les decisions structurantes qui doivent rester stables pour Leopardo RH. Une ADR est ajoutee quand un choix influence la securite, l'exploitation, le multi-tenant, les contrats API ou les couts de changement futurs.

## Registre

| ADR | Statut | Decision |
|---|---|---|
| [0001](0001-multi-tenant-postgresql.md) | Acceptee | Multi-tenant PostgreSQL avec schemas separes et mode shared controle |
| [0002](0002-api-contracts-openapi.md) | Acceptee | OpenAPI canonique dans `api/openapi.yaml`, publie via Swagger UI |
| [0003](0003-ci-github-actions-source-of-truth.md) | Acceptee | GitHub Actions est la source de verite de validation |
| [0004](0004-open-core-marketplace-boundaries.md) | Acceptee | Open core cadre, marketplace via contrats publics et enterprise core prive |
| [0005](0005-clean-architecture-modules.md) | Acceptee | Adoption de la Clean Architecture / decoupage en modules DDD sous `api/app/Modules/` |
| [0006](0006-auth-in-core.md) | Acceptee | Auth dans `app/Core/Auth/` plutot qu'un module, pour eviter les dependances circulaires |
| [0007](0007-progressive-migration-strategy.md) | Acceptee | Strategie de migration progressive flat -> modules DDD (skeleton, cablage, nettoyage) |

## Format

Chaque ADR contient :

- Contexte
- Decision
- Consequences
- Regles operationnelles
