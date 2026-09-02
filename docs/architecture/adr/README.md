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
| [0008](0008-payment-consent-signature-model.md) | Acceptee | Modele de consentement/signature de paiement, sans PKI premature |
| [0009](0009-ai-agent-tool-contracts-and-human-validation.md) | Acceptee | Contrats d'outils pour l'agent IA : permissions RBAC, audit et validation humaine avant toute ecriture |
| [0010](0010-marketplace-plugin-permissions-billing-webhooks.md) | Acceptee | Plugins marketplace : scopes Sanctum existants, gating via FeaturePlanMatrix, revenu via Partner/Commission, webhooks via AVAILABLE_EVENTS |
| [0013](0013-notifications-read-path-unification.md) | Proposee | Notifications in-app : read-path unifie sur `app_notifications`, migration du canal historique `notifications` (3 etapes) |
| [0011](0011-billing-payroll-domain-boundary.md) | Acceptee | Billing/Payroll : frontiere de domaine — casse la dependance circulaire (regles de facturation dans Billing, calculs de paie dans Payroll) |
| [0012](0012-focus-core-depth-peripheral-maintenance.md) | Proposee | Programme FOCUS : profondeur du noyau (HR/Payroll/Attendance), maintenance du peripherique |
| [0014](0014-plan-pricing-canonical-decisions.md) | Acceptee | Plans tarifaires canoniques, plan Free public, duree d'essai |
| [0015](0015-onboarding-steps-canonical.md) | Proposee | Onboarding : 6 etapes seedees canoniques (dont optionnelles), Quick Start < 15 employes |
| [0017](0017-paiement-en-ligne-portail-client.md) | Proposee | Paiement en ligne des factures (portail client) : passerelle(s) et architecture |
| [0016](0016-attendance-smartattendance-fusion.md) | Proposee | Fusion progressive Attendance + SmartAttendance en un module unique (5 phases, zero perte de donnees, contrat API preserve) |

## Format

Chaque ADR contient :

- Contexte
- Decision
- Consequences
- Regles operationnelles
