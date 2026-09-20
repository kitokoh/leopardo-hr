/// Alerte caméra dédupliquée (contrat `CameraAlert`, GET /cameras/alerts —
/// #7427). Cycle : open -> acknowledged -> resolved (actions idempotentes).
class CameraAlert {
  const CameraAlert({
    required this.id,
    required this.cameraId,
    this.cameraName,
    this.cameraEventId,
    required this.type,
    required this.severity,
    required this.status,
    this.acknowledgedAt,
    this.resolvedAt,
    this.createdAt,
  });

  final int id;
  final int cameraId;
  final String? cameraName;

  /// Événement à ouvrir depuis l'alerte (deep link notification, #7427).
  final int? cameraEventId;

  /// motion | person | vehicle | line_crossing | tamper.
  final String type;

  /// info | warning | high | critical.
  final String severity;

  /// open | acknowledged | resolved.
  final String status;
  final String? acknowledgedAt;
  final String? resolvedAt;
  final String? createdAt;

  bool get isOpen => status == 'open';
  bool get isResolved => status == 'resolved';

  factory CameraAlert.fromJson(Map<String, dynamic> json) {
    return CameraAlert(
      id: json['id'] as int,
      cameraId: (json['camera_id'] as int?) ?? 0,
      cameraName: json['camera_name'] as String?,
      cameraEventId: json['camera_event_id'] as int?,
      type: (json['type'] as String?) ?? 'motion',
      severity: (json['severity'] as String?) ?? 'info',
      status: (json['status'] as String?) ?? 'open',
      acknowledgedAt: json['acknowledged_at'] as String?,
      resolvedAt: json['resolved_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
