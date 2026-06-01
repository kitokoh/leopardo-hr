# Rapport mobile tenant branding - 2026-06-01

## Perimetre

- `front/mobile_apps/leopardo_employee`
- `front/mobile_apps/leopardo_manager`
- `front/mobile_apps/leopardo_core`

## Objectif

Finaliser le Lot 67.4 : appliquer l'identite visuelle tenant dans les apps employee et manager, sans casser la lisibilite et sans l'appliquer a l'app platform admin globale.

## Contrat livre

- `leopardo_core` contient le modele `TenantBranding`, le repository `TenantBrandingRepository`, le transformateur `TenantTheme` et le widget `TenantBrandMark`.
- Employee et manager lisent `GET /api/v1/company/branding` apres authentification via un provider tolerant aux erreurs.
- `MaterialApp.router` applique `TenantTheme.apply(...)` sur les themes clair/sombre et garde Leopardo en fallback.
- Les homes employee/manager affichent un signal d'entreprise avec logo/nom quand disponible.
- Les gradients de home utilisent les couleurs tenant avec fallback `AppColors.rh` / `AppColors.ia`.
- La sauvegarde branding manager invalide aussi le provider global tenant pour appliquer le changement immediatement.
- `leopardo_platform_admin` reste hors branding tenant global.

## Garde CI

Le script `dev-hub/tools/validate-mobile-tenant-branding.ps1` verifie :

- fichiers core branding presents;
- endpoint `/company/branding` wire dans le repository partage;
- `TenantTheme.apply` utilise par employee/manager;
- provider tolerant apres auth;
- `TenantBrandMark` visible sur les homes;
- platform admin non contamine par le theme tenant;
- invalidation du branding global apres sauvegarde manager.

Ce script est execute par `mobile-apps-ci.yml`.

## Risques restants

- Les ecrans historiques utilisant directement `AppColors.rh` ne sont pas tous re-themables individuellement. Le theme global et la home couvrent le lancement; les remplacements fins peuvent etre traites progressivement.
- Les logos distants restent soumis a disponibilite reseau et au domaine autorise par l'entreprise.
