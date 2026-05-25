class Absence {
  final int id;
  final int employeeId;
  final int absenceTypeId;
  final String? absenceTypeName;
  final DateTime startDate;
  final DateTime endDate;
  final double daysCount;
  final String status;
  final String? reason;
  final String? rejectionReason;

  Absence({
    required this.id,
    required this.employeeId,
    required this.absenceTypeId,
    this.absenceTypeName,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    required this.status,
    this.reason,
    this.rejectionReason,
  });

  factory Absence.fromJson(Map<String, dynamic> json) {
    return Absence(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      absenceTypeId: json['absence_type_id'] as int,
      absenceTypeName: json['absence_type']?['name'] as String?,
      startDate: DateTime.parse(json['start_date'] as String),
      endDate: DateTime.parse(json['end_date'] as String),
      daysCount: (json['days_count'] as num).toDouble(),
      status: json['status'] as String,
      reason: json['reason'] as String?,
      rejectionReason: json['rejected_reason'] as String?,
    );
  }
}
