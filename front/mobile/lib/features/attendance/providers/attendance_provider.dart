import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/models/attendance_log.dart';
import 'package:leopardo_rh/models/daily_summary.dart';
import 'package:leopardo_rh/models/monthly_summary.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';

class AttendanceState {
  final bool isLoading;
  final bool isPunching;
  final AttendanceLog? todayLog;
  final Map<String, dynamic>? context;
  final DailySummary? summary;
  final String? error;
  final String? notice;

  AttendanceState({
    this.isLoading = false,
    this.isPunching = false,
    this.todayLog,
    this.context,
    this.summary,
    this.error,
    this.notice,
  });

  AttendanceState copyWith({
    bool? isLoading,
    bool? isPunching,
    AttendanceLog? todayLog,
    Map<String, dynamic>? context,
    DailySummary? summary,
    String? error,
    String? notice,
    bool clearError = false,
    bool clearNotice = false,
  }) {
    return AttendanceState(
      isLoading: isLoading ?? this.isLoading,
      isPunching: isPunching ?? this.isPunching,
      todayLog: todayLog ?? this.todayLog,
      context: context ?? this.context,
      summary: summary ?? this.summary,
      error: clearError ? null : (error ?? this.error),
      notice: clearNotice ? null : (notice ?? this.notice),
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
    state = state.copyWith(
      isLoading: true,
      clearError: true,
      clearNotice: true,
    );
    try {
      final data = await _repository.getTodayStatus();
      state = state.copyWith(
        todayLog: data['log'],
        context: data['context'],
        isLoading: false,
      );
      final authState = _ref.read(authProvider);
      if (authState.employee != null && !authState.employee!.isManager) {
        _loadSummary();
      }
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return;
      }
      if (_isRecoverableLoadError(e)) {
        state = state.copyWith(
          isLoading: false,
          context: {...?state.context, 'load_degraded': true},
          notice:
              'Les donnees du jour prennent plus de temps que prevu. L\'ecran reste utilisable, vous pouvez actualiser.',
        );
        return;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> _loadSummary() async {
    final authState = _ref.read(authProvider);
    if (authState.employee != null) {
      try {
        final summary = await _repository.getMyDailySummary();
        state = state.copyWith(summary: summary);
      } catch (e) {
        // Ignore summary loading errors, non-blocking
      }
    }
  }

  Future<bool> checkIn() async {
    state = state.copyWith(
      isPunching: true,
      clearError: true,
      clearNotice: true,
    );
    try {
      final log = await _repository.checkIn();
      state = state.copyWith(
        todayLog: log,
        isPunching: false,
        notice: 'Arrivee enregistree a l instant.',
      );
      _loadSummary();
      return true;
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return false;
      }
      state = state.copyWith(isPunching: false, error: _friendlyActionError(e));
      return false;
    }
  }

  Future<bool> checkOut() async {
    state = state.copyWith(
      isPunching: true,
      clearError: true,
      clearNotice: true,
    );
    try {
      final log = await _repository.checkOut();
      state = state.copyWith(
        todayLog: log,
        isPunching: false,
        notice: 'Depart enregistre a l instant.',
      );
      _loadSummary();
      return true;
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return false;
      }
      state = state.copyWith(isPunching: false, error: _friendlyActionError(e));
      return false;
    }
  }

  Future<bool> updateCorrection({
    required int logId,
    required DateTime checkIn,
    DateTime? checkOut,
    required String notes,
  }) async {
    state = state.copyWith(isPunching: true, clearError: true);
    try {
      final log = await _repository.updateAttendanceLog(
        logId: logId,
        checkIn: checkIn,
        checkOut: checkOut,
        notes: notes,
      );
      final now = DateTime.now();
      final isToday =
          log.date.year == now.year &&
          log.date.month == now.month &&
          log.date.day == now.day;
      state = state.copyWith(
        todayLog: isToday ? log : state.todayLog,
        isPunching: false,
      );
      await _loadSummary();
      return true;
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return false;
      }
      state = state.copyWith(isPunching: false, error: _friendlyActionError(e));
      return false;
    }
  }

  Future<bool> requestCorrection({
    int? logId,
    required DateTime date,
    required DateTime checkIn,
    DateTime? checkOut,
    required String reason,
  }) async {
    state = state.copyWith(isPunching: true, clearError: true);
    try {
      await _repository.requestCorrection(
        logId: logId,
        date: date,
        checkIn: checkIn,
        checkOut: checkOut,
        reason: reason,
      );
      state = state.copyWith(isPunching: false);
      return true;
    } catch (e) {
      if (e is ApiException && e.statusCode == 401) {
        await _ref.read(authProvider.notifier).logout();
        return false;
      }
      state = state.copyWith(isPunching: false, error: _friendlyActionError(e));
      return false;
    }
  }

  bool _isRecoverableLoadError(Object error) {
    if (error is! ApiException) {
      return false;
    }

    final message = error.message.toLowerCase();

    return error.statusCode == null ||
        message.contains('delai') ||
        message.contains('temps') ||
        message.contains('connexion indisponible') ||
        message.contains('impossible de se connecter');
  }

  String _friendlyActionError(Object error) {
    if (error is ApiException) {
      if (error.statusCode == 409 || error.statusCode == 422) {
        return error.message;
      }
      if (error.statusCode == 403) {
        return 'Votre role ne permet pas cette action de pointage.';
      }
    }

    return 'Le pointage n a pas pu etre confirme. Verifiez la connexion puis reessayez.';
  }
}

final attendanceProvider =
    StateNotifierProvider<AttendanceNotifier, AttendanceState>((ref) {
      return AttendanceNotifier(ref.watch(attendanceRepositoryProvider), ref);
    });

final historyProvider = FutureProvider.family<List<AttendanceLog>, DateTime>((
  ref,
  date,
) async {
  final repo = ref.watch(attendanceRepositoryProvider);
  return await repo.getHistory(date.year, date.month);
});

final monthlySummaryProvider = FutureProvider.family<MonthlySummary, DateTime>((
  ref,
  date,
) async {
  final repo = ref.watch(attendanceRepositoryProvider);
  return await repo.getMyMonthlySummary(year: date.year, month: date.month);
});
