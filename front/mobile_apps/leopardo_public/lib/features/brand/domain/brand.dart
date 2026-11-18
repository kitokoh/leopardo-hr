/// #9581 (BC-41 BRANDING) — entités et règles du domaine « marque » mobile.
///
/// ## Ce fichier est PUR
///
/// Aucun import de `dio`, aucun `Color(...)`, aucune E/S. Il décrit une marque
/// et les règles qui la valident, en Dart pur.
///
/// Deux raisons, et non une seule :
///
///  1. **Testabilité** : les règles (contraste fourni, locale, capacité) se
///     testent sans monter de widget ni de serveur ;
///  2. **Garde couleur** : `dev-hub/tools/validate-mobile-color-tokens.ps1`
///     rejette tout littéral `Color(0x...)` hors de `AppColors`. Une marque
///     vient du RÉSEAU — donc ses couleurs ne peuvent pas être des constantes
///     du fichier de palette. On garde le domaine en `String` hexadécimal et
///     la conversion en `Color` se fait dans la couche de thème, une seule
///     fois, à partir de la valeur reçue.
///
/// ## Pourquoi une marque mobile du tout
///
/// Les applications verticales existantes (`leopardo_pos_restaurant`,
/// `leopardo_travel_agent`, `leopardo_pos_retail`…) gardent leur thème propre
/// et NE SONT PAS MODIFIÉES par cette feature. Elle est additive : une app qui
/// la branche affiche l'identité de l'enseigne servie par l'API ; une app qui
/// ne la branche pas garde exactement son comportement actuel.
library;

/// Sens d'écriture, dérivé de la locale — jamais recopié dans plusieurs écrans.
enum TextDirectionality { ltr, rtl }

/// Locales rendues par le produit (registre partagé avec l'API).
const List<String> supportedBrandLocales = <String>['fr', 'en', 'ar', 'tr'];

/// Palette d'une marque, telle que reçue de l'API.
///
/// `onPrimary` est CALCULÉ côté serveur (contraste WCAG) : le mobile ne le
/// recalcule pas. Deux implémentations de la même formule finiraient par
/// diverger, et c'est l'écran qui paierait la différence.
class BrandPalette {
  const BrandPalette({
    required this.primary,
    required this.accent,
    required this.surface,
    required this.onPrimary,
  });

  final String primary;
  final String accent;
  final String surface;
  final String onPrimary;

  static final RegExp _hex = RegExp(r'^#[0-9a-fA-F]{6}$');

  /// `null` si une couleur manque ou n'est pas `#rrggbb`.
  ///
  /// On refuse plutôt que de compléter : une palette partielle produirait un
  /// écran à moitié thémé, ce qui est plus difficile à diagnostiquer qu'un
  /// repli explicite sur le thème de l'app.
  static BrandPalette? tryParse(Map<String, dynamic>? json) {
    if (json == null) {
      return null;
    }

    final primary = json['primary'];
    final accent = json['accent'];
    final surface = json['surface'];
    final onPrimary = json['on_primary'];

    for (final value in [primary, accent, surface, onPrimary]) {
      if (value is! String || !_hex.hasMatch(value)) {
        return null;
      }
    }

    return BrandPalette(
      primary: primary as String,
      accent: accent as String,
      surface: surface as String,
      onPrimary: onPrimary as String,
    );
  }
}

/// Identité publique de la marque.
class BrandIdentity {
  const BrandIdentity({
    required this.displayName,
    this.tagline,
    this.logoUrl,
    this.faviconUrl,
    this.ogImageUrl,
  });

  final String displayName;
  final String? tagline;
  final String? logoUrl;
  final String? faviconUrl;
  final String? ogImageUrl;

  static BrandIdentity? tryParse(Map<String, dynamic>? json) {
    final displayName = json?['display_name'];

    // Le nom public est l'invariant fort : sans lui, l'écran n'a ni titre ni
    // en-tête. Une marque sans nom n'est pas affichable.
    if (displayName is! String || displayName.trim().isEmpty) {
      return null;
    }

    String? optional(Object? value) =>
        value is String && value.trim().isNotEmpty ? value : null;

    return BrandIdentity(
      displayName: displayName,
      tagline: optional(json?['tagline']),
      logoUrl: optional(json?['logo_url']),
      faviconUrl: optional(json?['favicon_url']),
      ogImageUrl: optional(json?['og_image_url']),
    );
  }
}

/// Adresse et locales de la marque.
class BrandSurface {
  const BrandSurface({
    this.host,
    this.pathPrefix,
    required this.locales,
    required this.defaultLocale,
  });

  final String? host;
  final String? pathPrefix;
  final List<String> locales;
  final String defaultLocale;
}

/// Capacités publiques déclarées par la verticale.
///
/// Ce ne sont pas des permissions : c'est ce que la surface SAIT faire. Un
/// écran n'affiche un parcours que si l'établissement peut l'honorer.
const List<String> knownBrandCapabilities = <String>[
  'order',
  'reserve',
  'book',
  'quote',
  'prescription',
  'ride',
  'contact',
  'directions',
  'hours',
  'ticket',
];

/// Profil de surface fourni par la verticale (même contrat que l'API).
class BrandSurfaceProfile {
  const BrandSurfaceProfile({
    required this.vertical,
    required this.label,
    required this.capabilities,
    required this.blockTypes,
    required this.primaryCta,
  });

  final String vertical;
  final String label;
  final List<String> capabilities;
  final List<String> blockTypes;
  final String? primaryCta;

  static BrandSurfaceProfile? tryParse(Map<String, dynamic>? json) {
    final vertical = json?['vertical'];
    final label = json?['label'];

    if (vertical is! String || vertical.isEmpty || label is! String || label.isEmpty) {
      return null;
    }

    List<String> strings(Object? value) => value is List
        ? value.whereType<String>().toList(growable: false)
        : const <String>[];

    final capabilities = strings(json?['capabilities']);
    final declaredCta = json?['primary_cta'];

    return BrandSurfaceProfile(
      vertical: vertical,
      label: label,
      capabilities: capabilities,
      blockTypes: strings(json?['block_types']),
      // Le CTA primaire doit être déclaré : sinon l'écran mettrait en avant une
      // action que la surface ne fournit pas.
      primaryCta: declaredCta is String && capabilities.contains(declaredCta)
          ? declaredCta
          : null,
    );
  }
}

/// Une marque publique, telle que consommée par les écrans.
class Brand {
  const Brand({
    required this.slug,
    required this.locale,
    required this.direction,
    required this.locales,
    required this.defaultLocale,
    required this.vertical,
    required this.identity,
    required this.palette,
    required this.surface,
    required this.profile,
  });

  final String slug;
  final String locale;
  final TextDirectionality direction;
  final List<String> locales;
  final String defaultLocale;
  final String vertical;
  final BrandIdentity identity;
  final BrandPalette palette;
  final BrandSurface surface;
  final BrandSurfaceProfile profile;

  /// `null` = la marque est inaffichable (slug, identité, palette ou profil
  /// manquant) ; l'appelant retombe alors sur le thème de l'app, jamais sur un
  /// écran vide.
  static Brand? tryParse(Map<String, dynamic>? json) {
    if (json == null) {
      return null;
    }

    final slug = json['slug'];
    final identity = BrandIdentity.tryParse(
      json['identity'] is Map<String, dynamic> ? json['identity'] as Map<String, dynamic> : null,
    );
    final palette = BrandPalette.tryParse(
      json['palette'] is Map<String, dynamic> ? json['palette'] as Map<String, dynamic> : null,
    );
    final profile = BrandSurfaceProfile.tryParse(
      json['profile'] is Map<String, dynamic> ? json['profile'] as Map<String, dynamic> : null,
    );

    if (slug is! String || slug.isEmpty || identity == null || palette == null || profile == null) {
      return null;
    }

    final locale = json['locale'] is String ? json['locale'] as String : 'fr';
    final locales = json['locales'] is List
        ? (json['locales'] as List).whereType<String>().toList(growable: false)
        : <String>[locale];

    final surfaceJson =
        json['surface'] is Map<String, dynamic> ? json['surface'] as Map<String, dynamic> : null;
    final surfaceLocales = surfaceJson?['locales'] is List
        ? (surfaceJson!['locales'] as List).whereType<String>().toList(growable: false)
        : locales;
    final defaultLocale = surfaceJson?['default_locale'] is String
        ? surfaceJson!['default_locale'] as String
        : locale;

    return Brand(
      slug: slug,
      locale: locale,
      direction: json['direction'] == 'rtl' ? TextDirectionality.rtl : TextDirectionality.ltr,
      locales: locales,
      defaultLocale: defaultLocale,
      vertical: json['vertical'] is String ? json['vertical'] as String : 'generic',
      identity: identity,
      palette: palette,
      surface: BrandSurface(
        host: surfaceJson?['host'] is String ? surfaceJson!['host'] as String : null,
        pathPrefix:
            surfaceJson?['path_prefix'] is String ? surfaceJson!['path_prefix'] as String : null,
        locales: surfaceLocales.isEmpty ? <String>[defaultLocale] : surfaceLocales,
        defaultLocale: defaultLocale,
      ),
      profile: profile,
    );
  }
}

/// Normalise une locale demandée.
///
/// Une locale inconnue retombe sur `''` (donc sur la locale par défaut de la
/// marque), pas sur `fr` : une enseigne arabophone ne doit pas basculer en
/// français parce qu'une préférence appareil inconnue a été reçue.
String normalizeBrandLocale(String? raw) {
  if (raw == null || raw.trim().isEmpty) {
    return '';
  }

  final candidate = raw.trim().toLowerCase();
  final short = candidate.length > 2 ? candidate.substring(0, 2) : candidate;

  return supportedBrandLocales.contains(short) ? short : '';
}

/// Le sens d'écriture d'une locale — même règle que le presenter API.
TextDirectionality directionForLocale(String locale) =>
    locale == 'ar' ? TextDirectionality.rtl : TextDirectionality.ltr;

/// Les capacités à afficher, CTA primaire en tête.
List<String> orderedCapabilities(BrandSurfaceProfile profile) {
  final primary = profile.primaryCta;

  if (primary == null) {
    return List<String>.unmodifiable(profile.capabilities);
  }

  return List<String>.unmodifiable(<String>[
    primary,
    ...profile.capabilities.where((capability) => capability != primary),
  ]);
}
