/// Pont entre les tokens `AppColors` et le domaine pur.
///
/// ## Pourquoi cette couche existe
///
/// Le domaine (`domain/surface_identity.dart`) ne connaît que des chaînes
/// hexadécimales. Les couleurs réelles vivent dans `AppColors`, qui est du
/// Flutter (`Color`). Ce fichier est le **seul** endroit où les deux se
/// rencontrent : il lit les tokens et les convertit en hex pour construire les
/// identités.
///
/// Conséquence : la garde `validate-mobile-color-tokens` reste satisfaite (aucun
/// littéral `Color(0x…)` hors de `app_colors.dart`), et le domaine reste
/// testable sans monter Flutter.
library;

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:material_ui/material_ui.dart';

import '../domain/surface_identity.dart';

/// Convertit une `Color` en `#RRGGBB`.
///
/// `toARGB32()` plutôt que les accesseurs `.r/.g/.b` : ceux-ci sont dépréciés
/// dans Flutter 3.50 au profit de l'espace colorimétrique explicite.
String hexOf(Color color) {
  final argb = color.toARGB32();
  final rgb = argb & 0xFFFFFF;

  return '#${rgb.toRadixString(16).padLeft(6, '0').toUpperCase()}';
}

/// Construit une palette de domaine depuis des couleurs Flutter.
///
/// `onPrimary` est **calculé** (`pickReadableOn`) et non saisi : une palette
/// dont l'action s'éclaircit ne peut pas produire un libellé illisible.
SurfacePalette paletteFrom({
  required Color primary,
  required Color accent,
  required Color surface,
  required Color onSurface,
  required Color muted,
}) {
  final primaryHex = hexOf(primary);

  return SurfacePalette(
    primary: primaryHex,
    onPrimary: pickReadableOn(primaryHex),
    accent: hexOf(accent),
    surface: hexOf(surface),
    onSurface: hexOf(onSurface),
    muted: hexOf(muted),
  );
}

/// Identités déclarées, une par verticale.
///
/// Ajouter une verticale = ajouter une entrée ici **et** dans l'enum
/// `SurfaceVertical`. Le registre est construit explicitement : une verticale
/// absente échoue proprement au lieu de retomber sur une identité générique.
///
/// Les domaines et chemins sont alignés sur
/// `front/web/src/modules/public-surfaces/<vertical>/identity/` : une vitrine
/// mobile et sa vitrine web ne peuvent pas se retrouver sur deux hôtes
/// différents.
final List<SurfaceIdentity> declaredSurfaceIdentities = [
  SurfaceIdentity(
    vertical: SurfaceVertical.restaurant,
    displayName: 'Leopardo Restaurants',
    tagline:
        'Trouvez votre table, découvrez la carte, réservez en deux gestes.',
    canonicalDomain: 'restaurants.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceRestaurantPrimary,
      accent: AppColors.surfaceRestaurantAccent,
      surface: AppColors.surfaceRestaurantSurface,
      onSurface: AppColors.surfaceRestaurantOnSurface,
      muted: AppColors.surfaceRestaurantMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.retail,
    displayName: 'Leopardo Marché',
    tagline:
        'Les boutiques près de chez vous, leur catalogue et leurs nouveautés.',
    canonicalDomain: 'marche.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceRetailPrimary,
      accent: AppColors.surfaceRetailAccent,
      surface: AppColors.surfaceRetailSurface,
      onSurface: AppColors.surfaceRetailOnSurface,
      muted: AppColors.surfaceRetailMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.hospitality,
    displayName: 'Leopardo Séjours',
    tagline: 'Hôtels, résidences et maisons d’hôtes — réservation directe.',
    canonicalDomain: 'sejours.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceHospitalityPrimary,
      accent: AppColors.surfaceHospitalityAccent,
      surface: AppColors.surfaceHospitalitySurface,
      onSurface: AppColors.surfaceHospitalityOnSurface,
      muted: AppColors.surfaceHospitalityMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.trade,
    displayName: 'Leopardo Entreprises',
    tagline: 'Fournisseurs et négociants vérifiés — import, export et marché '
        'domestique.',
    canonicalDomain: 'entreprises.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceTradePrimary,
      accent: AppColors.surfaceTradeAccent,
      surface: AppColors.surfaceTradeSurface,
      onSurface: AppColors.surfaceTradeOnSurface,
      muted: AppColors.surfaceTradeMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.travel,
    displayName: 'Leopardo Billets',
    tagline:
        'Comparez les trajets, réservez votre place, recevez votre billet.',
    canonicalDomain: 'billets.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceTravelPrimary,
      accent: AppColors.surfaceTravelAccent,
      surface: AppColors.surfaceTravelSurface,
      onSurface: AppColors.surfaceTravelOnSurface,
      muted: AppColors.surfaceTravelMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.pharmacy,
    displayName: 'Leopardo Pharmacies',
    tagline:
        'Trouvez une officine, ses horaires et sa garde, près de chez vous.',
    canonicalDomain: 'pharmacies.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfacePharmacyPrimary,
      accent: AppColors.surfacePharmacyAccent,
      surface: AppColors.surfacePharmacySurface,
      onSurface: AppColors.surfacePharmacyOnSurface,
      muted: AppColors.surfacePharmacyMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.vtc,
    displayName: 'Leopardo Mobilité',
    tagline: 'Opérateurs de transport vérifiés — demandez un trajet.',
    canonicalDomain: 'mobilite.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceMobilityPrimary,
      accent: AppColors.surfaceMobilityAccent,
      surface: AppColors.surfaceMobilitySurface,
      onSurface: AppColors.surfaceMobilityOnSurface,
      muted: AppColors.surfaceMobilityMuted,
    ),
  ),
  SurfaceIdentity(
    vertical: SurfaceVertical.careers,
    displayName: 'Leopardo Emploi',
    tagline: 'Les offres des entreprises près de chez vous, et comment '
        'postuler.',
    canonicalDomain: 'emploi.leopardo.app',
    palette: paletteFrom(
      primary: AppColors.surfaceCareersPrimary,
      accent: AppColors.surfaceCareersAccent,
      surface: AppColors.surfaceCareersSurface,
      onSurface: AppColors.surfaceCareersOnSurface,
      muted: AppColors.surfaceCareersMuted,
    ),
  ),
];

/// Registre construit une fois, validé à la construction.
///
/// Une identité invalide (nom vide, domaine malformé, contraste AA insuffisant)
/// fait échouer le démarrage — donc la CI — plutôt que de produire un rendu
/// silencieusement dégradé.
final SurfaceRegistry surfaceRegistry = createSurfaceRegistry(
  declaredSurfaceIdentities,
);

/// Identité d'une verticale, ou `null` si elle n'est pas déclarée.
SurfaceIdentity? surfaceIdentityFor(SurfaceVertical? vertical) =>
    surfaceRegistry.get(vertical);
