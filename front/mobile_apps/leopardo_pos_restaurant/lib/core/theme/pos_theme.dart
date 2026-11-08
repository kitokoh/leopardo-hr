import 'package:flutter/material.dart';

/// Thème **tablette de caisse** du POS Restaurant (#9214).
///
/// Contraintes d'usage réel en salle :
///  - cibles tactiles ≥ 48 dp (un serveur appuie vite, souvent debout) ;
///  - contraste élevé (lumière du jour, écran incliné) ;
///  - typographie large (lecture à bout de bras) ;
///  - mode sombre (service du soir, salle tamisée).
///
/// Aucune couleur n'est écrite en dur : le garde CI
/// `validate-mobile-color-tokens.ps1` interdit tout littéral `Color(0x...)`
/// hors de `leopardo_core/lib/core/theme/app_colors.dart`. Les surfaces sont
/// donc dérivées du `ColorScheme` généré par la graine de marque.
class PosTheme {
  const PosTheme._();

  /// Graine de marque du POS Restaurant (terre cuite).
  static const Color seed = Color.fromARGB(255, 180, 83, 31);

  /// Cible tactile minimale recommandée par les guides d'accessibilité.
  static const double minTouchTarget = 48;

  /// Rayon des cartes produit / table.
  static const double cardRadius = 14;

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;

    final scheme = ColorScheme.fromSeed(seedColor: seed, brightness: brightness);

    // Surfaces dérivées du scheme : le mode sombre éclaircit/assombrit la
    // surface de base au lieu d'introduire une palette parallèle.
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
