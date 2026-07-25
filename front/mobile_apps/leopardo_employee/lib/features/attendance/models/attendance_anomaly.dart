/// PA2-ATT-004 - Self-service anomaly models for the employee day-detail view.
///
/// Mirrors the shape returned by `GET /me/attendance-anomalies`, which is a
/// company-independent, employee-scoped alias of the manager anomaly report
/// (`AttendanceAnomalyService::summarize`) filtered to the caller's own
/// `employee_id`.
class AttendanceAnomaly {
  const AttendanceAnomaly({
    required this.type,
    required this.severity,
    required this.title,
    required this.date,
    required this.requiresManagerAction,
    required this.recommendedAction,
  });

  final String type;
  final String severity;
  final String title;
  final String date;
  final bool requiresManagerAction;
  final String recommendedAction;

  factory AttendanceAnomaly.fromJson(Map<String, dynamic> json) {
    return AttendanceAnomaly(
      type: json['type']?.toString() ?? 'unknown',
      severity: json['severity']?.toString() ?? 'info',
      title: json['title']?.toString() ?? 'Anomalie',
      date: json['date']?.toString() ?? '',
      requiresManagerAction: json['requires_manager_action'] == true,
      recommendedAction: json['recommended_action']?.toString() ?? '',
    );
  }
}

class AttendanceAnomalyReport {
  const AttendanceAnomalyReport({
    required this.total,
    required this.critical,
    required this.warning,
    required this.info,
    required this.items,
  });

  final int total;
  final int critical;
  final int warning;
  final int info;
  final List<AttendanceAnomaly> items;

  static const empty = AttendanceAnomalyReport(
    total: 0,
    critical: 0,
    warning: 0,
    info: 0,
    items: [],
  );

  factory AttendanceAnomalyReport.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] is Map
        ? (json['summary'] as Map).cast<String, dynamic>()
        : const <String, dynamic>{};
    final rawItems = json['items'] as List? ?? const [];

    return AttendanceAnomalyReport(
      total: _asInt(summary['total']),
      critical: _asInt(summary['critical']),
      warning: _asInt(summary['warning']),
      info: _asInt(summary['info']),
      items: rawItems
          .whereType<Map>()
          .map((e) => AttendanceAnomaly.fromJson(e.cast<String, dynamic>()))
          .toList(),
    );
  }

  /// Anomalies detected on a specific calendar day (`yyyy-MM-dd`).
  List<AttendanceAnomaly> forDate(String dateKey) {
    return items.where((item) => item.date == dateKey).toList();
  }
}

int _asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}
