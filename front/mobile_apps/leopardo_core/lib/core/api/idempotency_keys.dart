import 'package:uuid/uuid.dart';

/// RTMX (#5407) — clés d'idempotence pour les écritures de pointage.
///
/// Le serveur (`IdempotencyMiddleware`, #5277) rejoue la première réponse
/// 2xx pendant 24 h pour toute retentative avec la même clé, le même token et
/// le même corps. Chaque pointage logique (check-in, check-out, geo-event)
/// doit donc générer UNE clé au début de l'appel, l'envoyer en header
/// `Idempotency-Key` et la conserver dans l'entrée de la file hors-ligne
/// (`offline_punches`, champ `idempotencyKey`) pour que le rejeu
/// (`OfflineSyncService.syncPendingPunches`) réutilise la MÊME clé — le
/// serveur rejoue alors la réponse initiale au lieu de créer un doublon.
abstract final class IdempotencyKeys {
  static final Uuid _uuid = Uuid();

  /// Nouvelle clé unique (UUID v4, 36 caractères).
  ///
  /// Conforme au motif serveur `[A-Za-z0-9._:-]{8,255}`
  /// (`IdempotencyMiddleware::KEY_PATTERN`, #5277).
  static String newKey() => _uuid.v4();
}
