# Plan 45 - Fiche client mobile platform admin

## Objectif

Rendre l'app mobile d'administration plateforme réellement exploitable pour suivre un client après sa création, sans attendre le dashboard web.

## Probleme corrige

La liste mobile des entreprises affichait les tenants mais ne permettait pas d'ouvrir une fiche client. En plus, `PlatformCompany.id` etait caste en entier, ce qui casse les societes identifiees par UUID et peut transformer une action client en route `/platform/companies/0`.

## Livrables

- `PlatformCompany.id` devient une string.
- Nouvelle route mobile `/platform/companies/:companyId`.
- Nouvelle fiche client `CompanyDetailScreen`.
- Appels API connectes :
  - `GET /api/v1/platform/companies/{company}/health` ;
  - `GET /api/v1/platform/companies/{company}/subscription` ;
  - `GET /api/v1/platform/companies/{company}/features`.
- Affichage mobile :
  - score de sante et risque ;
  - adoption pointage 30 jours ;
  - onboarding et anomalies critiques ;
  - abonnement, plan, limites ;
  - modules actifs ;
  - prochaines actions recommandees.
- Contrat mobile mis a jour pour bloquer une route detail non declaree ou un endpoint detail debranche.

## Validation attendue

- `validate-mobile-workflow-contracts.ps1` passe.
- `validate-mobile-plan29.ps1` passe.
- GitHub Actions analyse et build `leopardo_platform_admin`.
