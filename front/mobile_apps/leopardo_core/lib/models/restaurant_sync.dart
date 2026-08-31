/// Synchronisation offline restaurant (RESTO-804/#6225).
///
/// L'app pousse ses opérations effectuées hors ligne ; le serveur les rejoue
/// IDEMPOTEMENT via `POST /api/v1/restaurant/mobile/sync` (clés client).
/// Un rejeu ne crée jamais de doublon (critère d'acceptation #6406).
library;

/// Opération offline en attente de rejeu.
class RestaurantSyncOperation {
  const RestaurantSyncOperation({
    required this.type,
    required this.idempotencyKey,
    this.payload = const <String, dynamic>{},
  });

  final String type;
  final String idempotencyKey;
  final Map<String, dynamic> payload;

  Map<String, dynamic> toJson() => {
    'type': type,
    'idempotency_key': idempotencyKey,
    if (payload.isNotEmpty) 'payload': payload,
  };
}

/// Résultat d'une opération rejouée par le serveur.
class RestaurantSyncResult {
  const RestaurantSyncResult({
    required this.type,
    required this.idempotencyKey,
    required this.status,
    this.message,
  });

  final String type;
  final String idempotencyKey;

  /// `created` | `duplicate` | `error`
  final String status;
  final String? message;

  bool get isDuplicate => status == 'duplicate';
  bool get isError => status == 'error';

  factory RestaurantSyncResult.fromJson(Map<String, dynamic> json) {
    return RestaurantSyncResult(
      type: json['type'] as String? ?? '',
      idempotencyKey: json['idempotency_key'] as String? ?? '',
      status: json['status'] as String? ?? 'error',
      message: json['message'] as String?,
    );
  }
}
