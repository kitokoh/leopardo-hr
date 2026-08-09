import 'package:leopardo_core/models/attendance_log.dart';

/// Manager-facing "day detail" view for a single employee, backed by the
/// existing tenant-safe `GET /attendance/today?employee_id=` contract
/// (PA2-ATT-005: manager sees the full day detail without cross-tenant
/// leakage, reusing the same policy-guarded endpoint the employee app uses
/// for its own day detail sheet).
class EmployeeDayDetail {
  final int employeeId;
  final String employeeName;
  final String? matricule;
  final String status;
  final bool checkedIn;
  final bool isWorking;
  final int sessionsCount;
  final double hoursWorked;
  final double overtimeHours;
  final int lateMinutes;
  final int breakMinutes;
  final double baseGain;
  final double overtimeGain;
  final double totalEstimated;
  final String currency;
  final List<AttendanceLog> sessions;

  const EmployeeDayDetail({
    required this.employeeId,
    required this.employeeName,
    this.matricule,
    required this.status,
    required this.checkedIn,
    required this.isWorking,
    required this.sessionsCount,
    required this.hoursWorked,
    required this.overtimeHours,
    required this.lateMinutes,
    required this.breakMinutes,
    required this.baseGain,
    required this.overtimeGain,
    required this.totalEstimated,
    required this.currency,
    required this.sessions,
  });

  factory EmployeeDayDetail.fromJson(Map<String, dynamic> json) {
    final item = json['item'] is Map
        ? (json['item'] as Map).cast<String, dynamic>()
        : json;
    final summary = json['summary'] is Map
        ? (json['summary'] as Map).cast<String, dynamic>()
        : const <String, dynamic>{};
    final rawSessions = json['sessions'];
    final sessions = rawSessions is List
        ? rawSessions
            .whereType<Map>()
            .map(
              (entry) => AttendanceLog.fromJson(entry.cast<String, dynamic>()),
            )
            .toList()
        : const <AttendanceLog>[];

    return EmployeeDayDetail(
      employeeId: _asInt(item['employee_id']),
      employeeName: (item['name'] ?? '').toString().trim().isEmpty
          ? 'Employe'
          : item['name'].toString(),
      matricule: item['matricule']?.toString(),
      status: (item['status'] ?? 'absent').toString(),
      checkedIn: item['checked_in'] == true,
      isWorking: summary['is_working'] == true,
      sessionsCount: _asInt(
        summary['sessions_count'] ?? item['sessions_count'],
      ),
      hoursWorked: _asDouble(item['hours_worked']),
      overtimeHours: _asDouble(item['overtime_hours']),
      lateMinutes: _asInt(item['late_minutes']),
      breakMinutes: _asInt(summary['break_minutes']),
      baseGain: _asDouble(item['base_gain']),
      overtimeGain: _asDouble(item['overtime_gain']),
      totalEstimated: _asDouble(item['total_estimated']),
      currency: (item['currency'] ?? 'DA').toString(),
      sessions: sessions,
    );
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static double _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0.0;
  }
}
