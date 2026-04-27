class SalaryAdvance {
  final int id;
  final int employeeId;
  final double amount;
  final String? reason;
  final String status;
  final double? monthlyDeduction;
  final double? amountRemaining;
  final int? repaymentMonths;

  SalaryAdvance({
    required this.id,
    required this.employeeId,
    required this.amount,
    this.reason,
    required this.status,
    this.monthlyDeduction,
    this.amountRemaining,
    this.repaymentMonths,
  });

  factory SalaryAdvance.fromJson(Map<String, dynamic> json) {
    return SalaryAdvance(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      amount: (json['amount'] as num).toDouble(),
      reason: json['reason'] as String?,
      status: json['status'] as String,
      monthlyDeduction: json['monthly_deduction'] != null
          ? (json['monthly_deduction'] as num).toDouble()
          : null,
      amountRemaining: json['amount_remaining'] != null
          ? (json['amount_remaining'] as num).toDouble()
          : null,
      repaymentMonths: json['repayment_months'] as int?,
    );
  }
}
