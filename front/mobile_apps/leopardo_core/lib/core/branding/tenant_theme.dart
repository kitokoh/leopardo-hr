import 'package:flutter/material.dart';
import 'package:leopardo_core/core/branding/tenant_branding.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

class TenantTheme {
  const TenantTheme._();

  static ThemeData apply(ThemeData base, TenantBranding? branding) {
    if (branding == null || !branding.hasCustomIdentity) {
      return base;
    }

    final primary = _accessibleAccent(branding.safePrimaryColor);
    final secondary = _accessibleAccent(branding.safeAccentColor);
    final scheme = base.colorScheme.copyWith(
      primary: primary,
      onPrimary: Colors.white,
      secondary: secondary,
      onSecondary: Colors.white,
    );

    return base.copyWith(
      primaryColor: primary,
      colorScheme: scheme,
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: base.elevatedButtonTheme.style?.copyWith(
          backgroundColor: WidgetStatePropertyAll(primary),
          foregroundColor: const WidgetStatePropertyAll(Colors.white),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: base.filledButtonTheme.style?.copyWith(
          backgroundColor: WidgetStatePropertyAll(primary),
          foregroundColor: const WidgetStatePropertyAll(Colors.white),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: base.textButtonTheme.style?.copyWith(
          foregroundColor: WidgetStatePropertyAll(primary),
        ),
      ),
      floatingActionButtonTheme: base.floatingActionButtonTheme.copyWith(
        backgroundColor: primary,
        foregroundColor: Colors.white,
      ),
      progressIndicatorTheme: base.progressIndicatorTheme.copyWith(
        color: primary,
      ),
      tabBarTheme: base.tabBarTheme.copyWith(
        labelColor: primary,
        indicator: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          color: primary.withValues(
            alpha: base.brightness == Brightness.dark ? 0.22 : 0.14,
          ),
        ),
      ),
      snackBarTheme: base.snackBarTheme.copyWith(actionTextColor: primary),
      inputDecorationTheme: base.inputDecorationTheme.copyWith(
        focusedBorder: _focusedBorder(base, primary),
      ),
    );
  }

  static Color _accessibleAccent(Color color) {
    final luminance = color.computeLuminance();
    if (luminance > 0.72) {
      return AppColors.rh;
    }
    return color;
  }

  static InputBorder? _focusedBorder(ThemeData base, Color primary) {
    final border = base.inputDecorationTheme.focusedBorder;
    if (border is OutlineInputBorder) {
      return border.copyWith(borderSide: BorderSide(color: primary));
    }
    return border;
  }
}
