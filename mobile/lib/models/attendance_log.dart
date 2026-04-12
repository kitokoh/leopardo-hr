class AttendanceLog {
  final int id;
  final int employeeId;
  final DateTime date;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final String status;
  final double? workedHours;
  final double? overtimeHours;

  AttendanceLog({
    required this.id,
    required this.employeeId,
    required this.date,
    this.checkIn,
    this.checkOut,
    required this.status,
    this.workedHours,
    this.overtimeHours,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    final hoursRaw = json['hours_worked'] ?? json['worked_hours'] ?? json['workedHours'];
    final overtimeRaw = json['overtime_hours'] ?? json['overtimeHours'];

    return AttendanceLog(
      id: (json['id'] ?? 0) as int,
      employeeId: (json['employee_id'] ?? json['employeeId']) as int,
      date: DateTime.parse((json['date'] ?? DateTime.now().toIso8601String()) as String),
      checkIn: json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
      checkOut: json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
      status: (json['status'] ?? 'incomplete') as String,
      workedHours: hoursRaw != null ? double.tryParse(hoursRaw.toString()) : null,
      overtimeHours: overtimeRaw != null ? double.tryParse(overtimeRaw.toString()) : null,
    );
  }
}
