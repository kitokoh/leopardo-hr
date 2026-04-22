import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_typography.dart';

/// Facade historique. Les nouveaux ecrans doivent utiliser [AppColors] et
/// [AppTypography] directement. Les constantes ci-dessous sont conservees
/// pour compatibilite avec l'existant jusqu'au refactor complet (Sprint C).
class AppTheme {
  static const Color background = AppColors.bgDark;
  static const Color cardColor = AppColors.cardDark;
  static const Color accentGreen = AppColors.rh;
  static const Color accentRed = AppColors.danger;
  static const Color accentYellow = AppColors.warning;
  static const Color textPrimary = AppColors.textDark;
  static const Color textSecondary = AppColors.textMutedDark;

  static ThemeData get darkTheme {
    return ThemeData(
      brightness: Brightness.dark,
      scaffoldBackgroundColor: AppColors.bgDark,
      primaryColor: AppColors.rh,
      cardColor: AppColors.cardDark,
      colorScheme: const ColorScheme.dark(
        primary: AppColors.rh,
        secondary: AppColors.rh,
        surface: AppColors.cardDark,
        error: AppColors.danger,
      ),
      fontFamily: AppTypography.fontFamily,
      textTheme: AppTypography.buildTextTheme(AppColors.textDark),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        centerTitle: true,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.rh,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      ),
    );
  }
}
