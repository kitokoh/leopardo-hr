import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/smart_attendance/data/smart_attendance_repository.dart';
import 'package:leopardo_manager/features/smart_attendance/data/models/geo_attendance_session.dart';

final managerSmartAttendanceRepositoryProvider =
    Provider<ManagerSmartAttendanceRepository>((ref) {
      return ManagerSmartAttendanceRepository(ref.watch(apiClientProvider));
    });

/// Liste des sessions en attente de validation manager.
final pendingGeoSessionsProvider =
    FutureProvider.autoDispose<List<GeoAttendanceSession>>((ref) async {
      return ref
          .watch(managerSmartAttendanceRepositoryProvider)
          .getPendingSessions();
    });

/// Stats du dashboard Smart Attendance.
final smartAttendanceDashboardProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
      return ref
          .watch(managerSmartAttendanceRepositoryProvider)
          .getDashboardStats();
    });
