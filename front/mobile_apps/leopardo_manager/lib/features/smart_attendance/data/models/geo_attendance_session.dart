/// Modèle d'une session de pointage GPS (Smart Attendance) — app Manager.
class GeoAttendanceSession {
  const GeoAttendanceSession({
    required this.id,
    required this.employeeId,
    required this.employeeName,
    required this.companyId,
    required this.startedAt,
    required this.status,
    this.endedAt,
    this.durationSeconds,
    this.validatedBy,
    this.validatedAt,
    this.validationNote,
    this.checkInLat,
    this.checkInLng,
    this.checkOutLat,
    this.checkOutLng,
  });

  final int id;
  final int employeeId;
  final String employeeName;
  final String companyId;
  final DateTime startedAt;
  final String status;
  final DateTime? endedAt;
  final int? durationSeconds;
  final int? validatedBy;
  final DateTime? validatedAt;
  final String? validationNote;
  final double? checkInLat;
  final double? checkInLng;
  final double? checkOutLat;
  final double? checkOutLng;

  factory GeoAttendanceSession.fromJson(Map<String, dynamic> json) {
    // L'API retourne employee: {id, name, photo} — on lit employee.name
    final employeeMap = json['employee'] as Map<String, dynamic>?;
    return GeoAttendanceSession(
      id: json['id'] as int,
      employeeId: (employeeMap?['id'] as int?) ?? (json['employee_id'] as int? ?? 0),
      employeeName: (employeeMap?['name'] as String?) ?? (json['employee_name'] as String?) ?? '',
      companyId: (json['company_id'] as String?) ?? '',
      startedAt: DateTime.parse(json['started_at'] as String),
      status: (json['status'] as String?) ?? 'detected',
      endedAt: json['ended_at'] != null
          ? DateTime.parse(json['ended_at'] as String)
          : null,
      durationSeconds: json['duration_seconds'] as int?,
      validatedBy: json['validated_by'] as int?,
      validatedAt: json['validated_at'] != null
          ? DateTime.parse(json['validated_at'] as String)
          : null,
      validationNote: json['validation_note'] as String?,
      checkInLat: (json['check_in_lat'] as num?)?.toDouble(),
      checkInLng: (json['check_in_lng'] as num?)?.toDouble(),
      checkOutLat: (json['check_out_lat'] as num?)?.toDouble(),
      checkOutLng: (json['check_out_lng'] as num?)?.toDouble(),
    );
  }

  String get durationLabel {
    if (durationSeconds == null) return '—';
    final h = durationSeconds! ~/ 3600;
    final m = (durationSeconds! % 3600) ~/ 60;
    return h > 0 ? '${h}h ${m}min' : '${m}min';
  }

  bool get isPendingValidation => status == 'pending_validation';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';
}
