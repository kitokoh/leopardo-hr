class AttendanceLog {
  final int id;
  final int employeeId;
  final DateTime date;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final String status;
  final double? workedHours;

  AttendanceLog({
    required this.id,
    required this.employeeId,
    required this.date,
    this.checkIn,
    this.checkOut,
    required this.status,
    this.workedHours,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) {
    return AttendanceLog(
      id: json['id'],
      employeeId: json['employee_id'],
      date: DateTime.parse(json['date']),
      checkIn: json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
      checkOut: json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
      status: json['status'],
      workedHours: json['worked_hours'] != null ? (json['worked_hours'] as num).toDouble() : null,
    );
  }
}
