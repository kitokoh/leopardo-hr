import 'dart:async';

import 'package:geolocator/geolocator.dart';
import 'package:leopardo_core/core/location/attendance_location_context.dart';

class AttendanceLocationService {
  const AttendanceLocationService();

  Future<AttendanceLocationContext> currentForAttendance({
    Duration timeout = const Duration(seconds: 3),
  }) async {
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled()
          .timeout(timeout);
      if (!serviceEnabled) {
        return const AttendanceLocationContext(
          status: AttendanceLocationStatus.serviceDisabled,
          message:
              'Position desactivee. Le pointage continue sans verification de zone.',
        );
      }

      var permission = await Geolocator.checkPermission().timeout(timeout);
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission().timeout(timeout);
      }

      if (permission == LocationPermission.denied) {
        return const AttendanceLocationContext(
          status: AttendanceLocationStatus.permissionDenied,
          message:
              'Permission position refusee. Le pointage continue sans verification de zone.',
        );
      }

      if (permission == LocationPermission.deniedForever) {
        return const AttendanceLocationContext(
          status: AttendanceLocationStatus.permissionDeniedForever,
          message:
              'Permission position bloquee. Activez-la dans les reglages pour verifier la zone.',
        );
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: timeout,
        ),
      ).timeout(timeout + const Duration(milliseconds: 500));

      return AttendanceLocationContext(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracyMeters: position.accuracy,
        status: AttendanceLocationStatus.available,
      );
    } on TimeoutException {
      return const AttendanceLocationContext(
        status: AttendanceLocationStatus.timeout,
        message:
            'Position trop lente. Le pointage continue, la zone sera reverifiee plus tard.',
      );
    } catch (_) {
      return const AttendanceLocationContext(
        status: AttendanceLocationStatus.unavailable,
        message:
            'Position indisponible. Le pointage continue sans bloquer votre journee.',
      );
    }
  }
}
