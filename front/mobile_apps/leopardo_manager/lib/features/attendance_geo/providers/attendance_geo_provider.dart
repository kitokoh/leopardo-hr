import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/attendance_geo/data/attendance_geo_repository.dart';
import 'package:leopardo_core/features/attendance_geo/data/models/geo_attendance_session.dart';

final managerAttendanceGeoRepositoryProvider =
    Provider<ManagerAttendanceGeoRepository>((ref) {
  return ManagerAttendanceGeoRepository(ref.watch(apiClientProvider));
});

/// Liste des sessions en attente de validation manager.
final pendingGeoSessionsProvider =
    FutureProvider.autoDispose<List<GeoAttendanceSession>>((ref) async {
  return ref
      .watch(managerAttendanceGeoRepositoryProvider)
      .getPendingSessions();
});

/// Stats du dashboard Smart Attendance.
final smartAttendanceDashboardProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  return ref
      .watch(managerAttendanceGeoRepositoryProvider)
      .getDashboardStats();
});
