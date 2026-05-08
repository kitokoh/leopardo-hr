class DailySummary {
  final double hoursWorked;
  final double overtimeHours;
  final double baseGain;
  final double overtimeGain;
  final double totalEstimated;
  final String currency;

  DailySummary({
    required this.hoursWorked,
    required this.overtimeHours,
    required this.baseGain,
    required this.overtimeGain,
    required this.totalEstimated,
    required this.currency,
  });

  factory DailySummary.fromJson(Map<String, dynamic> json) {
    return DailySummary(
      hoursWorked: _parseDouble(json['hours_worked']),
      overtimeHours: _parseDouble(json['overtime_hours']),
      baseGain: _parseDouble(json['base_gain']),
      overtimeGain: _parseDouble(json['overtime_gain']),
      totalEstimated: _parseDouble(json['total_estimated']),
      currency: json['currency']?.toString() ?? 'DA',
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}
