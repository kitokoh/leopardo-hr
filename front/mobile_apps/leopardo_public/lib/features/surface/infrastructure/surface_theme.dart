import 'dart:math' as math;

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:material_ui/material_ui.dart';

import '../domain/surface_identity.dart';

/// Thème Material d'une vitrine verticale.
///
/// ## Rôle
///
/// Traduit l'identité d'une surface en `ThemeData` : c'est la seule couture
/// entre le domaine (identité) et le rendu Flutter. Aucune couleur n'est
/// définie ici — tout vient de `AppColors` via l'identité.
///
/// ## Pourquoi un thème par verticale plutôt qu'un thème global
///
/// Une app publique qui afficherait la même identité pour un restaurant et une
/// pharmacie ne serait pas « spécialisée ». Le thème est donc dérivé de
/// l'identité de la verticale, jamais d'une constante partagée.
ThemeData surfaceTheme(SurfaceIdentity identity) {
  final palette = identity.palette;

  final colorScheme = ColorScheme.fromSeed(
    seedColor: palette.primary,
    brightness: Brightness.light,
  ).copyWith(
    primary: palette.primary,
    onPrimary: palette.onPrimary,
    secondary: palette.accent,
    surface: palette.surface,
    onSurface: palette.onSurface,
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: palette.surface,
    appBarTheme: AppBarTheme(
      backgroundColor: palette.surface,
      foregroundColor: palette.onSurface,
      elevation: 0,
    ),
    textTheme: Typography.material2021().black.apply(
      bodyColor: palette.onSurface,
      displayColor: palette.onSurface,
    ),
  );
}

/// Couleur de texte lisible sur un fond donné.
///
/// Même règle que côté web : le texte posé sur l'accent est **calculé**, jamais
/// supposé. On compare le contraste des deux candidats et on garde le meilleur.
Color readableOn(Color background) {
  const onDark = AppColors.surfaceOnPrimary;
  final onLight = AppColors.surfaceRestaurantOnSurface;

  return _contrast(background, onDark) >= _contrast(background, onLight) ? onDark : onLight;
}

/// Luminance relative (WCAG 2.1).
double _luminance(Color color) {
  double channel(double value) {
    return value <= 0.03928 ? value / 12.92 : math.pow((value + 0.055) / 1.055, 2.4).toDouble();
  }

  return 0.2126 * channel(color.r) + 0.7152 * channel(color.g) + 0.0722 * channel(color.b);
}

double _contrast(Color a, Color b) {
  final la = _luminance(a);
  final lb = _luminance(b);
  final lighter = la > lb ? la : lb;
  final darker = la > lb ? lb : la;

  return (lighter + 0.05) / (darker + 0.05);
}
