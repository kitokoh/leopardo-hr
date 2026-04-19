class BiometricEnrollment {
  const BiometricEnrollment({
    required this.id,
    required this.status,
    required this.requestedFaceEnabled,
    required this.requestedFingerprintEnabled,
    this.requestedFaceReferencePath,
    this.requestedFingerprintReferencePath,
    this.requestedFingerprintDeviceId,
    this.employeeNote,
    this.managerNote,
  });

  final int id;
  final String status;
  final bool requestedFaceEnabled;
  final bool requestedFingerprintEnabled;
  final String? requestedFaceReferencePath;
  final String? requestedFingerprintReferencePath;
  final String? requestedFingerprintDeviceId;
  final String? employeeNote;
  final String? managerNote;

  factory BiometricEnrollment.fromJson(Map<String, dynamic> json) {
    return BiometricEnrollment(
      id: (json['id'] as num?)?.toInt() ?? 0,
      status: json['status']?.toString() ?? 'pending',
      requestedFaceEnabled: json['requested_face_enabled'] == true,
      requestedFingerprintEnabled: json['requested_fingerprint_enabled'] == true,
      requestedFaceReferencePath: json['requested_face_reference_path']?.toString(),
      requestedFingerprintReferencePath: json['requested_fingerprint_reference_path']?.toString(),
      requestedFingerprintDeviceId: json['requested_fingerprint_device_id']?.toString(),
      employeeNote: json['employee_note']?.toString(),
      managerNote: json['manager_note']?.toString(),
    );
  }
}
