/// Identité d'une surface publique verticale — **domaine pur**.
///
/// ## Ce fichier n'importe aucun framework
///
/// Aucun `material_ui`, aucun `flutter_riverpod`, aucun `dio`, aucun
/// `leopardo_core`. C'est la règle d'ADR-0035, et elle est **opposée par un
/// test** (`test/architecture/layer_guard_test.dart`), pas par la revue.
///
/// Conséquence concrète : les couleurs sont des chaînes hexadécimales, pas des
/// `Color`. La conversion en `Color` se fait une seule fois, dans
/// `infrastructure/surface_palette_tokens.dart`, à partir des tokens
/// `AppColors`. Un domaine qui importerait `AppColors` ne serait plus testable
/// sans monter Flutter, et la garde `validate-mobile-color-tokens` n'aurait
/// plus de frontière claire.
///
/// Seul `dart:math` est importé : c'est le SDK, pas un framework. La garde de
/// couche autorise explicitement `dart:` et refuse tout `package:`.
///
/// ## Ce que ce type N'EST PAS
///
/// Ce n'est **pas** une marque de tenant. Le branding d'un tenant est un sujet
/// distinct (`features/brand/`, BC-41) : il vient du réseau et varie par
/// enseigne. Ici l'identité est celle d'une **surface publique de verticale** :
/// fixe, connue à la compilation, identique pour tous les visiteurs.
library;

import 'dart:math' as math;

/// Verticales grand public couvertes par les vitrines dédiées.
///
/// Une enum plutôt qu'une `String` : une verticale inconnue doit être
/// **impossible à représenter**, pas seulement à gérer. Un `switch` incomplet
/// ne compile pas, donc ajouter une verticale oblige à lui donner une identité.
enum SurfaceVertical {
  restaurant('restaurant', '/restaurants', ['restaurant']),
  retail('retail', '/boutiques', ['retail']),
  hospitality('hospitality', '/stay', ['hospitality', 'hospitality_property']),
  trade('trade', '/entreprises', ['trade']),
  travel('travel', '/billets', ['travel']),
  pharmacy('pharmacy', '/pharmacies', ['pharmacy']),
  vtc('vtc', '/operateurs', ['vtc']),
  careers('careers', '/emploi', ['careers']);

  const SurfaceVertical(this.code, this.basePath, this.apiTypeCodes);

  /// Code canonique de la verticale, identique à celui du registre web
  /// (`front/web/src/modules/public-surfaces/<vertical>/identity/`).
  final String code;

  /// Chemin public de la vitrine dans l'application web.
  final String basePath;

  /// Codes `type` que le contrat de découverte publique peut émettre pour
  /// cette verticale.
  ///
  /// ## Pourquoi ce n'est pas la même chose que [code]
  ///
  /// Le registre web nomme la verticale `hospitality` ; l'API de découverte
  /// émet `hospitality_property` (le type de ressource). Sans cette table, la
  /// fiche d'un hôtel ne résoudrait **jamais** son identité et retomberait
  /// silencieusement sur le thème neutre — un défaut invisible en test unitaire
  /// et visible seulement en production, sur une seule verticale.
  ///
  /// Le premier élément est toujours [code] : une verticale dont le code API
  /// diffère doit le déclarer explicitement, jamais par convention implicite.
  final List<String> apiTypeCodes;

  /// Résout une verticale depuis son code canonique **ou** un code `type` du
  /// contrat public ; `null` si inconnue (fail-closed).
  static SurfaceVertical? fromCode(String? code) {
    if (code == null) {
      return null;
    }

    for (final vertical in SurfaceVertical.values) {
      if (vertical.apiTypeCodes.contains(code)) {
        return vertical;
      }
    }

    return null;
  }
}

/// Palette d'une vitrine, en hexadécimal (`#RRGGBB`).
///
/// Les valeurs viennent **exclusivement** de `AppColors` (tokens `surface*`),
/// mais le domaine ne le sait pas : il ne manipule que des chaînes. C'est ce
/// qui lui permet de rester pur.
class SurfacePalette {
  const SurfacePalette({
    required this.primary,
    required this.onPrimary,
    required this.accent,
    required this.surface,
    required this.onSurface,
    required this.muted,
  });

  final String primary;
  final String onPrimary;
  final String accent;
  final String surface;
  final String onSurface;
  final String muted;
}

/// Identité d'une vitrine verticale.
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

/// Contraste WCAG 2.1 minimal pour du texte normal.
const double aaNormal = 4.5;

/// Contraste WCAG 2.1 minimal pour du grand texte.
const double aaLarge = 3.0;

/// Parse `#RRGGBB` (ou `RRGGBB`) en composantes 0-255 ; `null` si malformé.
///
/// Fonction pure : aucune dépendance, testable sans Flutter.
List<int>? parseHexColor(String value) {
  final raw = value.startsWith('#') ? value.substring(1) : value;

  if (raw.length != 6) {
    return null;
  }

  final parsed = int.tryParse(raw, radix: 16);
  if (parsed == null) {
    return null;
  }

  return [(parsed >> 16) & 0xFF, (parsed >> 8) & 0xFF, parsed & 0xFF];
}

/// Luminance relative (WCAG 2.1), sur une échelle 0-1.
double relativeLuminance(String hex) {
  final rgb = parseHexColor(hex);
  if (rgb == null) {
    return 0;
  }

  double channel(int value) {
    final normalized = value / 255;
    return normalized <= 0.03928
        ? normalized / 12.92
        : math.pow((normalized + 0.055) / 1.055, 2.4).toDouble();
  }

  return 0.2126 * channel(rgb[0]) +
      0.7152 * channel(rgb[1]) +
      0.0722 * channel(rgb[2]);
}

/// Ratio de contraste entre deux couleurs hexadécimales (1 à 21).
double contrastRatio(String a, String b) {
  final la = relativeLuminance(a);
  final lb = relativeLuminance(b);
  final lighter = la > lb ? la : lb;
  final darker = la > lb ? lb : la;

  return (lighter + 0.05) / (darker + 0.05);
}

/// Vrai si le couple atteint le seuil demandé.
bool meetsContrast(String background, String foreground, double minimum) {
  return contrastRatio(background, foreground) >= minimum;
}

/// Choisit le texte lisible sur un fond, entre blanc et noir.
///
/// Le texte posé sur une couleur d'action est **calculé**, jamais supposé : une
/// palette dont l'accent s'éclaircit ne doit pas produire un libellé illisible.
String pickReadableOn(String background) {
  const white = '#FFFFFF';
  const black = '#000000';

  return contrastRatio(background, white) >= contrastRatio(background, black)
      ? white
      : black;
}

/// Valide une identité et lève une erreur actionnable si un invariant casse.
///
/// Appelée à la construction du registre : une identité invalide fait échouer
/// le démarrage (et donc la CI), jamais un rendu silencieusement dégradé.
void assertSurfaceIdentity(SurfaceIdentity identity) {
  final vertical = identity.vertical;

  if (identity.displayName.trim().isEmpty) {
    throw ArgumentError('[${vertical.code}] displayName vide');
  }

  if (identity.tagline.trim().isEmpty) {
    throw ArgumentError('[${vertical.code}] tagline vide');
  }

  if (!identity.canonicalDomain.contains('.') ||
      identity.canonicalDomain.contains('/') ||
      identity.canonicalDomain.startsWith('http')) {
    throw ArgumentError(
      '[${vertical.code}] canonicalDomain doit être un hôte nu, sans schéma '
      'ni slash : ${identity.canonicalDomain}',
    );
  }

  if (!identity.basePath.startsWith('/') || identity.basePath.endsWith('/')) {
    throw ArgumentError(
      '[${vertical.code}] basePath doit être un chemin absolu sans slash '
      'final : ${identity.basePath}',
    );
  }

  final palette = identity.palette;
  final entries = <String, String>{
    'primary': palette.primary,
    'onPrimary': palette.onPrimary,
    'accent': palette.accent,
    'surface': palette.surface,
    'onSurface': palette.onSurface,
    'muted': palette.muted,
  };

  for (final entry in entries.entries) {
    if (parseHexColor(entry.value) == null) {
      throw ArgumentError(
        '[${vertical.code}] ${entry.key} n\'est pas un hex #RRGGBB : '
        '${entry.value}',
      );
    }
  }

  if (!meetsContrast(palette.primary, palette.onPrimary, aaNormal)) {
    throw ArgumentError(
      '[${vertical.code}] contraste AA insuffisant : onPrimary sur primary',
    );
  }

  if (!meetsContrast(palette.surface, palette.onSurface, aaNormal)) {
    throw ArgumentError(
      '[${vertical.code}] contraste AA insuffisant : onSurface sur surface',
    );
  }
}

/// Registre des identités déclarées.
///
/// Le registre est la **seule** porte d'entrée vers une identité. Il est
/// construit explicitement (pas de découverte dynamique) : ajouter une
/// verticale est un acte volontaire, visible en revue.
class SurfaceRegistry {
  SurfaceRegistry._(this._byVertical);

  final Map<SurfaceVertical, SurfaceIdentity> _byVertical;

  /// Identité de la verticale, ou `null` si elle n'est pas déclarée.
  ///
  /// Fail-closed : une verticale inconnue ne reçoit **jamais** une identité par
  /// défaut — l'appelant doit décider explicitement quoi afficher.
  SurfaceIdentity? get(SurfaceVertical? vertical) {
    if (vertical == null) {
      return null;
    }

    return _byVertical[vertical];
  }

  /// Toutes les identités déclarées, dans l'ordre de déclaration.
  List<SurfaceIdentity> all() => List.unmodifiable(_byVertical.values);

  /// Vrai si la verticale est déclarée.
  bool has(SurfaceVertical vertical) => _byVertical.containsKey(vertical);

  /// Verticales déclarées.
  List<SurfaceVertical> verticals() => List.unmodifiable(_byVertical.keys);
}

/// Construit un registre à partir d'identités.
///
/// Lève une erreur si deux identités partagent la même verticale, si deux
/// vitrines revendiquent le même domaine, ou si l'une est invalide.
SurfaceRegistry createSurfaceRegistry(List<SurfaceIdentity> identities) {
  final byVertical = <SurfaceVertical, SurfaceIdentity>{};
  final domains = <String>{};

  for (final identity in identities) {
    assertSurfaceIdentity(identity);

    if (byVertical.containsKey(identity.vertical)) {
      throw ArgumentError(
        'Vitrine déclarée deux fois pour la verticale : '
        '${identity.vertical.code}',
      );
    }

    if (!domains.add(identity.canonicalDomain)) {
      throw ArgumentError(
        'Deux vitrines revendiquent le même domaine : '
        '${identity.canonicalDomain}',
      );
    }

    byVertical[identity.vertical] = identity;
  }

  return SurfaceRegistry._(byVertical);
}
