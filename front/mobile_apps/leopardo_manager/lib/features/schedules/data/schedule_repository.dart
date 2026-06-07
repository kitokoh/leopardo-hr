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

  Future<int> assignEmployees(int scheduleId, List<int> employeeIds) async {
    final response = await apiClient.requestWithRetry(
      '/schedules/$scheduleId/assign-employees',
      method: 'POST',
      data: {'employee_ids': employeeIds},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
    final data = extractDataMap(response.data);
    return WorkSchedule._asInt(data['assigned_count']);
  }
}

class WorkSchedule {
  const WorkSchedule({
    required this.id,
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.breakMinutes,
    required this.breakRules,
    required this.workDays,
    required this.restDays,
    required this.leaveRules,
    required this.assignmentNotes,
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
  final List<ScheduleBreakRule> breakRules;
  final List<int> workDays;
  final List<int> restDays;
  final List<ScheduleLeaveRule> leaveRules;
  final String? assignmentNotes;
  final int lateToleranceMinutes;
  final double overtimeThresholdDaily;
  final double overtimeThresholdWeekly;
  final bool isDefault;

  static const _dayLabels = <int, String>{
    1: 'Lun',
    2: 'Mar',
    3: 'Mer',
    4: 'Jeu',
    5: 'Ven',
    6: 'Sam',
    7: 'Dim',
  };

  String get restDaysLabel {
    if (restDays.isEmpty) return 'aucun';

    return restDays.map((day) => _dayLabels[day] ?? day.toString()).join(', ');
  }

  String get leaveRulesLabel {
    if (leaveRules.isEmpty) return 'Conges non definis';

    final primary = leaveRules.first;
    final days = primary.daysPerYear;
    if (days == null || days <= 0) return primary.label;

    return '${primary.label} ${days.toStringAsFixed(days.truncateToDouble() == days ? 0 : 1)} j/an';
  }

  factory WorkSchedule.fromJson(Map<String, dynamic> json) {
    return WorkSchedule(
      id: _asInt(json['id']),
      name: json['name']?.toString() ?? 'Horaire',
      startTime: _normalizeTime(json['start_time']),
      endTime: _normalizeTime(json['end_time']),
      breakMinutes: _asInt(json['break_minutes']),
      breakRules:
          (json['break_rules'] as List?)
              ?.whereType<Map>()
              .map(
                (entry) =>
                    ScheduleBreakRule.fromJson(entry.cast<String, dynamic>()),
              )
              .toList() ??
          const [],
      workDays:
          (json['work_days'] as List?)
              ?.map((day) => _asInt(day))
              .where((day) => day >= 1 && day <= 7)
              .toList() ??
          const [1, 2, 3, 4, 5],
      restDays:
          (json['rest_days'] as List?)
              ?.map((day) => _asInt(day))
              .where((day) => day >= 1 && day <= 7)
              .toList() ??
          const [],
      leaveRules:
          (json['leave_rules'] as List?)
              ?.whereType<Map>()
              .map(
                (entry) =>
                    ScheduleLeaveRule.fromJson(entry.cast<String, dynamic>()),
              )
              .toList() ??
          const [],
      assignmentNotes: json['assignment_notes']?.toString(),
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
      breakRules: breakRules,
      workDays: workDays,
      restDays: restDays,
      leaveRules: leaveRules,
      assignmentNotes: assignmentNotes,
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

class ScheduleBreakRule {
  const ScheduleBreakRule({
    required this.label,
    required this.startTime,
    required this.endTime,
    required this.minutes,
    required this.isPaid,
  });

  final String label;
  final String? startTime;
  final String? endTime;
  final int minutes;
  final bool isPaid;

  factory ScheduleBreakRule.fromJson(Map<String, dynamic> json) {
    return ScheduleBreakRule(
      label: json['label']?.toString() ?? 'Pause',
      startTime: json['start_time']?.toString(),
      endTime: json['end_time']?.toString(),
      minutes: WorkSchedule._asInt(json['minutes']),
      isPaid: json['is_paid'] == true || json['is_paid'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'label': label.trim().isEmpty ? 'Pause' : label.trim(),
      if (startTime != null && startTime!.isNotEmpty) 'start_time': startTime,
      if (endTime != null && endTime!.isNotEmpty) 'end_time': endTime,
      'minutes': minutes,
      'is_paid': isPaid,
    };
  }
}

class ScheduleLeaveRule {
  const ScheduleLeaveRule({
    required this.label,
    this.type,
    this.daysPerYear,
    this.policyId,
  });

  final String label;
  final String? type;
  final double? daysPerYear;
  final int? policyId;

  factory ScheduleLeaveRule.fromJson(Map<String, dynamic> json) {
    return ScheduleLeaveRule(
      label: json['label']?.toString() ?? 'Conge',
      type: json['type']?.toString(),
      daysPerYear: WorkSchedule._asDouble(json['days_per_year']),
      policyId:
          json['policy_id'] is num
              ? (json['policy_id'] as num).toInt()
              : int.tryParse(json['policy_id']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'label': label.trim().isEmpty ? 'Conge' : label.trim(),
      if (type != null && type!.trim().isNotEmpty) 'type': type!.trim(),
      if (daysPerYear != null) 'days_per_year': daysPerYear,
      if (policyId != null) 'policy_id': policyId,
    };
  }
}

class SchedulePayload {
  const SchedulePayload({
    required this.name,
    required this.startTime,
    required this.endTime,
    required this.breakMinutes,
    required this.breakRules,
    required this.workDays,
    required this.restDays,
    required this.leaveRules,
    required this.assignmentNotes,
    required this.lateToleranceMinutes,
    required this.overtimeThresholdDaily,
    required this.overtimeThresholdWeekly,
    required this.isDefault,
  });

  final String name;
  final String startTime;
  final String endTime;
  final int breakMinutes;
  final List<ScheduleBreakRule> breakRules;
  final List<int> workDays;
  final List<int> restDays;
  final List<ScheduleLeaveRule> leaveRules;
  final String? assignmentNotes;
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
      'break_rules': breakRules.map((rule) => rule.toJson()).toList(),
      'work_days': workDays,
      'rest_days': restDays,
      'leave_rules': leaveRules.map((rule) => rule.toJson()).toList(),
      if (assignmentNotes != null && assignmentNotes!.trim().isNotEmpty)
        'assignment_notes': assignmentNotes!.trim(),
      'late_tolerance_minutes': lateToleranceMinutes,
      'overtime_threshold_daily': overtimeThresholdDaily,
      'overtime_threshold_weekly': overtimeThresholdWeekly,
      'is_default': isDefault,
    };
  }
}
