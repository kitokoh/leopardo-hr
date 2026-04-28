class SalaryAdvance {
  const SalaryAdvance({
    required this.id,
    required this.employeeId,
    required this.status,
    this.amount,
    this.reason,
    this.approvedBy,
    this.decisionComment,
    this.repaymentMonths,
    this.monthlyDeduction,
    this.amountRemaining,
    this.repaymentPlan = const <Map<String, dynamic>>[],
    this.createdAt,
    this.updatedAt,
  });

  final int id;
  final int employeeId;
  final String status;
  final double? amount;
  final String? reason;
  final int? approvedBy;
  final String? decisionComment;
  final int? repaymentMonths;
  final double? monthlyDeduction;
  final double? amountRemaining;
  final List<Map<String, dynamic>> repaymentPlan;
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

    return SalaryAdvance(
      id: (json['id'] as num?)?.toInt() ?? 0,
      employeeId: (json['employee_id'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? 'pending') as String,
      amount: _parseDouble(json['amount']),
      reason: json['reason'] as String?,
      approvedBy: (json['approved_by'] as num?)?.toInt(),
      decisionComment: json['decision_comment'] as String?,
      repaymentMonths: (json['repayment_months'] as num?)?.toInt(),
      monthlyDeduction: _parseDouble(json['monthly_deduction']),
      amountRemaining: _parseDouble(json['amount_remaining']),
      repaymentPlan: plan,
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
}
