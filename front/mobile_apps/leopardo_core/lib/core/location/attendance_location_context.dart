class AttendanceLocationContext {
  const AttendanceLocationContext({
    this.latitude,
    this.longitude,
    this.accuracyMeters,
    this.status = AttendanceLocationStatus.unavailable,
    this.message,
  });

  final double? latitude;
  final double? longitude;
  final double? accuracyMeters;
  final AttendanceLocationStatus status;
  final String? message;

  bool get hasCoordinates => latitude != null && longitude != null;

  Map<String, dynamic> toAttendancePayload() {
    return {
      if (latitude != null) 'gps_lat': latitude,
      if (longitude != null) 'gps_lng': longitude,
      if (accuracyMeters != null) 'gps_accuracy': accuracyMeters,
    };
  }
}

enum AttendanceLocationStatus {
  available,
  permissionDenied,
  permissionDeniedForever,
  serviceDisabled,
  timeout,
  unavailable,
}
