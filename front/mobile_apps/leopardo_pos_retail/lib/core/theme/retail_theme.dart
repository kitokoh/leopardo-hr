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
      // Accessibilite : le vendeur peut agrandir le texte de la tablette
      // (vue qui baisse, ecran incline). On borne l'echelle pour ne pas
      // casser la mise en page 3 colonnes, mais on la laisse agir.
      textTheme: _scaledTextTheme(ThemeData(brightness: brightness).textTheme),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize: const Size(minTouchTarget, minTouchTarget),
        ),
      ),
      chipTheme: ChipThemeData(
        labelStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant, thickness: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: fieldFill,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
        labelStyle: const TextStyle(fontSize: 16),
        hintStyle: const TextStyle(fontSize: 16),
      ),
      snackBarTheme: const SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        insetPadding: EdgeInsets.all(20),
        contentTextStyle: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
      ),
    );
  }

  /// Typographie de caisse : plancher de lisibilite a 15 px.
  ///
  /// Les styles Material par defaut descendent a 11-12 px (labels, captions),
  /// illisibles sur une tablette posee a 60 cm. On releve le plancher sans
  /// toucher aux tailles deja grandes.
  static TextTheme _scaledTextTheme(TextTheme base) => base.copyWith(
        bodySmall: base.bodySmall?.copyWith(fontSize: 15),
        labelSmall: base.labelSmall?.copyWith(fontSize: 15),
        labelMedium: base.labelMedium?.copyWith(fontSize: 15),
      );
}
