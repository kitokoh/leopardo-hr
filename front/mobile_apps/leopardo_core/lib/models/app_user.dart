/// Statuts personnels cumulables (#5540).
///
/// Un utilisateur peut cumuler plusieurs rôles simultanément
/// (ex: étudiant + chercheur d'emploi).
enum PersonalStatus {
  student,
  employee,
  entrepreneur,
  seekingEmployment;

  static PersonalStatus? fromString(String value) {
    return switch (value) {
      'student' => PersonalStatus.student,
      'employee' => PersonalStatus.employee,
      'entrepreneur' => PersonalStatus.entrepreneur,
      'seeking_employment' => PersonalStatus.seekingEmployment,
      _ => null,
    };
  }

  String toApiValue() => switch (this) {
        PersonalStatus.seekingEmployment => 'seeking_employment',
        _ => name,
      };
}

class AppUser {
  final int id;
  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String provider;
  final String preferredLanguage;
  final String status;
  final String accountType;
  final bool hasCompany;
  /// #5540 — Statuts personnels cumulables
  final List<PersonalStatus> personalStatuses;
  final List<CompanyRequestSummary> companyRequests;
  final List<EmployeeLinkSummary> employeeLinks;

  AppUser({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    this.avatarUrl,
    this.provider = 'email',
    this.preferredLanguage = 'fr',
    this.status = 'active',
    this.accountType = 'user',
    this.hasCompany = false,
    this.personalStatuses = const [],
    this.companyRequests = const [],
    this.employeeLinks = const [],
  });

  String get fullName => '$firstName $lastName'.trim();

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      provider: json['provider'] as String? ?? 'email',
      preferredLanguage: json['preferred_language'] as String? ?? 'fr',
      status: json['status'] as String? ?? 'active',
      accountType: json['account_type'] as String? ?? 'user',
      hasCompany: json['has_company'] as bool? ?? false,
      personalStatuses: (json['personal_statuses'] as List<dynamic>?)
              ?.map((e) => PersonalStatus.fromString(e.toString()))
              .whereType<PersonalStatus>()
              .toList() ??
          [],
      companyRequests:
          (json['company_requests'] as List<dynamic>?)
              ?.map(
                (e) =>
                    CompanyRequestSummary.fromJson(e as Map<String, dynamic>),
              )
              .toList() ??
          [],
      employeeLinks:
          (json['employee_links'] as List<dynamic>?)
              ?.map(
                (e) => EmployeeLinkSummary.fromJson(e as Map<String, dynamic>),
              )
              .toList() ??
          [],
    );
  }
}

class CompanyRequestSummary {
  final int id;
  final String companyName;
  final String status;
  final String? createdAt;

  CompanyRequestSummary({
    required this.id,
    required this.companyName,
    required this.status,
    this.createdAt,
  });

  factory CompanyRequestSummary.fromJson(Map<String, dynamic> json) {
    return CompanyRequestSummary(
      id: json['id'] as int,
      companyName: json['company_name'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      createdAt: json['created_at'] as String?,
    );
  }
}

class EmployeeLinkSummary {
  final String companyId;
  final String? companyName;
  final int employeeId;

  EmployeeLinkSummary({
    required this.companyId,
    this.companyName,
    required this.employeeId,
  });

  factory EmployeeLinkSummary.fromJson(Map<String, dynamic> json) {
    return EmployeeLinkSummary(
      companyId: json['company_id']?.toString() ?? '',
      companyName: json['company_name'] as String?,
      employeeId: json['employee_id'] as int? ?? 0,
    );
  }
}

/// #5540 — Résumé d'une demande d'intégration (rejoindre une entreprise existante).
class IntegrationRequestSummary {
  final int id;
  final String? targetCompanyId;
  final String targetCompanyName;
  final String status;
  final String? adminNotes;
  final String? createdAt;

  IntegrationRequestSummary({
    required this.id,
    this.targetCompanyId,
    required this.targetCompanyName,
    required this.status,
    this.adminNotes,
    this.createdAt,
  });

  factory IntegrationRequestSummary.fromJson(Map<String, dynamic> json) {
    return IntegrationRequestSummary(
      id: json['id'] as int,
      targetCompanyId: json['target_company_id'] as String?,
      targetCompanyName: json['target_company_name'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      adminNotes: json['admin_notes'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

/// #5540 — Entreprise trouvée via la recherche.
class CompanySearchResult {
  final String id;
  final String name;
  final String? country;
  final String? city;

  CompanySearchResult({
    required this.id,
    required this.name,
    this.country,
    this.city,
  });

  factory CompanySearchResult.fromJson(Map<String, dynamic> json) {
    return CompanySearchResult(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      country: json['country'] as String?,
      city: json['city'] as String?,
    );
  }
}
