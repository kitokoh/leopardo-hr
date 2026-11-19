import 'package:material_ui/material_ui.dart';

import '../domain/surface_identity.dart';

/// Thème Material d'une vitrine verticale.
///
/// ## Rôle
///
/// Traduit l'identité d'une surface en `ThemeData` : c'est la seule couture
/// entre le domaine (identité, en hex) et le rendu Flutter. Aucune couleur
/// n'est définie ici — tout vient de l'identité, donc de `AppColors` via
/// `surface_palette_tokens.dart`.
///
/// ## Pourquoi un thème par verticale plutôt qu'un thème global
///
/// Une app publique qui afficherait la même identité pour un restaurant et une
/// pharmacie ne serait pas « spécialisée ». Le thème est donc dérivé de
/// l'identité de la verticale, jamais d'une constante partagée.
ThemeData surfaceTheme(SurfaceIdentity identity) {
  final palette = identity.palette;

  final colorScheme =
      ColorScheme.fromSeed(
        seedColor: colorOf(palette.primary),
        brightness: Brightness.light,
      ).copyWith(
        primary: colorOf(palette.primary),
        onPrimary: colorOf(palette.onPrimary),
        secondary: colorOf(palette.accent),
        surface: colorOf(palette.surface),
        onSurface: colorOf(palette.onSurface),
      );

  return ThemeData(
    useMaterial3: true,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: colorOf(palette.surface),
    appBarTheme: AppBarTheme(
      backgroundColor: colorOf(palette.surface),
      foregroundColor: colorOf(palette.onSurface),
      elevation: 0,
    ),
    textTheme: Typography.material2021().black.apply(
      bodyColor: colorOf(palette.onSurface),
      displayColor: colorOf(palette.onSurface),
    ),
  );
}

/// Convertit un hex `#RRGGBB` du domaine en `Color`.
///
/// Le domaine ne manipule que des chaînes ; c'est ici, et seulement ici, que la
/// conversion a lieu. Un hex malformé retombe sur du noir transparent plutôt
/// que de lever : le registre a déjà validé toutes les palettes à la
/// construction, donc ce cas ne peut survenir que sur une identité fabriquée à
/// la main dans un test.
Color colorOf(String hex) {
  final rgb = parseHexColor(hex);
  if (rgb == null) {
    return const Color(0x00000000);
  }

  return Color.fromARGB(0xFF, rgb[0], rgb[1], rgb[2]);
}

/// Couleur de texte lisible sur un fond donné.
///
/// Même règle que côté web : le texte posé sur l'accent est **calculé**, jamais
/// supposé. On compare le contraste des deux candidats et on garde le meilleur.
Color readableOn(Color background) {
  final hex =
      '#${(background.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

  return colorOf(pickReadableOn(hex));
}
