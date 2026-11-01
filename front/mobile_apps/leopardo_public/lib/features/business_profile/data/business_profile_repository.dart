import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';

import '../../discovery/data/public_discovery_repository.dart';
import '../models/business_profile.dart';

/// Dépôt de la fiche commerce en LECTURE SEULE.
///
/// ## Une seule route pour plusieurs verticales
///
/// `GET /public/restaurants/{slug}` est aujourd'hui la seule fiche publique
/// complète servie (horaires + menu). Les autres verticales publient une fiche
/// dans `front/web` (`/vitrines/{slug}`), pas dans l'API : elles n'ont donc pas
/// d'endpoint mobile à appeler. On ne FABRIQUE pas d'URL : on tente la route
/// connue pour le type concerné et, sinon, on rend la fiche à partir des
/// données de découverte (dégradée mais honnête — pas de réseau inventé).
///
/// ## Fail-closed sur le 404
///
/// Un 404 signifie « ce slug n'est pas publié, ou la société est suspendue ».
/// Il remonte comme `notFound` et l'écran affiche « ce commerce n'est plus
/// disponible » — jamais « erreur de connexion ». Confondre les deux ferait
/// croire au client à une panne de l'app alors qu'il suit un vieux QR code.
class BusinessProfileRepository {
  BusinessProfileRepository(this._client);

  final ApiClient _client;

  /// Types dont une fiche complète est réellement servie par l'API publique.
  static const Set<String> typesWithDetailEndpoint = {'restaurant'};

  String detailPath(String type, String slug) =>
      '/public/restaurants/$slug';

  /// Charge la fiche ; [fallback] évite un appel réseau inutile pour les
  /// verticales sans endpoint de détail.
  Future<BusinessProfile> load({
    required String type,
    required String slug,
    Map<String, dynamic>? fallbackItem,
    List<String> supportedActions = const [],
    Map<String, String?> actionUrls = const {},
    CancelToken? cancelToken,
  }) async {
    if (!typesWithDetailEndpoint.contains(type)) {
      // Pas d'endpoint de détail pour cette verticale : on ne simule pas un
      // appel. La fiche reste consultable avec ce que la découverte a exposé.
      if (fallbackItem != null) {
        return BusinessProfile.fromDiscoveryItem(
          fallbackItem,
          supportedActions: supportedActions,
          actionUrls: actionUrls,
        );
      }
      throw const PublicApiFailure(
        PublicFailureKind.notFound,
        message: 'No public detail endpoint for this type',
      );
    }

    try {
      final response = await _client.requestWithRetry<dynamic>(
        detailPath(type, slug),
        method: 'GET',
        useUserSession: false,
      );

      final body = response.data;
      final data = body is Map ? body['data'] : null;

      final profile = BusinessProfile.tryParse(slug, data);
      if (profile == null) {
        throw const PublicApiFailure(PublicFailureKind.notFound);
      }

      // `supportedActions()` est la source de vérité des boutons ; la fiche
      // seule ne le porte pas encore. On propage donc les capacités que la
      // découverte a déjà reçues : c'est la même donnée serveur, pas une
      // supposition du client.
      return _withActions(profile, supportedActions, actionUrls);
    } on PublicApiFailure {
      rethrow;
    } on DioException catch (e) {
      throw _classify(e.response?.statusCode, e);
    }
  }

  BusinessProfile _withActions(
    BusinessProfile profile,
    List<String> actions,
    Map<String, String?> urls,
  ) {
    if (actions.isEmpty && urls.isEmpty) return profile;
    return BusinessProfile(
      slug: profile.slug,
      name: profile.name,
      type: profile.type,
      category: profile.category,
      city: profile.city,
      address: profile.address,
      description: profile.description,
      imageUrl: profile.imageUrl,
      latitude: profile.latitude,
      longitude: profile.longitude,
      phone: profile.phone,
      ratingAvg: profile.ratingAvg,
      reviewsCount: profile.reviewsCount,
      hours: profile.hours,
      menu: profile.menu,
      currency: profile.currency,
      verified: profile.verified,
      supportedActions:
          profile.supportedActions.isEmpty ? actions : profile.supportedActions,
      actionUrls: profile.actionUrls.isEmpty ? urls : profile.actionUrls,
    );
  }

  PublicApiFailure _classify(int? status, Object cause) {
    if (status == null) {
      return PublicApiFailure(PublicFailureKind.network, message: cause.toString());
    }
    if (status == 404) {
      return const PublicApiFailure(PublicFailureKind.notFound, statusCode: 404);
    }
    if (status == 408 || status == 504) {
      return PublicApiFailure(PublicFailureKind.timeout, statusCode: status);
    }
    if (status >= 500) {
      return PublicApiFailure(PublicFailureKind.server, statusCode: status);
    }
    return PublicApiFailure(
      PublicFailureKind.invalidRequest,
      statusCode: status,
      message: cause.toString(),
    );
  }
}

final businessProfileRepositoryProvider = Provider<BusinessProfileRepository>(
  (ref) => BusinessProfileRepository(ref.watch(apiClientProvider)),
);

/// Argument de la fiche : tout ce que l'écran précédent sait déjà.
///
/// Transmettre ces informations évite un appel réseau pour les verticales sans
/// endpoint de détail ET permet d'afficher immédiatement le nom/city pendant le
/// chargement (au lieu d'un squelette anonyme).
class BusinessProfileArgs {
  const BusinessProfileArgs({
    required this.type,
    required this.slug,
    this.name = '',
    this.fallbackItem,
    this.supportedActions = const [],
    this.actionUrls = const {},
  });

  final String type;
  final String slug;
  final String name;
  final Map<String, dynamic>? fallbackItem;
  final List<String> supportedActions;
  final Map<String, String?> actionUrls;

  @override
  bool operator ==(Object other) =>
      other is BusinessProfileArgs && other.type == type && other.slug == slug;

  @override
  int get hashCode => Object.hash(type, slug);
}

final businessProfileProvider =
    FutureProvider.family<BusinessProfile, BusinessProfileArgs>((ref, args) {
  return ref.watch(businessProfileRepositoryProvider).load(
        type: args.type,
        slug: args.slug,
        fallbackItem: args.fallbackItem,
        supportedActions: args.supportedActions,
        actionUrls: args.actionUrls,
      );
});
