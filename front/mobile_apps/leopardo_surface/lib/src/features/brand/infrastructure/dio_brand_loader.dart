/// #9581 (BC-41 BRANDING) — adaptateur réseau Dio → chargeur JSON.
///
/// ## Pourquoi cet adaptateur existe
///
/// `loadBrand()` (couche infrastructure) ne dépend pas de `dio` : elle reçoit
/// une closure `BrandJsonLoader`. Ce fichier fournit CETTE closure à partir du
/// `ApiClient` du core.
///
/// La conséquence est concrète : les tests de chargement ne montent ni Dio, ni
/// interception, ni serveur. Ils passent une closure. Le seul fichier qui
/// connaît Dio est celui-ci, et il ne contient aucune règle.
library;

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';

import '../infrastructure/brand_api.dart';

/// Construit un `BrandJsonLoader` à partir du client API du core.
///
/// Le `GET` est idempotent : `requestWithRetry` peut le rejouer sans risque de
/// dupliquer quoi que ce soit (la politique du core refuse de rejouer une
/// mutation).
BrandJsonLoader dioBrandLoader(ApiClient client) {
  return (String path) async {
    try {
      final response = await client.requestWithRetry<Map<String, dynamic>>(
        path,
        options: Options(method: 'GET', responseType: ResponseType.json),
      );

      return response.data;
    } on DioException catch (error) {
      // 404 = « pas de marque » : ce n'est pas une panne, c'est une réponse.
      // Tout le reste (timeout, 5xx, DNS) doit être distingué, donc on relance
      // pour que `loadBrand` le classe en « indisponible ».
      if (error.response?.statusCode == 404) {
        return null;
      }

      rethrow;
    }
  };
}
