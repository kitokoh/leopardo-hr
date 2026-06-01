# Guide open core et marketplace Leopardo RH

## Objectif

Ce guide fixe les limites de l'ouverture future du produit. Il sert a eviter une ouverture prematuree du backend ou des apps mobiles tout en preparant un ecosysteme partenaire propre.

Le statut actuel est volontaire : **cadrage strategique, pas de publication open source immediate**.

## Ce qui peut devenir open source

| Surface | Condition |
|---|---|
| OpenAPI publique | Spec nettoyee, versionnee et deja servie par `/docs/openapi.yaml` |
| SDK exemples | Aucun secret, aucune donnee client, environnement sandbox par defaut |
| Collections API | Payloads demo anonymises et erreurs documentees |
| Connecteurs simples | Pas de logique tenant interne, pas de paie sensible, pas de cle embarquee |
| Composants UI generiques | Licence compatible, pas de branding client ni de donnees metier |

## Ce qui reste enterprise

- Backend Laravel multi-tenant, migrations, services, jobs, policies, RBAC et orchestration.
- Apps mobiles `leopardo_employee`, `leopardo_manager`, `leopardo_platform_admin`.
- Kiosk, admin-dashboard, workflows de distribution Firebase et CI/CD.
- Modules paie, avances, GPS, biometrie, QR onboarding, notifications, documents et performance.
- Pricing, billing, scoring, audit logs, support, monitoring et runbooks operations.
- Donnees demo riches, seeders clients, assets marketing premium et templates commerciaux.

## Secrets et donnees a isoler

Ne jamais exposer dans un depot public ou package partenaire :

- `.env`, tokens GitHub, Render, Vercel, Cloudflare, Firebase, Google Cloud, SMTP, SMS, WhatsApp.
- Fichiers `google-services.json`, `GoogleService-Info.plist`, service accounts ou certificats.
- Dumps SQL, exports clients, logs, backups, captures d'ecran contenant des emails ou numeros reels.
- Fixtures demo contenant des personnes identifiables.
- URLs internes non publiques, credentials demo non limites ou tokens Bearer.

## Licence et support

Avant toute publication :

1. Auditer les licences de dependances.
2. Definir la licence du package publie.
3. Ajouter un `SECURITY.md`, un `CONTRIBUTING.md` et une politique de support.
4. Separarer clairement community support et support enterprise.
5. Documenter ce qui est stable, experimental ou interne.

## Marketplace cible

Les extensions marketplace doivent passer par une API publique et des webhooks signes.

Un manifeste plugin devra declarer :

- `name`, `publisher`, `version`, `support_url`.
- Scopes API demandes.
- Webhooks ecoutes.
- Endpoints externes appeles.
- Donnees lues/ecrites.
- Politique de retention.
- Mode sandbox et mode production.

## Scopes API initiaux proposes

| Scope | Usage |
|---|---|
| `companies.read` | Lire les informations tenant autorisees |
| `employees.read` | Lire les employes scoppes tenant |
| `attendance.read` | Lire presences et syntheses |
| `attendance.write` | Creer une correction ou integration controlee |
| `tasks.read` | Lire les taches |
| `tasks.write` | Creer/mettre a jour des taches |
| `documents.read` | Lire metadata documents autorises |
| `documents.write` | Ajouter des documents autorises |
| `payroll.read` | Lire soldes et exports autorises |
| `webhooks.manage` | Gerer les subscriptions webhook |

Aucun scope global n'est implicite. Les droits restent tenant-scopes et RBAC-scopes.

## Webhooks initiaux proposes

- `employee.created`
- `employee.updated`
- `attendance.punched`
- `attendance.corrected`
- `absence.requested`
- `absence.approved`
- `salary_advance.requested`
- `salary_advance.approved`
- `task.assigned`
- `payment.declared`
- `document.generated`

Chaque webhook doit inclure :

- `event_id`
- `event_type`
- `occurred_at`
- `tenant_id`
- `resource`
- `data`
- `signature`
- `idempotency_key`

## Ordre de livraison recommande

1. Stabiliser OpenAPI et reponses JSON standard.
2. Ajouter sandbox tokens et exemples d'integration.
3. Ajouter webhooks signes.
4. Publier collections API nettoyees.
5. Publier un SDK minimal read-only.
6. Ouvrir un programme partenaires prive.
7. Envisager seulement ensuite des packages open source.
