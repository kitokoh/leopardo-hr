
export 'package:leopardo_core/features/attendance/providers/attendance_provider.dart'
    show AttendanceState, AttendanceNotifier;

/// Leopardo manager — provider attendance partagé (leopardo_core, #5279).
/// Le notifier s'appuie sur `currentForAttendance()` (AttendanceLocationService)
/// pour l'état du jour et propage la précision GPS du pointage (`gpsAccuracy`)
/// dans le payload de check-in — voir [AttendanceNotifier] (core).
