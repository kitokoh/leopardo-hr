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
