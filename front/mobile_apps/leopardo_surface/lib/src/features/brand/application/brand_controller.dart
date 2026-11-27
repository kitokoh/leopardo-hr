/// #9581 (BC-41 BRANDING) — contrôleur de marque (couche application).
///
/// ## Ce que ce contrôleur garantit
///
/// 1. **Une marque indisponible ne bloque jamais l'app.** En cas d'échec
///    réseau ou de marque absente, `brand` reste `null` et l'app garde son
///    thème propre. C'est la condition de non-régression : la feature est
///    additive, donc son absence doit être un état valide.
/// 2. **La locale rendue est résolue une fois.** Une locale demandée mais non
///    publiée retombe sur la locale par défaut de la MARQUE, pas sur `fr` en
///    dur.
/// 3. **Aucun `Color` ici.** Les couleurs restent des chaînes hexadécimales
///    jusqu'à la couche de thème — c'est ce qui respecte la garde
///    `validate-mobile-color-tokens`.
///
/// ## Riverpod 3 `Notifier`, pas `StateNotifier`
///
/// Le dépôt est sur `flutter_riverpod ^3.4.2`, où `StateNotifier` est
/// déprécié. On suit le style de `DiscoveryController` : un `Notifier` avec
/// une méthode `build()` qui rend l'état initial.
library;

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/brand.dart';
import '../infrastructure/brand_api.dart';

/// État exposé aux écrans.
class BrandState {
  const BrandState({
    this.brand,
    this.outcome,
    this.isLoading = false,
  });

  const BrandState.loading() : this(isLoading: true);

  const BrandState.initial() : this();

  final Brand? brand;
  final BrandLoadOutcome? outcome;
  final bool isLoading;

  /// Vrai si une marque exploitable est disponible.
  bool get hasBrand => brand != null;

  /// La marque, ou `null` — l'appelant retombe sur le thème de l'app.
  Brand? get value => brand;
}

/// Chargeur de marque de l'application.
///
/// **À surcharger** au démarrage de l'app avec
/// `dioBrandLoader(apiClient)` (voir `dio_brand_loader.dart`).
///
/// La valeur par défaut n'est PAS un `throw` : elle répond « pas de marque ».
/// Un provider qui lève à la lecture ferait planter l'écran d'une app qui a
/// simplement oublié de brancher la feature — alors que le contrat de cette
/// feature est justement de ne jamais casser l'app. Un oubli de branchement
/// doit se voir comme « pas de marque dynamique », pas comme un crash.
final brandLoaderProvider = Provider<BrandJsonLoader>((ref) {
  return (String path) async => null;
});

/// Contrôleur : charge et expose une marque.
///
/// Il ne connaît ni Dio ni le conteneur : le chargeur est injecté. Un widget
/// peut donc le tester avec une simple closure.
class BrandController extends Notifier<BrandState> {
  @override
  BrandState build() => const BrandState.initial();

  /// Charge la marque d'un slug, dans la locale demandée si elle est publiée.
  ///
  /// Rechargeable : un changement d'enseigne (deep link, app multi-marques)
  /// rappelle simplement `load`.
  Future<void> load(String slug, {String? requestedLocale}) async {
    if (slug.trim().isEmpty) {
      state = const BrandState();
      return;
    }

    state = const BrandState.loading();

    final loader = ref.read(brandLoaderProvider);

    final result = await loadBrand(
      slug: slug,
      locale: normalizeBrandLocale(requestedLocale),
      loader: loader,
    );

    state = BrandState(brand: result.brand, outcome: result.outcome);
  }

  /// Efface la marque : l'app revient à son thème propre.
  void reset() {
    state = const BrandState();
  }
}

/// Le contrôleur pour un slug donné.
///
/// `autoDispose` : une marque ne doit pas survivre à l'écran qui l'affiche.
/// Garder une marque en mémoire après la sortie d'un établissement ferait
/// apparaître les couleurs de l'enseigne précédente au retour sur l'accueil.
final brandControllerProvider = NotifierProvider.autoDispose
    .family<BrandController, BrandState, String>((slug) {
  return BrandController();
});

/// Charge une marque depuis un widget, sans passer par le provider.
///
/// Utile pour les écrans qui n'ont pas besoin de l'état réactif (un en-tête
/// d'établissement, par exemple) : ils obtiennent la marque ou `null` et
/// gardent leur thème si elle est absente.
Future<Brand?> loadBrandOnce(
  WidgetRef ref,
  String slug, {
  String? requestedLocale,
}) async {
  final result = await loadBrand(
    slug: slug,
    locale: normalizeBrandLocale(requestedLocale),
    loader: ref.read(brandLoaderProvider),
  );

  return result.brand;
}
