
export 'package:leopardo_core/features/company_branding/providers/tenant_branding_provider.dart'
    show tenantBrandingProvider;

/// Leopardo manager — provider branding partagé (leopardo_core, #5279).
/// Le provider suit l'utilisateur via `authProvider.select`, charge le
/// branding via `TenantBrandingRepository` et retourne `null` en cas
/// d'absence/erreur (contrat read-only du garde readiness mobile).
