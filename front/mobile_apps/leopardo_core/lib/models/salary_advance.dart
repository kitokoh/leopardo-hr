class SalaryAdvance {
  const SalaryAdvance({
    required this.id,
    required this.employeeId,
    required this.status,
    this.amount,
    this.employeeName,
    this.employeeEmail,
    this.companyId,
    this.reason,
    this.approvedBy,
    this.decisionComment,
    this.repaymentMonths,
    this.monthlyDeduction,
    this.amountRemaining,
    this.repaymentPlan = const <Map<String, dynamic>>[],
    this.requestedAt,
    this.createdAt,
    this.updatedAt,
  });

  final int id;
  final int employeeId;
  final String status;
  final double? amount;
  final String? employeeName;
  final String? employeeEmail;
  final String? companyId;
  final String? reason;
  final int? approvedBy;
  final String? decisionComment;
  final int? repaymentMonths;
  final double? monthlyDeduction;
  final double? amountRemaining;
  final List<Map<String, dynamic>> repaymentPlan;
  final DateTime? requestedAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  factory SalaryAdvance.fromJson(Map<String, dynamic> json) {
    final rawPlan = json['repayment_plan'];
    final plan = <Map<String, dynamic>>[];
    if (rawPlan is List) {
      for (final item in rawPlan) {
        if (item is Map) {
          plan.add(item.cast<String, dynamic>());
        }
      }
    }

    final employee = json['employee'];
    final employeeMap =
        employee is Map ? employee.cast<String, dynamic>() : null;
    final firstName = employeeMap?['first_name']?.toString().trim() ?? '';
    final lastName = employeeMap?['last_name']?.toString().trim() ?? '';
    final composedName = '$firstName $lastName'.trim();

    return SalaryAdvance(
      id: (json['id'] as num?)?.toInt() ?? 0,
      employeeId: (json['employee_id'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? 'pending') as String,
      amount: _parseDouble(json['amount']),
      employeeName:
          (json['employee_name']?.toString().trim().isNotEmpty ?? false)
              ? json['employee_name'].toString().trim()
              : (composedName.isEmpty ? null : composedName),
      employeeEmail: employeeMap?['email']?.toString(),
      companyId:
          json['company_id']?.toString() ??
          employeeMap?['company_id']?.toString(),
      reason: json['reason'] as String?,
      approvedBy: (json['approved_by'] as num?)?.toInt(),
      decisionComment: json['decision_comment'] as String?,
      repaymentMonths: _parseInt(json['repayment_months']),
      monthlyDeduction: _parseDouble(json['monthly_deduction']),
      amountRemaining: _parseDouble(json['amount_remaining']),
      repaymentPlan: plan,
      requestedAt: DateTime.tryParse(json['requested_at']?.toString() ?? ''),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      updatedAt: DateTime.tryParse(json['updated_at']?.toString() ?? ''),
    );
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  static int? _parseInt(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }
}
