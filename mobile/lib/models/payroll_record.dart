import 'package:leopardo_rh/models/employee.dart';

class PayrollRecord {
  const PayrollRecord({
    required this.id,
    required this.employeeId,
    required this.periodMonth,
    required this.periodYear,
    required this.status,
    this.employee,
    this.grossSalary,
    this.overtimeAmount,
    this.bonuses,
    this.deductions,
    this.cotisations,
    this.irAmount,
    this.advanceDeduction,
    this.absenceDeduction,
    this.penaltyDeduction,
    this.netSalary,
    this.pdfPath,
    this.validatedBy,
    this.validatedAt,
    this.createdAt,
    this.updatedAt,
  });

  final int id;
  final int employeeId;
  final int periodMonth;
  final int periodYear;
  final String status;
  final Employee? employee;
  final double? grossSalary;
  final double? overtimeAmount;
  final double? bonuses;
  final double? deductions;
  final double? cotisations;
  final double? irAmount;
  final double? advanceDeduction;
  final double? absenceDeduction;
  final double? penaltyDeduction;
  final double? netSalary;
  final String? pdfPath;
  final int? validatedBy;
  final DateTime? validatedAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  factory PayrollRecord.fromJson(Map<String, dynamic> json) {
    final rawEmployee = json['employee'];
    return PayrollRecord(
      id: (json['id'] as num?)?.toInt() ?? 0,
      employeeId: (json['employee_id'] as num?)?.toInt() ?? 0,
      periodMonth: (json['period_month'] as num?)?.toInt() ?? 0,
      periodYear: (json['period_year'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? 'draft') as String,
      employee: rawEmployee is Map
          ? Employee.fromJson({
              'id': rawEmployee['id'] ?? 0,
              'first_name': rawEmployee['first_name'] ?? '',
              'last_name': rawEmployee['last_name'] ?? '',
              'email': rawEmployee['email'] ?? '',
              'status': rawEmployee['status'] ?? 'active',
            })
          : null,
      grossSalary: _parseDouble(json['gross_salary']),
      overtimeAmount: _parseDouble(json['overtime_amount']),
      bonuses: _parseDouble(json['bonuses']),
      deductions: _parseDouble(json['deductions']),
      cotisations: _parseDouble(json['cotisations']),
      irAmount: _parseDouble(json['ir_amount']),
      advanceDeduction: _parseDouble(json['advance_deduction']),
      absenceDeduction: _parseDouble(json['absence_deduction']),
      penaltyDeduction: _parseDouble(json['penalty_deduction']),
      netSalary: _parseDouble(json['net_salary']),
      pdfPath: json['pdf_path'] as String?,
      validatedBy: (json['validated_by'] as num?)?.toInt(),
      validatedAt: DateTime.tryParse(json['validated_at']?.toString() ?? ''),
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
