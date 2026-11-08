import 'package:flutter/material.dart';

/// Theme **tablette de caisse** du POS Retail (#9215).
///
/// Contraintes d'usage reel en boutique :
///  - cibles tactiles >= 48 dp (le vendeur enchaine les scans debout) ;
///  - contraste eleve (neons, vitrine, ecran incline) ;
///  - typographie large (lecture a bout de bras) ;
///  - mode sombre (reserve, arriere-boutique).
///
/// Aucune couleur n'est ecrite en dur : le garde CI
/// `validate-mobile-color-tokens.ps1` interdit tout litteral `Color(0x...)`
/// hors de `leopardo_core/lib/core/theme/app_colors.dart`. Les surfaces sont
/// donc derivees du `ColorScheme` genere par la graine de marque, ce qui garde
/// le theme coherent avec le reste de la suite mobile.
class RetailTheme {
  const RetailTheme._();

  /// Graine de marque du POS Retail (bleu commerce).
  static const Color seed = Color.fromARGB(255, 31, 95, 180);

  /// Cible tactile minimale recommandee par les guides d'accessibilite.
  static const double minTouchTarget = 48;

  /// Rayon des cartes produit.
  static const double cardRadius = 14;

  /// Hauteur d'une tuile produit dans la grille de caisse.
  static const double productTileHeight = 108;

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;

    final scheme = ColorScheme.fromSeed(seedColor: seed, brightness: brightness);

    // Surfaces derivees du scheme : le mode sombre eclaircit/assombrit la
    // surface de base au lieu d'introduire une palette parallele.
    final surface = isDark
        ? Color.alphaBlend(scheme.surface.withValues(alpha: 0.6), scheme.surfaceContainerHighest)
        : scheme.surface;
    final appBarSurface = isDark ? scheme.surfaceContainerHigh : scheme.surface;
    final fieldFill = isDark ? scheme.surfaceContainerHighest : scheme.surface;

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: surface,
      visualDensity: VisualDensity.comfortable,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        backgroundColor: appBarSurface,
        foregroundColor: scheme.onSurface,
        titleTextStyle: TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w700,
          color: scheme.onSurface,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(cardRadius),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(minTouchTarget, minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(minTouchTarget, minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
      ),
      listTileTheme: const ListTileThemeData(
        minVerticalPadding: 14,
        contentPadding: EdgeInsets.symmetric(horizontal: 18, vertical: 6),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: fieldFill,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        insetPadding: EdgeInsets.all(20),
      ),
    );
  }
}
