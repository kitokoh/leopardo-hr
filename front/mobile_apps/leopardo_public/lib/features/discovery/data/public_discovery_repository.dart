import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

import '../models/discovery_category.dart';
import '../models/discovery_item.dart';

/// Échec de chargement, déjà classé pour que l'interface choisisse le bon état.
///
/// ## Pourquoi classer plutôt que laisser remonter l'exception
///
/// Les états obligatoires du couloir 16 ne sont pas interchangeables : « hors
/// ligne, voici le cache » n'est pas « erreur, réessayez », et « ce commerce
/// n'existe plus » n'est **pas** une panne. Laisser un `DioException` remonter
/// jusqu'au widget forcerait chaque écran à réinterpréter des codes HTTP — et
/// le premier écran qui oublierait un cas afficherait « erreur » sur un 404.
class PublicApiFailure implements Exception {
  const PublicApiFailure(this.kind, {this.statusCode, this.message});

  final PublicFailureKind kind;
  final int? statusCode;
  final String? message;

  /// Vrai quand on peut proposer « Réessayer » sans mentir.
  bool get isRetryable =>
      kind == PublicFailureKind.network ||
      kind == PublicFailureKind.timeout ||
      kind == PublicFailureKind.server;

  @override
  String toString() =>
      'PublicApiFailure(${kind.name}, status: $statusCode, message: $message)';
}

enum PublicFailureKind {
  /// Pas de réseau (avion, tunnel) — afficher le cache, jamais une erreur brute.
  network,

  /// Délai dépassé — côté serveur lent ou réseau très dégradé.
  timeout,

  /// 5xx — le service a un problème, réessayer a du sens.
  server,

  /// 404 — la ressource n'existe pas ou n'est plus publiée. PAS une panne.
  notFound,

  /// 4xx métier (422 paramètre invalide, 429 quota) — réessayer à l'identique
  /// ne changera rien.
  invalidRequest,
}

/// Dépôt de lecture de la découverte publique.
///
/// ## Aucun jeton, aucun tenant
///
/// Chaque appel vise une route publique (`throttle:shop-public`) qui ne lit ni
/// `Authorization` ni contexte tenant : la découverte est consultable SANS
/// compte, ce qui est la condition même du parcours « ouverture → recherche →
/// fiche ». On n'envoie donc PAS de `X-User-Session` : envoyer un jeton inutile
/// élargirait inutilement la surface (un jeton volé sur cet appareil serait
/// présenté à des routes qui n'en ont pas besoin).
///
/// ## Ce que ce dépôt ne fait pas
///
/// Il ne met rien en cache sur disque : le cache de dernier écran est géré par
/// le provider (`DiscoveryCache`), pour que la politique « quoi garder » soit
/// visible à un seul endroit et testable sans I/O.
class PublicDiscoveryRepository {
  PublicDiscoveryRepository(this._client);

  final ApiClient _client;

  static const String discoverPath = '/public/discover';
  static const String categoriesPath = '/public/discovery/categories';

  /// `GET /api/v1/public/discover`
  ///
  /// [near] au format `lat,lng` n'est envoyé QUE si la position a été obtenue
  /// avec le consentement de la personne — l'appelant en est responsable.
  Future<DiscoveryPage> discover({
    String? query,
    String? city,
    String? type,
    String? near,
    double? radiusKm,
    bool openNow = false,
    int page = 1,
    int perPage = 20,
  }) async {
    // `open_now` est délibérément ABSENT de la requête : le contrat public ne
    // le connaît pas encore (voir COULOIR16_PLAN.md §5, manque M1-3). On le
    // conserve en paramètre pour que l'appelant exprime l'intention, et on
    // filtre côté client sur les horaires quand la fiche les expose. Envoyer
    // un paramètre inconnu ferait échouer la validation (`sometimes` ne
    // rejette pas, mais un futur `Rule::in` le ferait) et surtout donnerait
    // l'illusion d'un filtrage serveur.
    final params = <String, dynamic>{
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
      if (city != null && city.trim().isNotEmpty) 'city': city.trim(),
      if (type != null && type.trim().isNotEmpty) 'type': type.trim(),
      if (near != null && near.trim().isNotEmpty) 'near': near.trim(),
      if (near != null && radiusKm != null) 'radius_km': radiusKm,
      'per_page': perPage,
      'page': page,
    };

    final json = await _getMap(discoverPath, params);
    return DiscoveryPage.fromJson(json);
  }

  /// `GET /api/v1/public/discovery/categories?city=…`
  Future<DiscoveryCategories> categories(String? city) async {
    final json = await _getMap(
      categoriesPath,
      {if (city != null && city.isNotEmpty) 'city': city},
    );
    return DiscoveryCategories.fromJson(json);
  }

  Future<Map<String, dynamic>> _getMap(
    String path,
    Map<String, dynamic> params,
  ) async {
    try {
      final response = await _client.requestWithRetry<dynamic>(
        path,
        method: 'GET',
        queryParameters: params,
        // Pas de session user_api : route publique. `requestWithRetry` ne
        // rejoue JAMAIS une mutation et n'accepte pas de CancelToken ; les GET
        // de cette app sont idempotents et courts, l'annulation fine n'apporte
        // rien ici.
        useUserSession: false,
      );

      final data = response.data;
      if (data is Map<String, dynamic>) return data;
      if (data is Map) return Map<String, dynamic>.from(data);

      // Un 2xx dont le corps n'est pas un objet JSON est une réponse cassée
      // (page d'erreur d'un proxy, contenu tronqué) : la traiter comme une
      // erreur serveur vaut mieux que d'afficher une liste vide trompeuse.
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Unexpected response body',
      );
    } on PublicApiFailure {
      rethrow;
    } on DioException catch (e) {
      throw _classify(e.response?.statusCode, e);
    } on ApiException catch (e) {
      throw _classify(e.statusCode, e);
    }
  }

  PublicApiFailure _classify(int? status, Object cause) {
    if (status == null) {
      // Pas de status : la requête n'a jamais abouti (réseau, DNS, TLS).
      return PublicApiFailure(
        PublicFailureKind.network,
        message: cause.toString(),
      );
    }
    if (status == 404) {
      return PublicApiFailure(PublicFailureKind.notFound, statusCode: status);
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
