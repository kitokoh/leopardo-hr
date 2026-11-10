import 'package:flutter/material.dart';

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
/// Les valeurs ci-dessous sont la copie exacte de `front/web/tailwind.config.ts`
/// (palette `emerald` / `slate`) et des classes utilisées par le portail web.
/// Toute modification doit être faite **des deux côtés** : c'est le prix d'un
/// langage visuel commun entre deux stacks. La table de correspondance est
/// documentée token par token pour rendre la dérive visible en revue.
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

  // ── Palette (miroir de tailwind.config.ts) ──────────────────────────────
  static const Color emerald50 = Color(0xFFECFDF5);
  static const Color emerald100 = Color(0xFFD1FAE5);
  static const Color emerald400 = Color(0xFF34D399);
  static const Color emerald500 = Color(0xFF10B981);
  static const Color emerald600 = Color(0xFF059669);
  static const Color emerald700 = Color(0xFF047857);

  static const Color slate50 = Color(0xFFF8FAFC);
  static const Color slate100 = Color(0xFFF1F5F9);
  static const Color slate200 = Color(0xFFE2E8F0);
  static const Color slate400 = Color(0xFF94A3B8);
  static const Color slate500 = Color(0xFF64748B);
  static const Color slate600 = Color(0xFF475569);
  static const Color slate900 = Color(0xFF0F172A);
  static const Color slate950 = Color(0xFF020617);

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
      error: Color(0xFFDC2626),
      onError: Colors.white,
    );

    return _base(scheme, Brightness.light);
  }

  /// Thème sombre — équivalent de `dark:bg-slate-950 dark:text-slate-100`.
  static ThemeData dark() {
    const scheme = ColorScheme.dark(
      primary: emerald400,
      onPrimary: slate950,
      primaryContainer: Color(0xFF064E3B),
      onPrimaryContainer: emerald100,
      secondary: emerald500,
      onSecondary: slate950,
      surface: slate950,
      onSurface: Color(0xFFF1F5F9),
      surfaceContainerHighest: Color(0xFF1E293B),
      onSurfaceVariant: slate400,
      outline: Color(0xFF334155),
      outlineVariant: Color(0xFF1E293B),
      error: Color(0xFFF87171),
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
    final rounded2xl = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(radius2xl),
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
        color: isDark ? const Color(0xFF0B1220) : Colors.white,
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
        fillColor: isDark ? const Color(0xFF0B1220) : Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
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
        backgroundColor: isDark ? const Color(0xFF0B1220) : Colors.white,
        selectedColor: isDark
            ? emerald500.withValues(alpha: 0.18)
            : emerald50,
        side: BorderSide(color: scheme.outlineVariant),
        labelStyle: TextStyle(
          fontWeight: FontWeight.w600,
          color: scheme.onSurface,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
        ),
      ),

      dividerTheme: DividerThemeData(
        color: scheme.outlineVariant,
        thickness: 1,
        space: 1,
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: isDark ? slate950 : Colors.white,
        indicatorColor: isDark
            ? emerald500.withValues(alpha: 0.18)
            : emerald50,
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
        backgroundColor: isDark ? const Color(0xFF1E293B) : slate900,
        contentTextStyle: const TextStyle(color: Colors.white),
      ),

      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: scheme.primary,
      ),

      listTileTheme: ListTileThemeData(
        shape: roundedXl,
        iconColor: scheme.onSurfaceVariant,
        textColor: scheme.onSurface,
      ),
    );
  }
}
