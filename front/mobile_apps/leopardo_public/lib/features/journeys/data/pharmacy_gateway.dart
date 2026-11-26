import '../../discovery/data/public_discovery_repository.dart';
import '../domain/pharmacy_models.dart';

/// Parcours de la verticale **Pharmacie** (pharmacy) — annuaire des officines.
///
/// ## Le contrat, tel qu'il existe réellement
///
/// `GET /public/discover?type=pharmacy&q&city&page&per_page` — l'agrégateur
/// public de découverte, filtré sur le type `pharmacy`. Il n'existe **pas** de
/// route de lecture unitaire par slug : la fiche se résout par filtrage de la
/// liste, exactement comme le fait la vitrine web (`loadPharmacy`).
///
/// ## Ce que cette passerelle ne fait pas
///
/// Elle n'appelle **pas** `POST /public/contact-requests` : `pharmacy` n'est
/// pas dans `PublicProfileLocator::types()`, la requête serait refusée en 422.
/// Le blocage `contact` du registre reste donc légitime, et l'écran ne propose
/// pas de formulaire de contact.
///
/// Elle ne met rien en cache : la politique de cache appartient au provider de
/// découverte, pas à un parcours.
class PharmacyGateway {
  PharmacyGateway(this._discovery);

  final PublicDiscoveryRepository _discovery;

  static const String discoveryType = 'pharmacy';

  /// Annuaire des officines publiées.
  ///
  /// Une panne API rend `available: false` plutôt qu'une exception : l'écran
  /// doit pouvoir distinguer « aucune officine publiée » de « service
  /// indisponible », et c'est la même distinction que fait le web.
  Future<PharmacyDirectory> directory({
    String? query,
    String? city,
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final result = await _discovery.discover(
        query: query,
        city: city,
        type: discoveryType,
        page: page,
        perPage: perPage,
      );

      final pharmacies = <Pharmacy>[];
      for (final item in result.items) {
        // Le type est déjà filtré côté serveur, mais un agrégateur qui
        // ignorerait `type` ne doit pas faire apparaître une boutique dans
        // l'annuaire des pharmacies.
        if (item.type != discoveryType) continue;
        final pharmacy = Pharmacy.tryParse({
          'slug': item.slug,
          'name': item.name,
          'category': item.category,
          'city': item.city,
          'country': item.country,
          'description': item.description,
        });
        if (pharmacy != null) pharmacies.add(pharmacy);
      }

      return PharmacyDirectory(
        available: true,
        pharmacies: pharmacies,
        lastPage: result.lastPage,
      );
    } on PublicApiFailure {
      return PharmacyDirectory.unavailable;
    }
  }

  /// Fiche d'une officine, résolue par filtrage de l'annuaire.
  ///
  /// `null` = officine introuvable **ou** non publiée. Les deux cas sont
  /// indiscernables côté public, et c'est voulu : révéler qu'un profil existe
  /// mais n'est pas publié serait une fuite d'information commerciale.
  Future<Pharmacy?> bySlug(String slug) async {
    final needle = slug.trim();
    if (needle.isEmpty) return null;

    final result = await directory(query: needle, perPage: 50);
    if (!result.available) return null;

    for (final pharmacy in result.pharmacies) {
      if (pharmacy.slug == needle) return pharmacy;
    }
    return null;
  }
}
