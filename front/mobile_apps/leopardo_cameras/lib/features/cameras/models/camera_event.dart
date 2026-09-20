/// Événement détecté par la chaîne vidéo (contrat `CameraEvent`,
/// GET /cameras/events — #7427).
///
/// RGPD : aucun chemin de snapshot n'est exposé par l'API, seul le booléen
/// `has_snapshot` l'est.
class CameraEvent {
  const CameraEvent({
    required this.id,
    required this.cameraId,
    this.cameraName,
    required this.type,
    required this.severity,
    this.detectedAt,
    this.hasSnapshot = false,
    this.createdAt,
  });

  final int id;
  final int cameraId;
  final String? cameraName;

  /// motion | person | vehicle | line_crossing | tamper.
  final String type;

  /// info | warning | high | critical.
  final String severity;
  final String? detectedAt;
  final bool hasSnapshot;
  final String? createdAt;

  factory CameraEvent.fromJson(Map<String, dynamic> json) {
    return CameraEvent(
      id: json['id'] as int,
      cameraId: (json['camera_id'] as int?) ?? 0,
      cameraName: json['camera_name'] as String?,
      type: (json['type'] as String?) ?? 'motion',
      severity: (json['severity'] as String?) ?? 'info',
      detectedAt: json['detected_at'] as String?,
      hasSnapshot: json['has_snapshot'] == true,
      createdAt: json['created_at'] as String?,
    );
  }
}
