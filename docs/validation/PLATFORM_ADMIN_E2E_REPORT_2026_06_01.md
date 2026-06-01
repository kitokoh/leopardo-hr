# Platform admin E2E report - 2026-06-01

## Perimetre

Ce rapport couvre le Lot 67.2 : verifier que l'app mobile super-admin peut effectuer le parcours minimal de lancement.

Parcours vise :

1. Login super-admin.
2. Hydratation session.
3. Creation entreprise.
4. Redirection vers la fiche client creee.
5. Consultation health/subscription/features.

## Etat backend

Le backend couvre deja :

- `POST /api/v1/platform/auth/login`
- `GET /api/v1/platform/auth/me`
- `POST /api/v1/platform/auth/logout`
- `POST /api/v1/platform/companies`
- `GET /api/v1/platform/companies/{company}/health`
- `GET/PATCH /api/v1/platform/companies/{company}/subscription`
- `GET/PATCH /api/v1/platform/companies/{company}/features`

Tests existants :

- `PlatformAuthTest`
- `PlatformCompanyProvisioningTest`
- `PlatformCompanyHealthApiTest`
- `PlatformCompanySubscriptionApiTest`
- `PlatformCompanyFeatureApiTest`
- `FrontendApiContractTest`

## Changement mobile livre

Avant ce lot, `PlatformRepository.createCompany()` retournait `void`. L'app pouvait creer le client, mais elle ne conservait pas l'identifiant retourne par l'API.

Maintenant :

- `createCompany()` retourne `PlatformCompany`.
- `CompanyCreateScreen` invalide la liste clients puis redirige vers `/platform/companies/{companyId}`.
- `PlatformCompany.fromProvisioningResponse()` mappe le payload `data.company`.
- Les UUID restent des strings pour le routing mobile.

## Garde ajoute

Test modele :

- `front/mobile_apps/leopardo_platform_admin/test/models/platform_company_model_test.dart`

Il couvre :

- mapping du payload de creation plateforme ;
- conservation des IDs UUID comme strings.

## Validation locale effectuee

Commandes :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-workflow-contracts.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-plan29.ps1
```

Les tests Flutter complets restent executes par GitHub Actions pour eviter les lenteurs locales.

## Risque restant

La preuve device super-admin complete depend encore de credentials/staging reels et sera reprise dans le Lot 67.6 release readiness.
