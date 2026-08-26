import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';
import 'package:leopardo_core/core/providers/theme_mode_provider.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';

// #5624 — thème in-app : le provider themeModeProvider lit la préférence
// persistée, expose le ThemeMode courant et le réécrit via setMode().
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  ProviderContainer makeContainer() {
    final container = ProviderContainer(
      overrides: [
        appPreferencesProvider.overrideWithValue(AppPreferences()),
      ],
    );
    addTearDown(container.dispose);
    return container;
  }

  test('themeModeProvider defaults to ThemeMode.system', () {
    final container = makeContainer();

    expect(container.read(themeModeProvider), ThemeMode.system);
  });

  test('setMode(light) updates state and persists the preference', () async {
    final container = makeContainer();

    await container.read(themeModeProvider.notifier).setMode(ThemeMode.light);

    expect(container.read(themeModeProvider), ThemeMode.light);
    expect(
      container.read(appPreferencesProvider).themeModeSetting,
      'light',
    );
  });

  test('setMode(dark) then setMode(system) round-trips the persisted value',
      () async {
    final container = makeContainer();

    await container.read(themeModeProvider.notifier).setMode(ThemeMode.dark);
    await container.read(themeModeProvider.notifier).setMode(ThemeMode.system);

    expect(container.read(themeModeProvider), ThemeMode.system);
    expect(container.read(appPreferencesProvider).themeModeSetting, 'system');
  });
}
