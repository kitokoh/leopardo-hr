/// #9581 (BC-41 BRANDING) — conversion marque → thème Flutter.
///
/// ## Le SEUL fichier qui convertit une couleur de marque en `Color`
///
/// La garde `dev-hub/tools/validate-mobile-color-tokens.ps1` interdit tout
/// littéral `Color(0x...)` hors de `AppColors`. Une marque vient du réseau :
/// ses couleurs ne peuvent donc pas être des constantes. On les convertit ici,
/// à partir de la chaîne hexadécimale reçue, et nulle part ailleurs.
///
/// ## Pourquoi un `ThemeData` dérivé et pas un thème global
///
/// Une app peut afficher plusieurs enseignes (une liste de commerces, puis la
/// fiche de l'un d'eux). Remplacer le thème global ferait clignoter toute
/// l'app au changement d'enseigne. On rend donc un `ThemeData` que l'écran
/// concerné applique localement via `Theme(data: …)`.
library;

import 'package:material_ui/material_ui.dart';

import '../domain/brand.dart';

/// Convertit `#rrggbb` en `Color`. `null` si la valeur est invalide.
///
/// On ne lève pas : une couleur invalide doit produire un repli, pas un écran
/// d'erreur. Le domaine a déjà validé la forme ; cette garde couvre le cas où
/// un appelant construirait une palette à la main.
Color? colorFromHex(String hex) {
  final normalized = hex.trim().replaceFirst('#', '');

  if (normalized.length != 6) {
    return null;
  }

  final value = int.tryParse(normalized, radix: 16);

  return value == null ? null : Color(0xFF000000 | value);
}

/// Construit un `ThemeData` à partir d'une marque.
///
/// `base` est le thème de l'app : on ne le remplace pas, on le dérive. Une
/// marque ne définit que ce qu'elle sait — la couleur primaire, la surface, le
/// rayon. Tout le reste (typographie, tailles, sémantique) reste celui de
/// l'app, ce qui garantit qu'une enseigne ne peut pas casser l'accessibilité
/// ou la lisibilité générale.
ThemeData brandTheme(Brand brand, {ThemeData? base}) {
  final fallback = base ?? ThemeData.light();

  final primary = colorFromHex(brand.palette.primary);
  final accent = colorFromHex(brand.palette.accent);
  final surface = colorFromHex(brand.palette.surface);
  final onPrimary = colorFromHex(brand.palette.onPrimary);

  // Aucune couleur exploitable : on rend le thème de base inchangé plutôt
  // qu'un thème à moitié appliqué.
  if (primary == null ||
      accent == null ||
      surface == null ||
      onPrimary == null) {
    return fallback;
  }

  final scheme = ColorScheme.fromSeed(
    seedColor: primary,
    brightness: fallback.brightness,
  ).copyWith(
    primary: primary,
    secondary: accent,
    surface: surface,
    // `onPrimary` vient du serveur (contraste WCAG calculé) : on l'impose au
    // lieu de laisser `fromSeed` en déduire un, qui pourrait ne pas respecter
    // le contraste choisi par le tenant.
    onPrimary: onPrimary,
  );

  return fallback.copyWith(
    colorScheme: scheme,
    scaffoldBackgroundColor: surface,
  );
}

/// Le sens d'écriture Flutter d'une marque.
TextDirection textDirectionOf(Brand brand) =>
    brand.direction == TextDirectionality.rtl
        ? TextDirection.rtl
        : TextDirection.ltr;
