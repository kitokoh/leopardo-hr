/// #9581 (BC-41 BRANDING) — accès réseau au contrat public d'une marque.
///
/// ## Pourquoi un client dédié et pas le repository de découverte
///
/// `PublicDiscoveryRepository` porte les états du couloir 16 (cache hors ligne,
/// géolocalisation, catégories). La marque n'a rien de tout cela : c'est un
/// contrat d'identité, court, sans cache hors ligne — une marque périmée
/// afficherait les anciennes couleurs d'une enseigne rebrandée.
///
/// Le client réutilise `ApiClient.resolveBaseUrl()` du core : une seule
/// résolution d'URL dans tout le mobile, donc pas de seconde source qui
/// divergerait du fail-closed release (#4524 / #7963).
///
/// ## Échec = résultat explicite, jamais une exception qui remonte à l'écran
///
/// Une surface publique qui ne répond pas ne doit pas produire un écran
/// d'erreur : elle doit laisser l'app afficher son thème propre. C'est ce qui
/// rend la feature véritablement additive — son indisponibilité n'empêche
/// jamais l'app de fonctionner.
library;

import '../domain/brand.dart';

/// Résultat d'un chargement de marque.
enum BrandLoadOutcome {
  /// Une marque valide a été chargée.
  found,

  /// L'API a répondu 404 : la marque n'existe pas ou n'est pas publiée.
  notFound,

  /// Réseau, timeout ou 5xx : la marque n'a pas pu être vérifiée.
  unavailable,

  /// La réponse était malformée : elle a été rejetée plutôt que rendue.
  invalid,
}

/// Résultat complet d'un chargement.
class BrandLoadResult {
  const BrandLoadResult(this.outcome, {this.brand});

  const BrandLoadResult.found(Brand brand)
      : this(BrandLoadOutcome.found, brand: brand);

  final BrandLoadOutcome outcome;
  final Brand? brand;

  bool get isFound => outcome == BrandLoadOutcome.found && brand != null;
}

/// Signature d'un chargeur de JSON public — injecté pour rester testable.
///
/// Retourne `null` sur 404, lève sur réseau/timeout, et rend l'enveloppe
/// décodée sinon. C'est exactement ce que fait `ApiClient` du core.
typedef BrandJsonLoader = Future<Map<String, dynamic>?> Function(String path);

/// Chemin public d'une marque (le préfixe `/v1` est posé par `ApiClient`).
String brandPublicPath(String slug, {String? locale}) {
  final encoded = Uri.encodeComponent(slug.trim());

  return locale == null || locale.isEmpty
      ? '/public/brands/$encoded'
      : '/public/brands/$encoded?lang=${Uri.encodeComponent(locale)}';
}

/// Charge une marque publique par son slug.
///
/// `loader` est l'adaptateur réseau (voir `brand_repository.dart`) : cette
/// fonction se contente de classer le résultat, ce qui la rend testable sans
/// serveur — on lui passe une closure qui renvoie un JSON fixe.
Future<BrandLoadResult> loadBrand({
  required String slug,
  String? locale,
  required BrandJsonLoader loader,
}) async {
  try {
    final payload = await loader(brandPublicPath(slug, locale: locale));

    if (payload == null) {
      return const BrandLoadResult(BrandLoadOutcome.notFound);
    }

    // L'API enveloppe le DTO dans `{ data: … }` (convention JsonResource).
    final data = payload['data'];
    final map = data is Map<String, dynamic> ? data : payload;

    final brand = Brand.tryParse(map);

    return brand == null
        ? const BrandLoadResult(BrandLoadOutcome.invalid)
        : BrandLoadResult.found(brand);
  } on Object {
    // Volontairement large : un timeout, un 5xx, une réponse tronquée — tout
    // se traduit par « indisponible », et l'app garde son thème. Laisser une
    // exception remonter transformerait un incident réseau en écran d'erreur
    // sur une app qui fonctionne par ailleurs.
    return const BrandLoadResult(BrandLoadOutcome.unavailable);
  }
}
