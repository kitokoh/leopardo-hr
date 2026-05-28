class Absence {
  final int id;
  final int employeeId;
  final int absenceTypeId;
  final String? absenceTypeName;
  final DateTime startDate;
  final DateTime endDate;
  final double daysCount;
  final String status;
  final String? employeeName;
  final String? reason;
  final String? rejectionReason;
  final DateTime? createdAt;

  Absence({
    required this.id,
    required this.employeeId,
    required this.absenceTypeId,
    this.absenceTypeName,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    required this.status,
    this.employeeName,
    this.reason,
    this.rejectionReason,
    this.createdAt,
  });

  factory Absence.fromJson(Map<String, dynamic> json) {
    final employee = json['employee'];
    final employeeMap =
        employee is Map ? employee.cast<String, dynamic>() : null;
    final firstName = employeeMap?['first_name']?.toString().trim() ?? '';
    final lastName = employeeMap?['last_name']?.toString().trim() ?? '';
    final composedName = '$firstName $lastName'.trim();

    return Absence(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      absenceTypeId: json['absence_type_id'] as int,
      absenceTypeName: json['absence_type']?['name'] as String?,
      startDate: DateTime.parse(json['start_date'] as String),
      endDate: DateTime.parse(json['end_date'] as String),
      daysCount: (json['days_count'] as num).toDouble(),
      status: json['status'] as String,
      employeeName:
          (json['employee_name']?.toString().trim().isNotEmpty ?? false)
              ? json['employee_name'].toString().trim()
              : (composedName.isEmpty ? null : composedName),
      reason: json['reason'] as String?,
      rejectionReason: json['rejected_reason'] as String?,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }
}
