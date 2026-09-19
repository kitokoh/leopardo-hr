export 'package:leopardo_core/features/attendance/providers/attendance_provider.dart'
    show
        AttendanceState,
        AttendanceNotifier,
        attendanceProvider,
        attendanceRepositoryProvider,
        historyProvider,
        monthlySummaryProvider,
        todayTasksProvider,
        monthlyAnomaliesProvider;

/// Leopardo employee — provider attendance partagé (leopardo_core, #7652).
/// Les différences par app/rôle (ton du message hors-zone, etc.) passent par
/// `AttendanceFeatureConfig` surchargé dans `main.dart`, pas par un fork.
