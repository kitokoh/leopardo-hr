class PayrollBalance {
  final int employeeId;
  final String employeeName;
  final String currency;
  final String periodStart;
  final String periodEnd;
  final String periodLabel;
  final String cycle;
  final double grossDue;
  final double advances;
  final double paid;
  final double remaining;
  final int? paySlipId;
  final String? paySlipStatus;

  const PayrollBalance({
    required this.employeeId,
    required this.employeeName,
    required this.currency,
    required this.periodStart,
    required this.periodEnd,
    required this.periodLabel,
    required this.cycle,
    required this.grossDue,
    required this.advances,
    required this.paid,
    required this.remaining,
    this.paySlipId,
    this.paySlipStatus,
  });

  factory PayrollBalance.fromJson(Map<String, dynamic> json) {
    final period = _asMap(json['period']);
    final paySlip = _asMap(json['pay_slip']);

    return PayrollBalance(
      employeeId: _asInt(json['employee_id']),
      employeeName: (json['employee_name'] ?? '').toString(),
      currency: (json['currency'] ?? 'DZD').toString(),
      periodStart: (period['start'] ?? '').toString(),
      periodEnd: (period['end'] ?? '').toString(),
      periodLabel: (period['label'] ?? '').toString(),
      cycle: (period['cycle'] ?? 'monthly').toString(),
      grossDue: _asDouble(json['gross_due']),
      advances: _asDouble(json['advances']),
      paid: _asDouble(json['paid']),
      remaining: _asDouble(json['remaining']),
      paySlipId: _nullableInt(paySlip['id']),
      paySlipStatus: paySlip['status']?.toString(),
    );
  }

  static Map<String, dynamic> _asMap(dynamic value) =>
      value is Map ? value.cast<String, dynamic>() : const <String, dynamic>{};

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int? _nullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value.toString());
  }

  static double _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class PayrollMobileSummary {
  final List<PayrollBalance> items;
  final double grossDue;
  final double advances;
  final double paid;
  final double remaining;

  const PayrollMobileSummary({
    required this.items,
    required this.grossDue,
    required this.advances,
    required this.paid,
    required this.remaining,
  });

  factory PayrollMobileSummary.fromJson(Map<String, dynamic> json) {
    final items =
        (json['items'] is List ? json['items'] as List : const <dynamic>[])
            .whereType<Map>()
            .map(
              (item) => PayrollBalance.fromJson(item.cast<String, dynamic>()),
            )
            .toList();
    final totals = PayrollBalance._asMap(json['totals']);

    return PayrollMobileSummary(
      items: items,
      grossDue: PayrollBalance._asDouble(totals['gross_due']),
      advances: PayrollBalance._asDouble(totals['advances']),
      paid: PayrollBalance._asDouble(totals['paid']),
      remaining: PayrollBalance._asDouble(totals['remaining']),
    );
  }
}
