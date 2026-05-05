class AttendanceLog {
  final int id;
  final int employeeId;
  final String? employeeName;
  final String? employeePhotoUrl;
  final DateTime date;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final String status;
  final double? workedHours;
  final double? overtimeHours;
  final int? lateMinutes;

  AttendanceLog({
    required this.id,
    required this.employeeId,
    this.employeeName,
    this.employeePhotoUrl,
    required this.date,
    this.checkIn,
    this.checkOut,
    required this.status,
    this.workedHours,
    this.overtimeHours,
    this.lateMinutes,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    final hoursRaw =
        json['hours_worked'] ?? json['worked_hours'] ?? json['workedHours'];
    final overtimeRaw = json['overtime_hours'] ?? json['overtimeHours'];
    final lateRaw = json['late_minutes'];

    final employeeJson = json['employee'];
    String? employeeName;
    String? employeePhotoUrl;

    if (employeeJson is Map) {
      employeeName = employeeJson['name']?.toString();
      employeePhotoUrl = employeeJson['photo_url']?.toString();
    } else {
      employeeName = json['employee_name']?.toString() ?? json['name']?.toString();
      employeePhotoUrl = json['employee_photo_url']?.toString() ?? json['photo_url']?.toString();
    }

    return AttendanceLog(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      employeeId: int.tryParse(
            (json['employee_id'] ?? json['employeeId'])?.toString() ?? '',
          ) ??
          0,
      employeeName: employeeName,
      employeePhotoUrl: employeePhotoUrl,
      date: DateTime.parse(
        (json['date']?.toString() ?? DateTime.now().toIso8601String()),
      ),
      checkIn: json['check_in'] != null
          ? DateTime.tryParse(json['check_in'].toString())
          : null,
      checkOut: json['check_out'] != null
          ? DateTime.tryParse(json['check_out'].toString())
          : null,
      status: (json['status']?.toString() ?? 'incomplete'),
      workedHours:
          hoursRaw != null ? double.tryParse(hoursRaw.toString()) : null,
      overtimeHours:
          overtimeRaw != null ? double.tryParse(overtimeRaw.toString()) : null,
      lateMinutes: lateRaw != null ? int.tryParse(lateRaw.toString()) : null,
    );
  }
}
