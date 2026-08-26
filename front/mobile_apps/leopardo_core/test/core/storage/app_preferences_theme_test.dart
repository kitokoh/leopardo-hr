import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';

// #5624 — thème in-app : la préférence themeMode ('system'|'light'|'dark')
// est persistée via AppPreferences (fallback mémoire sans box Hive).
void main() {
  test('themeMode defaults to system when unset', () {
    final preferences = AppPreferences();

    expect(preferences.themeModeSetting, 'system');
    expect(preferences.themeMode, ThemeMode.system);
  });

  test('saveThemeMode persists and exposes the ThemeMode', () async {
    final preferences = AppPreferences();

    await preferences.saveThemeMode(ThemeMode.light);

    expect(preferences.themeModeSetting, 'light');
    expect(preferences.themeMode, ThemeMode.light);

    await preferences.saveThemeMode(ThemeMode.dark);

    expect(preferences.themeModeSetting, 'dark');
    expect(preferences.themeMode, ThemeMode.dark);
  });

  test('saveThemeMode round-trips back to system', () async {
    final preferences = AppPreferences();

    await preferences.saveThemeMode(ThemeMode.light);
    await preferences.saveThemeMode(ThemeMode.system);

    expect(preferences.themeModeSetting, 'system');
    expect(preferences.themeMode, ThemeMode.system);
  });
}
