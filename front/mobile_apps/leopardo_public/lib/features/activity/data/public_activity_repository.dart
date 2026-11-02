import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

import '../../discovery/data/public_discovery_repository.dart';

/// Activité d'un client public, telle que le serveur la renvoie.
///
/// ## Pourquoi le statut n'est pas calculé ici
///
/// Le serveur relit le statut DANS le schéma du tenant avant de répondre :
/// l'index plateforme n'est qu'une projection qui peut être en retard d'un
/// événement. Recalculer ou deviner un statut côté app produirait exactement
/// l'écran qui ment que le couloir interdit (« votre commande est prête » alors
/// qu'elle ne l'est pas).
class PublicActivity {
  const PublicActivity({
    required this.vertical,
    required this.activityType,
    required this.reference,
    required this.status,
    required this.title,
    required this.occurredAt,
  });

  final String vertical;
  final String activityType;
  final String reference;
  final String status;
  final String title;
  final DateTime? occurredAt;

  bool get isOrder => activityType == 'order';

  factory PublicActivity.fromJson(Map<String, dynamic> json) {
    return PublicActivity(
      vertical: (json['vertical'] ?? '').toString(),
      activityType: (json['activity_type'] ?? '').toString(),
      reference: (json['reference'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      occurredAt: DateTime.tryParse((json['occurred_at'] ?? '').toString()),
    );
  }
}

/// Section de l'onglet Activités : une verticale qui a un CONTRAT.
///
/// Le serveur ne renvoie que les verticales dont le provider déclare le type.
/// L'app n'invente donc jamais une section : c'est ce qui garantit « un module
/// sans contrat n'apparaît pas ».
class PublicActivitySection {
  const PublicActivitySection({required this.vertical, required this.types});

  final String vertical;
  final List<String> types;

  factory PublicActivitySection.fromJson(Map<String, dynamic> json) {
    final rawTypes = json['types'];
    return PublicActivitySection(
      vertical: (json['vertical'] ?? '').toString(),
      types: rawTypes is List
          ? rawTypes.map((e) => e.toString()).toList(growable: false)
          : const <String>[],
    );
  }
}

/// Favori d'un client public.
class PublicFavorite {
  const PublicFavorite({
    required this.targetType,
    required this.targetSlug,
    required this.displayName,
    required this.available,
  });

  final String targetType;
  final String targetSlug;
  final String? displayName;

  /// Faux si le profil n'est plus joignable. Le favori est CONSERVÉ — la
  /// personne l'a choisi — mais l'écran propose autre chose au lieu d'un
  /// bouton mort.
  final bool available;

  factory PublicFavorite.fromJson(Map<String, dynamic> json) {
    final name = json['display_name'];
    return PublicFavorite(
      targetType: (json['target_type'] ?? '').toString(),
      targetSlug: (json['target_slug'] ?? '').toString(),
      displayName: name is String && name.isNotEmpty ? name : null,
      available: json['available'] == true,
    );
  }
}

/// Entrée d'historique de recherche.
class PublicSearchEntry {
  const PublicSearchEntry({
    required this.query,
    required this.city,
    required this.resultCount,
    required this.searchedAt,
  });

  final String query;
  final String? city;
  final int resultCount;
  final DateTime? searchedAt;

  factory PublicSearchEntry.fromJson(Map<String, dynamic> json) {
    final city = json['city'];
    return PublicSearchEntry(
      query: (json['query'] ?? '').toString(),
      city: city is String && city.isNotEmpty ? city : null,
      resultCount: (json['result_count'] as num?)?.toInt() ?? 0,
      searchedAt: DateTime.tryParse((json['searched_at'] ?? '').toString()),
    );
  }
}

/// Résultat d'un chargement d'activités : les lignes ET les sections.
class PublicActivityPage {
  const PublicActivityPage({required this.activities, required this.sections});

  final List<PublicActivity> activities;
  final List<PublicActivitySection> sections;

  bool get isEmpty => activities.isEmpty;
}

/// Dépôt des activités, favoris et historique du client public.
///
/// ## Même classification d'erreurs que la découverte
///
/// Les états obligatoires ne sont pas interchangeables : « hors ligne, voici le
/// cache » n'est pas « erreur, réessayez », et un 404 n'est PAS une panne. Ce
/// dépôt réutilise donc `PublicApiFailure` / `PublicFailureKind` de la
/// découverte plutôt que d'inventer une seconde taxonomie qui divergerait.
///
/// ## Session obligatoire
///
/// Contrairement à la découverte, ces routes exigent `auth:user_api` : on passe
/// `useUserSession: true`. Sans jeton, le serveur répond 401 — que l'app
/// traduit en « connectez-vous », jamais en erreur technique.
class PublicActivityRepository {
  PublicActivityRepository(this._client);

  final ApiClient _client;

  static const String activitiesPath = '/user/activities';
  static const String favoritesPath = '/user/favorites';
  static const String searchHistoryPath = '/user/search-history';

  /// `GET /api/v1/user/activities`
  Future<PublicActivityPage> activities({int perPage = 50}) async {
    final json = await _request(
      activitiesPath,
      method: 'GET',
      query: {'per_page': perPage},
    );

    final rawData = json['data'];
    final rawMeta = json['meta'];

    final activities = rawData is List
        ? rawData
            .whereType<Map>()
            .map((e) => PublicActivity.fromJson(Map<String, dynamic>.from(e)))
            .toList(growable: false)
        : const <PublicActivity>[];

    final sections = rawMeta is Map && rawMeta['sections'] is List
        ? (rawMeta['sections'] as List)
            .whereType<Map>()
            .map(
              (e) => PublicActivitySection.fromJson(
                Map<String, dynamic>.from(e),
              ),
            )
            .toList(growable: false)
        : const <PublicActivitySection>[];

    return PublicActivityPage(activities: activities, sections: sections);
  }

  /// `GET /api/v1/user/favorites`
  Future<List<PublicFavorite>> favorites() async {
    final json = await _request(favoritesPath, method: 'GET');
    final raw = json['data'];

    if (raw is! List) return const <PublicFavorite>[];

    return raw
        .whereType<Map>()
        .map((e) => PublicFavorite.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);
  }

  /// `POST /api/v1/user/favorites` — idempotent côté serveur.
  Future<void> addFavorite(String type, String slug) async {
    await _request(
      favoritesPath,
      method: 'POST',
      body: {'target_type': type, 'target_slug': slug},
    );
  }

  /// `DELETE /api/v1/user/favorites/{type}/{slug}` — idempotent côté serveur.
  Future<void> removeFavorite(String type, String slug) async {
    await _request('$favoritesPath/$type/$slug', method: 'DELETE');
  }

  /// `GET /api/v1/user/search-history`
  Future<List<PublicSearchEntry>> searchHistory() async {
    final json = await _request(searchHistoryPath, method: 'GET');
    final raw = json['data'];

    if (raw is! List) return const <PublicSearchEntry>[];

    return raw
        .whereType<Map>()
        .map((e) => PublicSearchEntry.fromJson(Map<String, dynamic>.from(e)))
        .toList(growable: false);
  }

  /// `POST /api/v1/user/search-history`
  ///
  /// Aucune coordonnée n'est envoyée : `city` est une ville, pas une position.
  Future<void> recordSearch(
    String query, {
    String? city,
    int resultCount = 0,
  }) async {
    await _request(
      searchHistoryPath,
      method: 'POST',
      body: {
        'query': query,
        if (city != null && city.isNotEmpty) 'city': city,
        'result_count': resultCount,
      },
    );
  }

  /// `DELETE /api/v1/user/search-history`
  Future<void> clearSearchHistory() async {
    await _request(searchHistoryPath, method: 'DELETE');
  }

  Future<Map<String, dynamic>> _request(
    String path, {
    required String method,
    Map<String, dynamic>? query,
    Map<String, dynamic>? body,
  }) async {
    try {
      final response = await _client.requestWithRetry<dynamic>(
        path,
        method: method,
        queryParameters: query,
        data: body,
        // Ces routes exigent `auth:user_api` : le jeton vient du Keystore.
        useUserSession: true,
      );

      final data = response.data;

      // 204 sans corps (suppressions, enregistrement d'historique) : succès.
      if (data == null) return const <String, dynamic>{};

      if (data is Map<String, dynamic>) return data;
      if (data is Map) return Map<String, dynamic>.from(data);

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
