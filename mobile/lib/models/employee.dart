class Employee {
  final int id;
  final String? companyId;
  final String firstName;
  final String lastName;
  final String email;
  final String? role;
  final String status;
  final bool biometricFaceEnabled;
  final bool biometricFingerprintEnabled;

  Employee({
    required this.id,
    this.companyId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.role,
    required this.status,
    this.biometricFaceEnabled = false,
    this.biometricFingerprintEnabled = false,
  });

  factory Employee.fromJson(Map<String, dynamic> json) {
    return Employee(
      id: json['id'],
      companyId: json['company_id'] as String?,
      firstName: json['first_name'],
      lastName: json['last_name'],
      email: json['email'],
      role: json['role'] as String?,
      status: json['status'],
      biometricFaceEnabled: json['biometric_face_enabled'] == true,
      biometricFingerprintEnabled: json['biometric_fingerprint_enabled'] == true,
    );
  }

  bool get isManager => role == 'manager';
}
