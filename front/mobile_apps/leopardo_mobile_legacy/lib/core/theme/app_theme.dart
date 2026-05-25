import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_typography.dart';

/// Theme central Leopardo RH.
///
/// Le mode clair est la presentation par defaut. Le mode sombre reste
/// supporte pour les surfaces qui en ont besoin, sans devenir l experience
/// principale du produit.
class AppTheme {
  AppTheme._();

  // Facade historique conservee pour l'existant.
  static const Color background = AppColors.bgLight;
  static const Color cardColor = AppColors.cardLight;
  static const Color accentGreen = AppColors.rh;
  static const Color accentRed = AppColors.danger;
  static const Color accentYellow = AppColors.warning;
  static const Color textPrimary = AppColors.textLight;
  static const Color textSecondary = AppColors.textMuted;

  static ThemeData get lightTheme => _buildTheme(Brightness.light);
  static ThemeData get darkTheme => _buildTheme(Brightness.dark);

  static ThemeData _buildTheme(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    const mobileDarkBackground = Color(0xFF0B1120);
    const mobileDarkSurface = Color(0xFF111B2E);
    const mobileDarkField = Color(0xFF0C1525);
    const mobileDarkBorder = Color(0xFF1A2B44);
    const mobileDarkText = Color(0xFFE2EAF6);
    const mobileDarkMuted = Color(0xFF7A9CC0);
    final scaffold = isDark ? mobileDarkBackground : AppColors.bgLight;
    final surface = isDark ? mobileDarkSurface : AppColors.cardLight;
    final fieldFill = isDark ? mobileDarkField : AppColors.bgLight;
    final border = isDark ? mobileDarkBorder : AppColors.borderLight;
    final text = isDark ? mobileDarkText : AppColors.textLight;
    final muted = isDark ? mobileDarkMuted : AppColors.textMuted;
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.rh,
      brightness: brightness,
    ).copyWith(
      primary: AppColors.rh,
      onPrimary: Colors.white,
      secondary: AppColors.ia,
      onSecondary: Colors.white,
      surface: surface,
      onSurface: text,
      error: AppColors.danger,
      onError: Colors.white,
      outline: border,
    );

    OutlineInputBorder borderStyle(Color color) {
      return OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: color),
      );
    }

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      scaffoldBackgroundColor: scaffold,
      primaryColor: AppColors.rh,
      cardColor: surface,
      colorScheme: scheme,
      fontFamily: AppTypography.fontFamily,
      textTheme: AppTypography.buildTextTheme(
        text,
      ).apply(bodyColor: text, displayColor: text),
      dividerColor: border,
      appBarTheme: AppBarTheme(
        backgroundColor: scaffold,
        foregroundColor: text,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: AppTypography.title.copyWith(color: text),
        iconTheme: IconThemeData(color: text),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(isDark ? 16 : 24),
          side: BorderSide(color: border, width: isDark ? 0.7 : 1),
        ),
      ),
      dividerTheme: DividerThemeData(color: border, thickness: 1, space: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: fieldFill,
        labelStyle: AppTypography.bodySmall.copyWith(color: muted),
        hintStyle: AppTypography.bodySmall.copyWith(color: muted),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        enabledBorder: borderStyle(border),
        focusedBorder: borderStyle(AppColors.rh),
        errorBorder: borderStyle(AppColors.danger),
        focusedErrorBorder: borderStyle(AppColors.danger),
        border: borderStyle(border),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.rh,
          foregroundColor: Colors.white,
          elevation: 0,
          minimumSize: Size.fromHeight(isDark ? 46 : 52),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(isDark ? 12 : 18),
          ),
          textStyle: AppTypography.subtitle,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.rh,
          foregroundColor: Colors.white,
          minimumSize: Size.fromHeight(isDark ? 46 : 52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(isDark ? 12 : 18),
          ),
          textStyle: AppTypography.subtitle,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: text,
          side: BorderSide(color: border),
          minimumSize: Size.fromHeight(isDark ? 46 : 52),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(isDark ? 12 : 18),
          ),
          textStyle: AppTypography.subtitle,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.rh,
          textStyle: AppTypography.bodySmall.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: surface,
        contentTextStyle: AppTypography.bodySmall.copyWith(color: text),
        actionTextColor: AppColors.rh,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: border),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scaffold,
        modalBackgroundColor: scaffold,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: AppColors.rh,
        unselectedLabelColor: muted,
        indicator: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          color: AppColors.rh.withValues(alpha: isDark ? 0.22 : 0.14),
        ),
        dividerColor: Colors.transparent,
        labelStyle: AppTypography.bodySmall.copyWith(
          fontWeight: FontWeight.w600,
        ),
        unselectedLabelStyle: AppTypography.bodySmall,
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.rh,
        foregroundColor: Colors.white,
      ),
      listTileTheme: ListTileThemeData(
        tileColor: surface,
        iconColor: muted,
        textColor: text,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.rh,
      ),
    );
  }
}
