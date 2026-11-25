import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

import '../../discovery/data/public_discovery_repository.dart';

/// Client des parcours transactionnels publics.
///
/// ## Pourquoi une couche et pas trois appels `requestWithRetry` par écran
///
/// Trois parcours (commande, réservation, demande) partagent la même politique :
/// aucune session (`useUserSession: false`), pas de rejeu automatique sur une
/// mutation, et une classification d'erreur unique. Écrire cette politique dans
/// chaque écran garantirait qu'un des trois l'oublie — et le premier oubli
/// enverrait un jeton public à une route qui n'en a pas besoin, ou rejouerait
/// une commande.
///
/// ## Le rejeu est porté par la CLÉ D'IDEMPOTENCE, pas par le client
///
/// `requestWithRetry` ne rejoue jamais une mutation. Un retry réseau réel passe
/// donc par la clé d'idempotence (`idempotency_key`) fournie par les
/// passerelles : rejouer avec la même clé rend la même commande (201 puis 200
/// côté serveur), sans doublon.
class JourneyApi {
  JourneyApi(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    return _send(
      () => _client.requestWithRetry<dynamic>(
        path,
        method: 'GET',
        queryParameters: query,
        useUserSession: false,
      ),
    );
  }

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> body,
  ) async {
    return _send(
      () => _client.requestWithRetry<dynamic>(
        path,
        method: 'POST',
        data: body,
        useUserSession: false,
      ),
    );
  }

  Future<Map<String, dynamic>> _send(
    Future<Response<dynamic>> Function() call,
  ) async {
    try {
      final response = await call();
      final data = response.data;
      if (data is Map) return Map<String, dynamic>.from(data);

      // Un 2xx sans objet JSON est une réponse cassée (proxy, troncature) :
      // la traiter comme une erreur serveur vaut mieux que de rendre un
      // objet vide qui ferait croire à une confirmation sans référence.
      throw const PublicApiFailure(
        PublicFailureKind.server,
        message: 'Unexpected response body',
      );
    } on PublicApiFailure {
      rethrow;
    } on DioException catch (e) {
      throw classifyPublicFailure(e.response?.statusCode, e);
    } on ApiException catch (e) {
      throw classifyPublicFailure(e.statusCode, e);
    }
  }

  /// Corps `data` d'une réponse, ou objet vide si absent.
  static Map<String, dynamic> dataOf(Map<String, dynamic> json) {
    final data = json['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return const <String, dynamic>{};
  }

  /// Liste `data` d'une réponse, ou liste vide si absente.
  static List<Object?> listOf(Map<String, dynamic> json) {
    final data = json['data'];
    if (data is List) return data;
    return const <Object?>[];
  }
}
