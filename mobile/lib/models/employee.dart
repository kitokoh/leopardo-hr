class Employee {
  final int id;
  final String? companyId;
  final String firstName;
  final String lastName;
  final String email;
  final String? role;
  final String? managerRole;
  final String status;
  final bool biometricFaceEnabled;
  final bool biometricFingerprintEnabled;
  final String? suggestedHomeRoute;
  final List<String> capabilities;
  final String? salaryType;
  final double? hourlyRate;
  final double? salaryBase;
  final String? currency;

  Employee({
    required this.id,
    this.companyId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.role,
    this.managerRole,
    required this.status,
    this.biometricFaceEnabled = false,
    this.biometricFingerprintEnabled = false,
    this.suggestedHomeRoute,
    this.capabilities = const <String>[],
    this.salaryType,
    this.hourlyRate,
    this.salaryBase,
    this.currency,
  });

  factory Employee.fromJson(Map<String, dynamic> json) {
    final rawCapabilities = json['capabilities'];
    final capabilities = <String>[];
    if (rawCapabilities is List) {
      for (final entry in rawCapabilities) {
        if (entry is String) capabilities.add(entry);
      }
    }

    return Employee(
      id: json['id'],
      companyId: json['company_id'] as String?,
      firstName: (json['first_name'] ?? '') as String,
      lastName: (json['last_name'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      role: json['role'] as String?,
      managerRole: json['manager_role'] as String?,
      status: (json['status'] ?? 'active') as String,
      biometricFaceEnabled: json['biometric_face_enabled'] == true,
      biometricFingerprintEnabled: json['biometric_fingerprint_enabled'] == true,
      suggestedHomeRoute: json['suggested_home_route'] as String?,
      capabilities: capabilities,
      salaryType: json['salary_type'] as String?,
      hourlyRate: _parseDouble(json['hourly_rate']),
      salaryBase: _parseDouble(json['salary_base']),
      currency: json['currency'] as String?,
    );
  }

  bool get isManager => role == 'manager';
  bool get isPrincipal => isManager && managerRole == 'principal';
  bool get isHr => isManager && managerRole == 'rh';
  bool get canManageTeam => isPrincipal || isHr || capabilities.contains('employees.manage');
  bool get canManageInvitations => isPrincipal || isHr || capabilities.contains('invitations.manage');

  String get fullName {
    final full = '$firstName $lastName'.trim();
    return full.isEmpty ? email : full;
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}
