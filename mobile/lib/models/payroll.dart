class Payroll {
  final int id;
  final int employeeId;
  final int month;
  final int year;
  final double grossSalary;
  final double netSalary;
  final String status;
  final String? pdfPath;
  final DateTime? validatedAt;

  Payroll({
    required this.id,
    required this.employeeId,
    required this.month,
    required this.year,
    required this.grossSalary,
    required this.netSalary,
    required this.status,
    this.pdfPath,
    this.validatedAt,
  });

  factory Payroll.fromJson(Map<String, dynamic> json) {
    return Payroll(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      month: json['period_month'] as int,
      year: json['period_year'] as int,
      grossSalary: (json['gross_salary'] as num).toDouble(),
      netSalary: (json['net_salary'] as num).toDouble(),
      status: json['status'] as String,
      pdfPath: json['pdf_path'] as String?,
      validatedAt:
          json['validated_at'] != null
              ? DateTime.parse(json['validated_at'] as String)
              : null,
    );
  }
}
