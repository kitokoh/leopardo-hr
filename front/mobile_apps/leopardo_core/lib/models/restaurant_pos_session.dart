/// Session de caisse restaurant (RESTO-803/#6224).
///
/// Shape partielle vue du mobile : `{id, status, opened_at, closed_at,
/// expected_minor, counted_minor, variance_minor}` — la clôture délègue à
/// `ClosePosSessionAction` (écart serveur + événement
/// `restaurant.pos.closed.v1`).
class RestaurantPosSession {
  const RestaurantPosSession({
    required this.id,
    required this.status,
    this.openedAt,
    this.closedAt,
    this.expectedMinor,
    this.countedMinor,
    this.varianceMinor,
  });

  final int id;
  final String status;
  final DateTime? openedAt;
  final DateTime? closedAt;
  final int? expectedMinor;
  final int? countedMinor;
  final int? varianceMinor;

  bool get isOpen => status == 'open';

  factory RestaurantPosSession.fromJson(Map<String, dynamic> json) {
    return RestaurantPosSession(
      id: (json['id'] as num?)?.toInt() ?? 0,
      status: json['status'] as String? ?? '',
      openedAt: json['opened_at'] != null
          ? DateTime.tryParse(json['opened_at'] as String)
          : null,
      closedAt: json['closed_at'] != null
          ? DateTime.tryParse(json['closed_at'] as String)
          : null,
      expectedMinor: (json['expected_minor'] as num?)?.toInt(),
      countedMinor: (json['counted_minor'] as num?)?.toInt(),
      varianceMinor: (json['variance_minor'] as num?)?.toInt(),
    );
  }
}
