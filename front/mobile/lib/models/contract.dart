class Contract {
  final int id;
  final int employeeId;
  final String reference;
  final String type;
  final String startDate;
  final String? endDate;
  final double baseSalary;
  final String currency;
  final String status;

  Contract({
    required this.id,
    required this.employeeId,
    required this.reference,
    required this.type,
    required this.startDate,
    this.endDate,
    required this.baseSalary,
    required this.currency,
    required this.status,
  });

  factory Contract.fromJson(Map<String, dynamic> json) {
    return Contract(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      reference: json['reference'] as String? ?? '',
      type: json['type'] as String? ?? 'cdi',
      startDate: json['start_date'] as String,
      endDate: json['end_date'] as String?,
      baseSalary: (json['base_salary'] as num?)?.toDouble() ?? 0,
      currency: json['currency'] as String? ?? 'DZD',
      status: json['status'] as String? ?? 'active',
    );
  }
}
