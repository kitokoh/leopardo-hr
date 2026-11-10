import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

/// Thème de l'application grand public Leopardo (`leopardo_public`).
///
/// ## Pourquoi ce fichier existe
///
/// L'app utilisait `TenantTheme.apply(ThemeData.light(), null)` : le thème
/// **tenant** générique, partagé avec les apps métier. Avec `branding == null`,
/// `TenantTheme.apply` retourne la `ThemeData` Material **inchangée** — l'app
/// grand public n'avait donc aucune identité visuelle et ne ressemblait pas au
/// portail web (`/`, `/decouvrir`).
///
/// ## Source unique des tokens
///
/// Les couleurs ne sont PAS définies ici : elles vivent dans
/// `leopardo_core/lib/core/theme/app_colors.dart` (tokens `public*`), seule
/// source autorisée côté mobile — la garde
/// `dev-hub/tools/validate-mobile-color-tokens.ps1` rejette tout littéral
/// `Color(0x...)` hors de ce fichier. Les constantes ci-dessous sont des
/// alias de lecture vers `AppColors`.
///
/// Les valeurs de `AppColors.public*` sont la copie exacte de
/// `front/web/tailwind.config.ts` (palette `emerald` / `slate`). Toute
/// modification doit être faite **des deux côtés** : c'est le prix d'un
/// langage visuel commun entre deux stacks. La table de correspondance
/// ci-dessous rend la dérive visible en revue.
///
/// | Token Flutter        | Source web                          |
/// |----------------------|-------------------------------------|
/// | `emerald600`         | `bg-emerald-600` (CTA principal)    |
/// | `emerald500`         | `bg-emerald-500` (CTA sur fond noir)|
/// | `emerald700`         | `text-emerald-700` (liens)          |
/// | `slate50`            | `bg-slate-50` (fond clair)          |
/// | `slate950`           | `bg-slate-950` (fond sombre)        |
/// | `slate900`           | `text-slate-900` (texte clair)      |
/// | `slate100`           | `text-slate-100` (texte sombre)     |
/// | `radiusXl = 12`      | `rounded-xl` (boutons, champs)      |
/// | `radius2xl = 16`     | `rounded-2xl` (cartes)              |
///
/// ## Ce que le thème NE fait pas
///
/// Il ne remplace pas `TenantTheme` : les apps métier continuent d'appliquer
/// la marque de leur tenant. Ici il n'y a pas de tenant — le portail public
/// est mono-marque, donc un thème statique est le bon outil.
class PublicTheme {
  const PublicTheme._();

  // ── Palette ─────────────────────────────────────────────────────────────
  // Les valeurs vivent dans `AppColors` (source unique côté mobile, gardée par
  // `dev-hub/tools/validate-mobile-color-tokens.ps1`). Ces alias ne sont que
  // des raccourcis de lecture : ils ne redéfinissent AUCUNE couleur.
  static const Color emerald50 = AppColors.publicEmerald50;
  static const Color emerald100 = AppColors.publicEmerald100;
  static const Color emerald400 = AppColors.publicEmerald400;
  static const Color emerald500 = AppColors.publicEmerald500;
  static const Color emerald600 = AppColors.publicEmerald600;
  static const Color emerald700 = AppColors.publicEmerald700;

  static const Color slate50 = AppColors.publicSlate50;
  static const Color slate100 = AppColors.publicSlate100;
  static const Color slate200 = AppColors.publicSlate200;
  static const Color slate400 = AppColors.publicSlate400;
  static const Color slate500 = AppColors.publicSlate500;
  static const Color slate600 = AppColors.publicSlate600;
  static const Color slate900 = AppColors.publicSlate900;
  static const Color slate950 = AppColors.publicSlate950;

  // ── Rayons (rounded-xl / rounded-2xl) ───────────────────────────────────
  static const double radiusXl = 12;
  static const double radius2xl = 16;

  /// Thème clair — équivalent de `bg-slate-50 text-slate-900`.
  static ThemeData light() {
    const scheme = ColorScheme.light(
      primary: emerald600,
      onPrimary: Colors.white,
      primaryContainer: emerald50,
      onPrimaryContainer: emerald700,
      secondary: emerald500,
      onSecondary: Colors.white,
      surface: Colors.white,
      onSurface: slate900,
      surfaceContainerHighest: slate100,
      onSurfaceVariant: slate600,
      outline: slate200,
      outlineVariant: slate200,
      error: AppColors.publicError,
      onError: Colors.white,
    );

    return _base(scheme, Brightness.light);
  }

  /// Thème sombre — équivalent de `dark:bg-slate-950 dark:text-slate-100`.
  static ThemeData dark() {
    const scheme = ColorScheme.dark(
      primary: emerald400,
      onPrimary: slate950,
      primaryContainer: AppColors.publicEmerald700,
      onPrimaryContainer: emerald100,
      secondary: emerald500,
      onSecondary: slate950,
      surface: slate950,
      onSurface: AppColors.publicTextDark,
      surfaceContainerHighest: AppColors.publicSurfaceDarkAlt,
      onSurfaceVariant: slate400,
      outline: AppColors.publicBorderDark,
      outlineVariant: AppColors.publicSurfaceDarkAlt,
      error: AppColors.publicErrorDark,
      onError: slate950,
    );

    return _base(scheme, Brightness.dark);
  }

  /// Squelette commun aux deux modes.
  ///
  /// Tout ce qui dépend de la luminosité passe par `scheme` : aucun
  /// `Colors.grey` / `Colors.black` codé en dur, sinon une couleur Material
  /// par défaut fuiterait dans l'un des deux modes.
  static ThemeData _base(ColorScheme scheme, Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final base = ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: isDark ? slate950 : slate50,
      canvasColor: isDark ? slate950 : Colors.white,
      splashFactory: InkSparkle.splashFactory,
    );

    final roundedXl = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(radiusXl),
    );

    return base.copyWith(
      // Typographie : le web utilise Inter (next/font). On garde la police
      // système côté mobile (pas d'asset à embarquer) mais on aligne les
      // graisses et les interlignes sur les classes du portail.
      textTheme: base.textTheme.copyWith(
        displaySmall: base.textTheme.displaySmall?.copyWith(
          fontWeight: FontWeight.w900,
          letterSpacing: -0.5,
          color: scheme.onSurface,
        ),
        headlineMedium: base.textTheme.headlineMedium?.copyWith(
          fontWeight: FontWeight.w900,
          letterSpacing: -0.4,
          color: scheme.onSurface,
        ),
        titleLarge: base.textTheme.titleLarge?.copyWith(
          fontWeight: FontWeight.w800,
          color: scheme.onSurface,
        ),
        titleMedium: base.textTheme.titleMedium?.copyWith(
          fontWeight: FontWeight.w700,
          color: scheme.onSurface,
        ),
        bodyMedium: base.textTheme.bodyMedium?.copyWith(
          height: 1.5,
          color: scheme.onSurfaceVariant,
        ),
        bodySmall: base.textTheme.bodySmall?.copyWith(
          height: 1.45,
          color: scheme.onSurfaceVariant,
        ),
        labelLarge: base.textTheme.labelLarge?.copyWith(
          fontWeight: FontWeight.w700,
        ),
      ),

      appBarTheme: AppBarTheme(
        backgroundColor: isDark ? slate950 : Colors.white,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: base.textTheme.titleLarge?.copyWith(
          fontWeight: FontWeight.w900,
          letterSpacing: -0.3,
          color: scheme.onSurface,
        ),
      ),

      cardTheme: CardThemeData(
        color: isDark ? AppColors.publicSurfaceDark : Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radius2xl),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: roundedXl,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          elevation: 0,
          minimumSize: const Size(0, 48),
          shape: roundedXl,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: scheme.onSurface,
          minimumSize: const Size(0, 48),
          side: BorderSide(color: scheme.outline),
          shape: roundedXl,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: isDark ? emerald400 : emerald700,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? AppColors.publicSurfaceDark : Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        hintStyle: TextStyle(color: scheme.onSurfaceVariant),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusXl),
          borderSide: BorderSide(color: scheme.outline),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusXl),
          borderSide: BorderSide(color: scheme.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusXl),
          borderSide: BorderSide(color: scheme.primary, width: 1.5),
        ),
      ),

      chipTheme: base.chipTheme.copyWith(
        backgroundColor: isDark ? AppColors.publicSurfaceDark : Colors.white,
        selectedColor: isDark ? emerald500.withValues(alpha: 0.18) : emerald50,
        side: BorderSide(color: scheme.outlineVariant),
        labelStyle: TextStyle(
          fontWeight: FontWeight.w600,
          color: scheme.onSurface,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      ),

      dividerTheme: DividerThemeData(
        color: scheme.outlineVariant,
        thickness: 1,
        space: 1,
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: isDark ? slate950 : Colors.white,
        indicatorColor: isDark ? emerald500.withValues(alpha: 0.18) : emerald50,
        elevation: 0,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
            color: states.contains(WidgetState.selected)
                ? (isDark ? emerald400 : emerald700)
                : scheme.onSurfaceVariant,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? (isDark ? emerald400 : emerald700)
                : scheme.onSurfaceVariant,
          ),
        ),
      ),

      snackBarTheme: base.snackBarTheme.copyWith(
        behavior: SnackBarBehavior.floating,
        shape: roundedXl,
        backgroundColor: isDark ? AppColors.publicSurfaceDarkAlt : slate900,
        contentTextStyle: const TextStyle(color: Colors.white),
      ),

      progressIndicatorTheme: ProgressIndicatorThemeData(color: scheme.primary),

      listTileTheme: ListTileThemeData(
        shape: roundedXl,
        iconColor: scheme.onSurfaceVariant,
        textColor: scheme.onSurface,
      ),
    );
  }
}
