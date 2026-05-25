import 'package:leopardo_core/models/mobile_experience.dart';

class Employee {
  final int id;
  final String? matricule;
  final String? companyId;
  final String firstName;
  final String lastName;
  final String email;
  final String? role;
  final String? managerRole;
  final String status;
  final String? photoUrl;
  final DateTime? hireDate;
  final bool biometricFaceEnabled;
  final bool biometricFingerprintEnabled;
  final String? suggestedHomeRoute;
  final List<String> capabilities;
  final String? salaryType;
  final double? hourlyRate;
  final double? salaryBase;
  final String? currency;
  final String language;
  final bool isRtl;
  final Map<String, bool> features;
  final MobileExperience mobileExperience;

  Employee({
    required this.id,
    this.matricule,
    this.companyId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.role,
    this.managerRole,
    required this.status,
    this.photoUrl,
    this.hireDate,
    this.biometricFaceEnabled = false,
    this.biometricFingerprintEnabled = false,
    this.suggestedHomeRoute,
    this.capabilities = const <String>[],
    this.salaryType,
    this.hourlyRate,
    this.salaryBase,
    this.currency,
    this.language = 'fr',
    this.isRtl = false,
    this.features = const <String, bool>{},
    this.mobileExperience = const MobileExperience(
      stage: 'regular',
      modules: <MobileModule>[],
      quickActions: <MobileQuickAction>[],
    ),
  });

  factory Employee.fromJson(Map<String, dynamic> json) {
    final rawCapabilities = json['capabilities'];
    final capabilities = <String>[];
    if (rawCapabilities is List) {
      for (final entry in rawCapabilities) {
        if (entry is String) capabilities.add(entry);
      }
    } else if (rawCapabilities is Map) {
      rawCapabilities.forEach((key, value) {
        if (value == true && key is String) capabilities.add(key);
      });
    }

    final features = <String, bool>{};
    final rawFeatures = json['features'];
    if (rawFeatures is Map) {
      rawFeatures.forEach((key, value) {
        if (key is String) {
          features[key] = value == true;
        }
      });
    }

    final hireDateRaw = json['hire_date'];

    return Employee(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      matricule: json['matricule']?.toString(),
      companyId: json['company_id']?.toString(),
      firstName: (json['first_name'] ?? '') as String,
      lastName: (json['last_name'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      role: json['role'] as String?,
      managerRole: json['manager_role'] as String?,
      status: (json['status'] ?? 'active') as String,
      photoUrl: json['photo_url']?.toString(),
      hireDate:
          hireDateRaw != null
              ? DateTime.tryParse(hireDateRaw.toString())
              : null,
      biometricFaceEnabled: json['biometric_face_enabled'] == true,
      biometricFingerprintEnabled:
          json['biometric_fingerprint_enabled'] == true,
      suggestedHomeRoute: json['suggested_home_route'] as String?,
      capabilities: capabilities,
      salaryType: json['salary_type'] as String?,
      hourlyRate: _parseDouble(json['hourly_rate']),
      salaryBase: _parseDouble(json['salary_base']),
      currency: json['currency'] as String?,
      language: (json['language'] ?? 'fr') as String,
      isRtl: json['is_rtl'] == true,
      features: features,
      mobileExperience: MobileExperience.fromJson(
        json['mobile_experience'] is Map
            ? (json['mobile_experience'] as Map).cast<String, dynamic>()
            : null,
      ),
    );
  }

  bool get isManager => role == 'manager';
  bool get isPrincipal => isManager && managerRole == 'principal';
  bool get isHr => isManager && managerRole == 'rh';
  bool get canManageTeam =>
      isPrincipal ||
      isHr ||
      capabilities.contains('can_create_employees') ||
      capabilities.contains('employees.manage');
  bool get canManageInvitations =>
      isPrincipal ||
      isHr ||
      capabilities.contains('can_manage_invitations') ||
      capabilities.contains('invitations.manage');
  bool get hasRhModule => features['rh'] ?? true;
  bool get hasFinanceModule => features['finance'] == true;
  bool get hasCamerasModule => features['cameras'] == true;

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
