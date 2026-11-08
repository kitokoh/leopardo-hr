import 'package:flutter/material.dart';

/// Theme **tablette de caisse** du POS Retail (#9215).
///
/// Contraintes d'usage reel en boutique :
///  - cibles tactiles >= 48 dp (le vendeur enchaine les scans debout) ;
///  - contraste eleve (neons, vitrine, ecran incline) ;
///  - typographie large (lecture a bout de bras) ;
///  - mode sombre (reserve, arriere-boutique).
class RetailTheme {
  const RetailTheme._();

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

    final scheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF1F5FB4),
      brightness: brightness,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: isDark ? const Color(0xFF101418) : const Color(0xFFF4F6F9),
      visualDensity: VisualDensity.comfortable,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        backgroundColor: isDark ? const Color(0xFF171C22) : const Color(0xFFFFFFFF),
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
        fillColor: isDark ? const Color(0xFF1B2129) : const Color(0xFFFFFFFF),
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
