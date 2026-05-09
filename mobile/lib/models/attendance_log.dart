class AttendanceLog {
  final int id;
  final int employeeId;
  final DateTime date;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final String status;
  final double? workedHours;
  final double? overtimeHours;
  final int? lateMinutes;
  final String? employeeName;
  final String? employeePhotoUrl;

  final double? baseGain;
  final double? overtimeGain;
  final double? totalEstimated;
  final String? currency;

  AttendanceLog({
    required this.id,
    required this.employeeId,
    required this.date,
    this.checkIn,
    this.checkOut,
    required this.status,
    this.workedHours,
    this.overtimeHours,
    this.lateMinutes,
    this.employeeName,
    this.employeePhotoUrl,
    this.baseGain,
    this.overtimeGain,
    this.totalEstimated,
    this.currency,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    final hoursRaw =
        json['hours_worked'] ?? json['worked_hours'] ?? json['workedHours'];
    final overtimeRaw = json['overtime_hours'] ?? json['overtimeHours'];
    final lateRaw = json['late_minutes'];

    final employeeJson = json['employee'];
    final String? employeeName = employeeJson is Map
        ? employeeJson['name']?.toString()
        : json['name']?.toString();
    final String? employeePhotoUrl = employeeJson is Map
        ? (employeeJson['photo_url'] ?? employeeJson['photo_path'])?.toString()
        : (json['photo_url'] ?? json['photo_path'])?.toString();

    return AttendanceLog(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      employeeId: int.tryParse(
            (json['employee_id'] ?? json['employeeId'])?.toString() ?? '',
          ) ??
          0,
      date: DateTime.parse(
        (json['date'] ?? DateTime.now().toIso8601String()) as String,
      ),
      checkIn:
          json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
      checkOut:
          json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
      status: (json['status'] ?? 'incomplete') as String,
      workedHours:
          hoursRaw != null ? double.tryParse(hoursRaw.toString()) : null,
      overtimeHours:
          overtimeRaw != null ? double.tryParse(overtimeRaw.toString()) : null,
      lateMinutes: lateRaw != null ? int.tryParse(lateRaw.toString()) : null,
      employeeName: employeeName,
      employeePhotoUrl: employeePhotoUrl,
      baseGain: _parseDouble(json['base_gain']),
      overtimeGain: _parseDouble(json['overtime_gain']),
      totalEstimated: _parseDouble(json['total_estimated']),
      currency: json['currency']?.toString(),
    );
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}
