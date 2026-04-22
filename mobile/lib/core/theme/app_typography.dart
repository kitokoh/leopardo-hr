import 'package:flutter/material.dart';

/// APV Design v3 — Typographie Leopardo.
///
/// Regles :
///  - Police : Inter (alignee sur le web via Fonts.bunny/Google Fonts).
///  - 2 tailles principales : body (16) et title (20/24).
///  - 2 poids : regular (400) et bold (600).
///  - Les valeurs sont exposees via [AppTypography.build] pour les
///    injecter dans le [ThemeData].
class AppTypography {
  AppTypography._();

  static const String fontFamily = 'Inter';

  static const TextStyle display = TextStyle(
    fontFamily: fontFamily,
    fontSize: 28,
    fontWeight: FontWeight.w600,
    height: 1.2,
  );

  static const TextStyle title = TextStyle(
    fontFamily: fontFamily,
    fontSize: 20,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  static const TextStyle subtitle = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16,
    fontWeight: FontWeight.w600,
    height: 1.4,
  );

  static const TextStyle body = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static const TextStyle bodySmall = TextStyle(
    fontFamily: fontFamily,
    fontSize: 14,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static const TextStyle caption = TextStyle(
    fontFamily: fontFamily,
    fontSize: 12,
    fontWeight: FontWeight.w500,
    height: 1.4,
  );

  static TextTheme buildTextTheme(Color baseColor) {
    return TextTheme(
      displaySmall: display.copyWith(color: baseColor),
      titleLarge: title.copyWith(color: baseColor),
      titleMedium: subtitle.copyWith(color: baseColor),
      bodyLarge: body.copyWith(color: baseColor),
      bodyMedium: bodySmall.copyWith(color: baseColor),
      labelSmall: caption.copyWith(color: baseColor),
    );
  }
}
