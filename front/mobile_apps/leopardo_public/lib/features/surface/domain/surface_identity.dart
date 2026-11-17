import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:material_ui/material_ui.dart';

/// Verticales grand public couvertes par les vitrines dédiées.
///
/// ## Périmètre
///
/// Ce fichier ne concerne **que** les vitrines publiques des verticales. Il ne
/// touche ni la super administration, ni Etna, ni la Vitrine existante, ni le
/// branding d'un tenant (sujet distinct, déjà traité ailleurs).
///
/// ## Pourquoi une enum plutôt qu'une String
///
/// Une verticale inconnue doit être **impossible à représenter**, pas seulement
/// à gérer : avec une enum, un `switch` incomplet ne compile pas. C'est ce qui
/// garantit qu'ajouter une verticale oblige à lui donner une identité.
enum SurfaceVertical {
  restaurant('restaurant', '/restaurants'),
  retail('retail', '/boutiques'),
  hospitality('hospitality', '/stay'),
  trade('trade', '/entreprises'),
  travel('travel', '/travel'),
  pharmacy('pharmacy', '/pharmacies');

  const SurfaceVertical(this.code, this.basePath);

  /// Code canonique, identique au `discoveryType` du contrat public.
  final String code;

  /// Chemin public de la vitrine dans l'application web.
  final String basePath;

  /// Résout une verticale depuis son code ; `null` si inconnue (fail-closed).
  static SurfaceVertical? fromCode(String? code) {
    if (code == null) {
      return null;
    }

    for (final vertical in SurfaceVertical.values) {
      if (vertical.code == code) {
        return vertical;
      }
    }

    return null;
  }
}

/// Palette d'une vitrine verticale.
///
/// Les couleurs viennent **exclusivement** de `AppColors` (tokens `surface*`) :
/// la garde `validate-mobile-color-tokens` rejette tout littéral `Color(0x…)`
/// hors de `app_colors.dart`. Une vitrine ne peut donc pas inventer une teinte.
@immutable
class SurfacePalette {
  const SurfacePalette({
    required this.primary,
    required this.onPrimary,
    required this.accent,
    required this.surface,
    required this.onSurface,
    required this.muted,
  });

  final Color primary;
  final Color onPrimary;
  final Color accent;
  final Color surface;
  final Color onSurface;
  final Color muted;
}

/// Identité d'une vitrine verticale.
///
/// C'est l'identité de la **surface publique**, pas une marque de tenant : elle
/// est fixe, connue à la compilation, et propre à la verticale.
@immutable
class SurfaceIdentity {
  const SurfaceIdentity({
    required this.vertical,
    required this.displayName,
    required this.tagline,
    required this.canonicalDomain,
    required this.palette,
  });

  final SurfaceVertical vertical;
  final String displayName;
  final String tagline;

  /// Hôte canonique de la vitrine, sans schéma.
  final String canonicalDomain;

  final SurfacePalette palette;

  /// Chemin public de la vitrine.
  String get basePath => vertical.basePath;
}

/// Identités déclarées, une par verticale.
///
/// Ajouter une verticale = ajouter une entrée ici. Le registre est construit
/// explicitement (pas de découverte dynamique) : une verticale absente échoue
/// proprement au lieu de retomber sur une identité générique.
const Map<SurfaceVertical, SurfaceIdentity> surfaceIdentities = {
  SurfaceVertical.restaurant: SurfaceIdentity(
    vertical: SurfaceVertical.restaurant,
    displayName: 'Leopardo Restaurants',
    tagline: 'Trouvez votre table, découvrez la carte, réservez en deux gestes.',
    canonicalDomain: 'restaurants.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfaceRestaurantPrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfaceRestaurantAccent,
      surface: AppColors.surfaceRestaurantSurface,
      onSurface: AppColors.surfaceRestaurantOnSurface,
      muted: AppColors.surfaceRestaurantMuted,
    ),
  ),
  SurfaceVertical.retail: SurfaceIdentity(
    vertical: SurfaceVertical.retail,
    displayName: 'Leopardo Marché',
    tagline: 'Les boutiques près de chez vous, leur catalogue et leurs nouveautés.',
    canonicalDomain: 'marche.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfaceRetailPrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfaceRetailAccent,
      surface: AppColors.surfaceRetailSurface,
      onSurface: AppColors.surfaceRetailOnSurface,
      muted: AppColors.surfaceRetailMuted,
    ),
  ),
  SurfaceVertical.hospitality: SurfaceIdentity(
    vertical: SurfaceVertical.hospitality,
    displayName: 'Leopardo Séjours',
    tagline: 'Hôtels, résidences et maisons d’hôtes — réservation directe.',
    canonicalDomain: 'sejours.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfaceHospitalityPrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfaceHospitalityAccent,
      surface: AppColors.surfaceHospitalitySurface,
      onSurface: AppColors.surfaceHospitalityOnSurface,
      muted: AppColors.surfaceHospitalityMuted,
    ),
  ),
  SurfaceVertical.trade: SurfaceIdentity(
    vertical: SurfaceVertical.trade,
    displayName: 'Leopardo Entreprises',
    tagline: 'Fournisseurs et négociants vérifiés — import, export et marché domestique.',
    canonicalDomain: 'entreprises.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfaceTradePrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfaceTradeAccent,
      surface: AppColors.surfaceTradeSurface,
      onSurface: AppColors.surfaceTradeOnSurface,
      muted: AppColors.surfaceTradeMuted,
    ),
  ),
  SurfaceVertical.travel: SurfaceIdentity(
    vertical: SurfaceVertical.travel,
    displayName: 'Leopardo Billets',
    tagline: 'Comparez les trajets, réservez votre place, recevez votre billet.',
    canonicalDomain: 'billets.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfaceTravelPrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfaceTravelAccent,
      surface: AppColors.surfaceTravelSurface,
      onSurface: AppColors.surfaceTravelOnSurface,
      muted: AppColors.surfaceTravelMuted,
    ),
  ),
  SurfaceVertical.pharmacy: SurfaceIdentity(
    vertical: SurfaceVertical.pharmacy,
    displayName: 'Leopardo Pharmacies',
    tagline: 'Trouvez une officine, ses horaires et sa garde, près de chez vous.',
    canonicalDomain: 'pharmacies.leopardo.app',
    palette: SurfacePalette(
      primary: AppColors.surfacePharmacyPrimary,
      onPrimary: AppColors.surfaceOnPrimary,
      accent: AppColors.surfacePharmacyAccent,
      surface: AppColors.surfacePharmacySurface,
      onSurface: AppColors.surfacePharmacyOnSurface,
      muted: AppColors.surfacePharmacyMuted,
    ),
  ),
};

/// Identité d'une verticale, ou `null` si elle n'est pas déclarée.
///
/// Fail-closed : une verticale inconnue ne reçoit **jamais** une identité par
/// défaut — l'appelant doit décider explicitement quoi afficher.
SurfaceIdentity? surfaceIdentityFor(SurfaceVertical? vertical) {
  if (vertical == null) {
    return null;
  }

  return surfaceIdentities[vertical];
}
