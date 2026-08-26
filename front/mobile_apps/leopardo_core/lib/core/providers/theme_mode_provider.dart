/// Issue #5624 — Réglage thème in-app (clair / sombre / automatique).
///
/// Utilisation :
///   final themeMode = ref.watch(themeModeProvider);
///   ref.read(themeModeProvider.notifier).setMode(ThemeMode.light);
library;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

/// [StateNotifier] qui expose et persiste le [ThemeMode] de l'application.
/// Lit la valeur initiale depuis [AppPreferences] au premier accès.
class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier(this._ref) : super(ThemeMode.system) {
    // Lire la valeur persistée pour initialiser l'état.
    state = _ref.read(appPreferencesProvider).themeMode;
  }

  final Ref _ref;

  /// Change et persiste le thème.
  Future<void> setMode(ThemeMode mode) async {
    state = mode;
    await _ref.read(appPreferencesProvider).saveThemeMode(mode);
  }
}

/// Provider global du thème in-app.
/// À regarder dans [MaterialApp.themeMode] :
///   themeMode: ref.watch(themeModeProvider),
final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>(
  (ref) => ThemeModeNotifier(ref),
);
