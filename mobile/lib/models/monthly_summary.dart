class MonthlySummary {
  final int employeeId;
  final String name;
  final int? year;
  final int? month;
  final DateTime periodFrom;
  final DateTime periodTo;
  final int workingDays;
  final int daysPresent;
  final int daysAbsent;
  final double hours;
  final double overtimeHours;
  final double gross;
  final double deductions;
  final double net;
  final String currency;
  final List<MonthlyBreakdownEntry> breakdown;
  final String disclaimer;

  MonthlySummary({
    required this.employeeId,
    required this.name,
    this.year,
    this.month,
    required this.periodFrom,
    required this.periodTo,
    required this.workingDays,
    required this.daysPresent,
    required this.daysAbsent,
    required this.hours,
    required this.overtimeHours,
    required this.gross,
    required this.deductions,
    required this.net,
    required this.currency,
    required this.breakdown,
    required this.disclaimer,
  });

  factory MonthlySummary.fromJson(Map<String, dynamic> json) {
    final period = (json['period'] as Map).cast<String, dynamic>();
    final totals = (json['totals'] as Map).cast<String, dynamic>();
    final rawBreakdown = json['breakdown'];
    final breakdown = <MonthlyBreakdownEntry>[];
    if (rawBreakdown is List) {
      for (final entry in rawBreakdown) {
        if (entry is Map) {
          breakdown.add(MonthlyBreakdownEntry.fromJson(entry.cast<String, dynamic>()));
        }
      }
    }

    return MonthlySummary(
      employeeId: (json['employee_id'] as num).toInt(),
      name: (json['name'] ?? '') as String,
      year: json['year'] is num ? (json['year'] as num).toInt() : null,
      month: json['month'] is num ? (json['month'] as num).toInt() : null,
      periodFrom: DateTime.parse(period['from'] as String),
      periodTo: DateTime.parse(period['to'] as String),
      workingDays: (period['working_days'] as num).toInt(),
      daysPresent: (period['days_present'] as num).toInt(),
      daysAbsent: (period['days_absent'] as num).toInt(),
      hours: _d(totals['hours']),
      overtimeHours: _d(totals['overtime_hours']),
      gross: _d(totals['gross']),
      deductions: _d(totals['deductions']),
      net: _d(totals['net']),
      currency: (json['currency'] ?? 'DA') as String,
      breakdown: breakdown,
      disclaimer: (json['disclaimer'] ?? '') as String,
    );
  }

  static double _d(dynamic value) {
    if (value == null) return 0.0;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class MonthlyBreakdownEntry {
  final DateTime date;
  final double hours;
  final double overtimeHours;
  final double baseGain;
  final double overtimeGain;
  final double total;

  MonthlyBreakdownEntry({
    required this.date,
    required this.hours,
    required this.overtimeHours,
    required this.baseGain,
    required this.overtimeGain,
    required this.total,
  });

  factory MonthlyBreakdownEntry.fromJson(Map<String, dynamic> json) {
    return MonthlyBreakdownEntry(
      date: DateTime.parse(json['date'] as String),
      hours: MonthlySummary._d(json['hours']),
      overtimeHours: MonthlySummary._d(json['overtime_hours']),
      baseGain: MonthlySummary._d(json['base_gain']),
      overtimeGain: MonthlySummary._d(json['overtime_gain']),
      total: MonthlySummary._d(json['total']),
    );
  }
}
