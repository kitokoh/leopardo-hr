/// Modèle d'une session de présence GPS de l'employé.
/// Endpoint : GET /api/v1/smart-attendance/my-sessions
class GeoAttendanceSession {
  /// Identifiant unique de la session
  final int id;

  /// Statut : detected | pending_validation | approved | rejected | cancelled
  final String status;

  /// Début de la session (entrée dans la zone)
  final DateTime startedAt;

  /// Fin de la session (sortie de la zone), null si en cours
  final DateTime? endedAt;

  /// Durée totale en secondes, null si session en cours
  final int? durationSeconds;

  /// Durée formatée lisible (ex: "7h 30min"), null si session en cours
  final String? durationFormatted;

  const GeoAttendanceSession({
    required this.id,
    required this.status,
    required this.startedAt,
    this.endedAt,
    this.durationSeconds,
    this.durationFormatted,
  });

  factory GeoAttendanceSession.fromJson(Map<String, dynamic> json) {
    return GeoAttendanceSession(
      id: (json['id'] as num).toInt(),
      status: (json['status'] as String?) ?? 'detected',
      startedAt: DateTime.parse(json['started_at'] as String),
      endedAt: json['ended_at'] != null
          ? DateTime.parse(json['ended_at'] as String)
          : null,
      durationSeconds: (json['duration_seconds'] as num?)?.toInt(),
      durationFormatted: json['duration_formatted'] as String?,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'status': status,
        'started_at': startedAt.toIso8601String(),
        'ended_at': endedAt?.toIso8601String(),
        'duration_seconds': durationSeconds,
        'duration_formatted': durationFormatted,
      };

  /// Indique si la session est toujours active (pas encore terminée)
  bool get isActive => endedAt == null;

  /// Indique si la session a été approuvée
  bool get isApproved => status == 'approved';

  /// Indique si la session est en attente de validation
  bool get isPending => status == 'detected' || status == 'pending_validation';

  /// Indique si la session a été rejetée ou annulée
  bool get isRejectedOrCancelled =>
      status == 'rejected' || status == 'cancelled';

  @override
  String toString() =>
      'GeoAttendanceSession(id: $id, status: $status, startedAt: $startedAt)';
}
