import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

import '../i18n/app_strings.dart';

/// Catalogue résolu pour la langue en vigueur.
///
/// ## D'où vient la langue
///
/// De `AppPreferences.preferredLanguage` (leopardo_core), déjà hydraté par
/// `StartupGate`. C'est la MÊME valeur que celle envoyée par `ApiClient` dans
/// `Accept-Language` : une seule vérité sur « quelle langue parle cette
/// personne ». Une préférence locale à l'app créerait deux sources qui
/// divergeraient dès le premier changement.
///
/// ## Pourquoi un `Provider` et pas un `StateProvider`
///
/// Un `StateProvider` mutable permettrait à un écran de changer la langue à
/// l'écran sans l'écrire sur disque : l'app repartirait en français au prochain
/// lancement. Le changement de langue passe donc par une écriture de préférence
/// puis invalidation — un seul chemin, traçable.
final appStringsProvider = Provider<AppStrings>((ref) {
  final preferences = ref.watch(appPreferencesProvider);
  return AppStrings.of(preferences.preferredLanguage);
});
