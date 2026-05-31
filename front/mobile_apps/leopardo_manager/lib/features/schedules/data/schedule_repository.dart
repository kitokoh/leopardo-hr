import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class ScheduleRepository {
  ScheduleRepository(this.apiClient);

  final ApiClient apiClient;

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<List<WorkSchedule>> list() async {
    final response = await apiClient.requestWithRetry(
      '/schedules',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);

    return items
        .map(
          (item) =>
              WorkSchedule.fromJson((item as Map).cast<String, dynamic>()),
        )
        .toList();
  }

  Future<WorkSchedule> create(SchedulePayload payload) async {
    final response = await apiClient.requestWithRetry(
      '/schedules',
      method: 'POST',
      data: payload.toJson(),
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    return WorkSchedule.fromJson(extractDataMap(response.data));
  }

  Future<WorkSchedule> update(int scheduleId, SchedulePayload payload) async {
    final response = await apiClient.requestWithRetry(
      '/schedules/$scheduleId',
      method: 'PUT',
      data: payload.toJson(),
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );

    return WorkSchedule.fromJson(extractDataMap(response.data));
  }

  Future<void> delete(int scheduleId) async {
    await apiClient.requestWithRetry(
      '/schedules/$scheduleId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}

class WorkSchedule {
  const WorkSchedule({
    required this.id,
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.breakMinutes,
    required this.workDays,
    required this.lateToleranceMinutes,
    required this.overtimeThresholdDaily,
    required this.overtimeThresholdWeekly,
    required this.isDefault,
  });

  final int id;
  final String name;
  final String startTime;
  final String endTime;
  final int breakMinutes;
  final List<int> workDays;
  final int lateToleranceMinutes;
  final double overtimeThresholdDaily;
  final double overtimeThresholdWeekly;
  final bool isDefault;

  factory WorkSchedule.fromJson(Map<String, dynamic> json) {
    return WorkSchedule(
      id: _asInt(json['id']),
      name: json['name']?.toString() ?? 'Horaire',
      startTime: _normalizeTime(json['start_time']),
      endTime: _normalizeTime(json['end_time']),
      breakMinutes: _asInt(json['break_minutes']),
      workDays:
          (json['work_days'] as List?)
              ?.map((day) => _asInt(day))
              .where((day) => day >= 1 && day <= 7)
              .toList() ??
          const [1, 2, 3, 4, 5],
      lateToleranceMinutes: _asInt(json['late_tolerance_minutes']),
      overtimeThresholdDaily: _asDouble(json['overtime_threshold_daily']),
      overtimeThresholdWeekly: _asDouble(json['overtime_threshold_weekly']),
      isDefault: json['is_default'] == true || json['is_default'] == 1,
    );
  }

  SchedulePayload toPayload() {
    return SchedulePayload(
      name: name,
      startTime: startTime,
      endTime: endTime,
      breakMinutes: breakMinutes,
      workDays: workDays,
      lateToleranceMinutes: lateToleranceMinutes,
      overtimeThresholdDaily: overtimeThresholdDaily,
      overtimeThresholdWeekly: overtimeThresholdWeekly,
      isDefault: isDefault,
    );
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static double _asDouble(dynamic value) {
    if (value is double) return value;
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _normalizeTime(dynamic value) {
    final raw = value?.toString() ?? '08:00';
    if (raw.length >= 5) return raw.substring(0, 5);
    return raw;
  }
}

class SchedulePayload {
  const SchedulePayload({
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.breakMinutes,
    required this.workDays,
    required this.lateToleranceMinutes,
    required this.overtimeThresholdDaily,
    required this.overtimeThresholdWeekly,
    required this.isDefault,
  });

  final String name;
  final String startTime;
  final String endTime;
  final int breakMinutes;
  final List<int> workDays;
  final int lateToleranceMinutes;
  final double overtimeThresholdDaily;
  final double overtimeThresholdWeekly;
  final bool isDefault;

  Map<String, dynamic> toJson() {
    return {
      'name': name.trim(),
      'start_time': startTime,
      'end_time': endTime,
      'break_minutes': breakMinutes,
      'work_days': workDays,
      'late_tolerance_minutes': lateToleranceMinutes,
      'overtime_threshold_daily': overtimeThresholdDaily,
      'overtime_threshold_weekly': overtimeThresholdWeekly,
      'is_default': isDefault,
    };
  }
}
