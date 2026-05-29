# Plan 57 - Documentation API professionnelle et ecosysteme developpeur

## Source

Point utilisateur 31.

## Objectif

Transformer l'API Leopardo RH en surface integrable par des developpeurs tiers : documentation OpenAPI complete, API Explorer exploitable, erreurs standardisees, exemples et environnement sandbox.

## Perimetre

- Backend Laravel API.
- `api/openapi.yaml`.
- Pages publiques `/docs`, `/api-explorer`, `/tester-guide`.
- Guides partenaires dans `docs/GUIDES/`.
- Contrats JSON, pagination, erreurs et permissions.

## Lots d'execution

### Lot 57.1 - Audit OpenAPI existant

- Lister les routes exposees par Laravel.
- Comparer routes critiques avec `api/openapi.yaml`.
- Identifier endpoints absents, schemas incomplets et erreurs non documentees.
- Mettre a jour `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` si une route critique manque.

### Lot 57.2 - Standard documentation endpoints

- Documenter auth, tenants, roles, rate limits et versioning.
- Ajouter exemples requete/reponse pour auth, employees, attendance, tasks, absences, salary advances, notifications, platform admin.
- Documenter erreurs standard : `VALIDATION_ERROR`, `FORBIDDEN`, `UNAUTHENTICATED`, `COMPANY_NOT_FOUND`, `TWO_FA_REQUIRED`, `NOT_IMPLEMENTED`.

### Lot 57.3 - API Explorer premium

- Verifier que `/api-explorer` lit la spec canonique.
- Ajouter contexte sandbox/demo : URL Render, comptes demo, headers requis, token Bearer.
- Ajouter sections "Mobile", "Manager", "Platform admin", "Kiosk", "Webhooks".

### Lot 57.4 - Developer ecosystem

- Creer ou mettre a jour le guide partenaire canonique.
- Documenter sandbox tokens et strategie de futurs tokens developpeurs.
- Documenter webhooks : signature, retry, idempotence, evenements.

## Fichiers probables

- `api/openapi.yaml`
- `api/routes/**/*.php`
- `api/app/Http/Controllers/**/*`
- `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md`
- `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`
- `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md`

## Tests et validations

- `OpenApiDocsTest`
- `FrontendApiContractTest`
- `node dev-hub/tools/generate-openapi-sdk.mjs --check` si SDK impacte
- CI backend et governance gates

## Criteres d'acceptation

- Aucun endpoint critique mobile/web/admin/kiosk non documente.
- Swagger/OpenAPI charge sans erreur.
- Les erreurs et permissions sont explicites.
- Le guide partenaire permet a un integrateur de faire login + appel API + gestion erreur sans lire le code.
