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
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      employeeId: int.tryParse(json['employee_id']?.toString() ?? '') ?? 0,
      absenceTypeId: int.tryParse(json['absence_type_id']?.toString() ?? '') ?? 0,
      absenceTypeName: json['absence_type']?['name']?.toString() ?? json['absenceType']?['name']?.toString(),
      startDate: DateTime.parse(json['start_date']?.toString() ?? DateTime.now().toIso8601String()),
      endDate: DateTime.parse(json['end_date']?.toString() ?? DateTime.now().toIso8601String()),
      daysCount: double.tryParse(json['days_count']?.toString() ?? '0') ?? 0.0,
      status: json['status']?.toString() ?? 'pending',
      reason: json['reason'] as String?,
      rejectionReason: json['rejected_reason'] as String?,
    );
  }
}
