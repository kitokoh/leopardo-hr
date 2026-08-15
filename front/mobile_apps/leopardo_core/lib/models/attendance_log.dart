class AttendanceLog {
  final int id;
  final int employeeId;
  final DateTime date;
  final int sessionNumber;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final String status;
  final String workType;
  final String? punchNote;
  final Map<String, dynamic>? punchMeta;
  final double? workedHours;
  final double? overtimeHours;
  final int? lateMinutes;
  final String? timezone;
  final String? deviceTimezone;
  final Map<String, dynamic>? geofence;
  final String? employeeName;
  final String? employeePhotoUrl;

  AttendanceLog({
    required this.id,
    required this.employeeId,
    required this.date,
    this.sessionNumber = 1,
    this.checkIn,
    this.checkOut,
    required this.status,
    this.workType = 'normal',
    this.punchNote,
    this.punchMeta,
    this.workedHours,
    this.overtimeHours,
    this.lateMinutes,
    this.timezone,
    this.deviceTimezone,
    this.geofence,
    this.employeeName,
    this.employeePhotoUrl,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    final hoursRaw =
        json['hours_worked'] ?? json['worked_hours'] ?? json['workedHours'];
    final overtimeRaw = json['overtime_hours'] ?? json['overtimeHours'];
    final lateRaw = json['late_minutes'];

    final employeeJson = json['employee'];
    final String? employeeName =
        employeeJson is Map
            ? employeeJson['name']?.toString()
            : json['name']?.toString();
    final String? employeePhotoUrl =
        employeeJson is Map
            ? (employeeJson['photo_url'] ?? employeeJson['photo_path'])
                ?.toString()
            : (json['photo_url'] ?? json['photo_path'])?.toString();

    return AttendanceLog(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      employeeId:
          int.tryParse(
            (json['employee_id'] ?? json['employeeId'])?.toString() ?? '',
          ) ??
          0,
      // #3433 : date nullable/malformée → tryParse avec repli sur maintenant.
      date: DateTime.tryParse((json['date'] ?? '').toString()) ?? DateTime.now(),
      sessionNumber:
          int.tryParse(
            (json['session_number'] ?? json['sessionNumber'])?.toString() ?? '',
          ) ??
          1,
      checkIn:
          json['check_in'] != null ? DateTime.tryParse(json['check_in'] as String? ?? '') : null,
      checkOut:
          json['check_out'] != null ? DateTime.tryParse(json['check_out'] as String? ?? '') : null,
      status: (json['status'] ?? 'incomplete') as String,
      workType: (json['work_type'] ?? json['workType'] ?? 'normal').toString(),
      punchNote: json['punch_note']?.toString(),
      punchMeta:
          json['punch_meta'] is Map
              ? (json['punch_meta'] as Map).cast<String, dynamic>()
              : null,
      workedHours:
          hoursRaw != null ? double.tryParse(hoursRaw.toString()) : null,
      overtimeHours:
          overtimeRaw != null ? double.tryParse(overtimeRaw.toString()) : null,
      lateMinutes: lateRaw != null ? int.tryParse(lateRaw.toString()) : null,
      timezone: json['timezone']?.toString(),
      deviceTimezone: json['device_timezone']?.toString(),
      geofence:
          json['geofence'] is Map
              ? (json['geofence'] as Map).cast<String, dynamic>()
              : null,
      employeeName: employeeName,
      employeePhotoUrl: employeePhotoUrl,
    );
  }
}
