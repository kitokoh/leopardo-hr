import 'package:dio/dio.dart';

import '../api/api_exceptions.dart';

/// Détection HORS-LIGNE standardisée (T090, QA 2026-08-15).
///
/// Une erreur est « hors-ligne » si le transport a échoué (connexion,
/// timeout — types `DioException`) ou si l'API signale une indisponibilité
/// réseau (message `connexion`/`internet` dans l'`ApiException`). Les
/// erreurs métier (4xx/5xx avec réponse) ne sont PAS considérées hors-ligne
/// et ne doivent donc pas être mises en file (pointage offline #1289/#1290).
bool isOfflineNetworkError(Object e) {
  if (e is DioException) {
    return e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.sendTimeout ||
        e.type == DioExceptionType.receiveTimeout;
  }
  if (e is ApiException) {
    final message = e.message.toLowerCase();
    return message.contains('connexion') || message.contains('internet');
  }
  return false;
}
