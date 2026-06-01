# ADR 0004 - Bornes open core et marketplace

## Statut

Acceptee.

## Contexte

Leopardo RH evolue vers un OS de gestion d'entreprise mobile-first avec API publique, webhooks, apps mobiles, kiosk, vitrine, super-admin et futurs modules marketplace. Une ouverture partielle du code peut accelerer l'ecosysteme, mais un partage premature expose des risques : fuite de secrets, donnees demo non nettoyees, logique multi-tenant sensible, confusion licence et support impossible.

## Decision

Le projet adopte une strategie **open core cadree**, sans split de code immediat.

Les surfaces candidates open source sont :

- SDK clients et exemples d'integration API.
- Specifications OpenAPI publiques.
- Guides developpeurs, Postman/Bruno collections et exemples webhook.
- Connecteurs non sensibles vers outils tiers, quand ils n'incluent pas de logique tenant, paie, facturation ou securite proprietaire.
- Composants UI generiques non lies aux donnees client, si leur licence le permet.

Les surfaces qui restent **enterprise only** sont :

- Backend Laravel multi-tenant, RBAC, policies, workflows RH, paie, avances, documents, pointage, QR, notifications et orchestration jobs.
- Apps mobiles employee, manager et platform admin.
- Kiosk client, dashboard admin, vitrine commerciale et contenus marketing premium.
- Migrations, seeders clients, demo data riche, scripts ops, workflows CI/CD, configuration infra et runbooks internes.
- Toute logique de pricing, billing, scoring, performance, anti-fraude, GPS, biometrie ou IA metier.

Le futur marketplace expose des extensions via contrats controles, pas par import direct du code interne :

- OAuth ou tokens serveur a serveur avec scopes minimaux.
- Webhooks signes avec rotation de secrets.
- API versionnee sous `/api/v1`.
- Manifeste plugin declare : nom, editeur, scopes API, webhooks ecoutes, endpoints appeles, politique de donnees, support et version.
- Revue securite avant activation tenant.

## Consequences

- Aucun depot public ne doit etre cree avant audit licence, secret scan, nettoyage demo data et validation juridique.
- Les futurs plugins utilisent les contrats publics (`api/openapi.yaml`, guide partenaire, webhooks signes) et non des classes Laravel internes.
- Les exemples open source doivent utiliser des comptes sandbox, jamais des tokens de production ni des donnees client.
- Toute route ou webhook expose a l'ecosysteme doit etre documente dans OpenAPI ou dans le guide partenaire.
- Le depot principal reste prive tant que les bornes ci-dessus ne sont pas industrialisees.

## Regles operationnelles

- Avant de publier un package, executer secret scan, license scan et revue des donnees embarquees.
- Ne jamais publier `.env`, fichiers Firebase, service accounts, dumps SQL, logs Render, exports clients ou fixtures identifiantes.
- Les scopes marketplace doivent etre allowlistes et auditables.
- Un plugin ne peut pas obtenir un scope global par defaut ; il doit demander seulement les scopes necessaires.
- Les webhooks doivent inclure signature, timestamp, idempotency key et politique de retry.
- Une extension qui ecrit des donnees doit etre testee sur sandbox avant activation production.
