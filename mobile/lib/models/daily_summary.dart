class DailySummary {
  final double baseGain;
  final double overtimeGain;
  final double totalEstimated;
  final String currency;

  DailySummary({
    required this.baseGain,
    required this.overtimeGain,
    required this.totalEstimated,
    required this.currency,
  });

  factory DailySummary.fromJson(Map<String, dynamic> json) {
    return DailySummary(
      baseGain: json['base_gain'] != null ? (json['base_gain'] as num).toDouble() : 0.0,
      overtimeGain: json['overtime_gain'] != null ? (json['overtime_gain'] as num).toDouble() : 0.0,
      totalEstimated: json['total_estimated'] != null ? (json['total_estimated'] as num).toDouble() : 0.0,
      currency: json['currency'] ?? 'DA',
    );
  }
}
