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
  final String? employeeMatricule;

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
    this.employeeMatricule,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    final hoursRaw =
        json['hours_worked'] ?? json['worked_hours'] ?? json['workedHours'];
    final overtimeRaw = json['overtime_hours'] ?? json['overtimeHours'];

    final employeeJson = json['employee'];
    String? employeeName = json['name'];
    String? employeePhotoUrl = json['photo_url'];
    String? employeeMatricule = json['matricule'];

    if (employeeJson is Map) {
      employeeName ??= employeeJson['name'];
      employeePhotoUrl ??= employeeJson['photo_url'];
      employeeMatricule ??= employeeJson['matricule'];
    }
    final lateRaw = json['late_minutes'];

    return AttendanceLog(
      id: (json['id'] ?? 0) as int,
      employeeId: (json['employee_id'] ?? json['employeeId']) as int,
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
      employeeMatricule: employeeMatricule,
    );
  }
}
