import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

import '../data/public_activity_repository.dart';

/// Providers de l'onglet Activités (couloir 16, M3).
///
/// ## Pourquoi un `FutureProvider` et pas un état mutable
///
/// Les trois listes (activités, favoris, historique) sont en LECTURE. Un
/// `FutureProvider` donne gratuitement les trois états obligatoires —
/// chargement, données, erreur — sans qu'aucun écran ait à les réimplémenter,
/// et `ref.invalidate` suffit à rafraîchir après une action (ajout d'un favori,
/// effacement de l'historique). Un état mutable écrit à la main finirait par
/// diverger du serveur.
///
/// ## Aucun cache hors ligne ici
///
/// Le cache de la dernière recherche et des favoris est un objectif M4
/// (budget de poids, persistance). En M3, l'écran affiche l'état « hors ligne »
/// honnête plutôt qu'une liste périmée présentée comme fraîche.

final publicActivityRepositoryProvider = Provider<PublicActivityRepository>(
  (ref) => PublicActivityRepository(ref.watch(apiClientProvider)),
);

/// Activités du client, avec les sections que le serveur déclare.
final publicActivitiesProvider = FutureProvider<PublicActivityPage>((ref) {
  return ref.watch(publicActivityRepositoryProvider).activities();
});

/// Favoris du client.
final publicFavoritesProvider = FutureProvider<List<PublicFavorite>>((ref) {
  return ref.watch(publicActivityRepositoryProvider).favorites();
});

/// Dernières recherches du client.
final publicSearchHistoryProvider = FutureProvider<List<PublicSearchEntry>>((
  ref,
) {
  return ref.watch(publicActivityRepositoryProvider).searchHistory();
});
