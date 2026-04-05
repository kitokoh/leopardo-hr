import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/models/attendance_log.dart';
import 'package:leopardo_rh/models/daily_summary.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';

class AttendanceState {
  final bool isLoading;
  final AttendanceLog? todayLog;
  final Map<String, dynamic>? context;
  final DailySummary? summary;
  final String? error;

  AttendanceState({
    this.isLoading = false,
    this.todayLog,
    this.context,
    this.summary,
    this.error,
  });

  AttendanceState copyWith({
    bool? isLoading,
    AttendanceLog? todayLog,
    Map<String, dynamic>? context,
    DailySummary? summary,
    String? error,
    bool clearError = false,
  }) {
    return AttendanceState(
      isLoading: isLoading ?? this.isLoading,
      todayLog: todayLog ?? this.todayLog,
      context: context ?? this.context,
      summary: summary ?? this.summary,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class AttendanceNotifier extends StateNotifier<AttendanceState> {
  final AttendanceRepository _repository;
  final Ref _ref;

  AttendanceNotifier(this._repository, this._ref) : super(AttendanceState()) {
    loadTodayData();
  }

  Future<void> loadTodayData() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.getTodayStatus();
      state = state.copyWith(
        todayLog: data['log'],
        context: data['context'],
        isLoading: false,
      );
      _loadSummary();
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> _loadSummary() async {
    final authState = _ref.read(authProvider);
    if (authState.employee != null) {
      try {
        final summary = await _repository.getDailySummary(authState.employee!.id);
        state = state.copyWith(summary: summary);
      } catch (e) {
        // Ignore summary loading errors, non-blocking
      }
    }
  }

  Future<void> checkIn() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final log = await _repository.checkIn();
      state = state.copyWith(todayLog: log, isLoading: false);
      _loadSummary();
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> checkOut() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final log = await _repository.checkOut();
      state = state.copyWith(todayLog: log, isLoading: false);
      _loadSummary();
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }
}

final attendanceProvider = StateNotifierProvider<AttendanceNotifier, AttendanceState>((ref) {
  return AttendanceNotifier(ref.watch(attendanceRepositoryProvider), ref);
});

final historyProvider = FutureProvider.family<List<AttendanceLog>, DateTime>((ref, date) async {
  final repo = ref.watch(attendanceRepositoryProvider);
  return await repo.getHistory(date.year, date.month);
});
