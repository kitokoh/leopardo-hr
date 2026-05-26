class PlatformAdminUser {
  const PlatformAdminUser({
    required this.id,
    required this.name,
    required this.email,
    required this.twoFactorEnabled,
  });

  final int id;
  final String name;
  final String email;
  final bool twoFactorEnabled;

  factory PlatformAdminUser.fromJson(Map<String, dynamic> json) {
    return PlatformAdminUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? 'Super admin',
      email: json['email']?.toString() ?? '',
      twoFactorEnabled: json['two_fa_enabled'] == true,
    );
  }
}

class PlatformCompany {
  const PlatformCompany({
    required this.id,
    required this.name,
    required this.status,
    required this.country,
    required this.plan,
    required this.createdAt,
  });

  final int id;
  final String name;
  final String status;
  final String country;
  final String plan;
  final String createdAt;

  factory PlatformCompany.fromJson(Map<String, dynamic> json) {
    final planValue = json['plan'];
    return PlatformCompany(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? json['company_name']?.toString() ?? '-',
      status: json['status']?.toString() ?? 'unknown',
      country: json['country']?.toString() ?? '--',
      plan:
          planValue is Map
              ? planValue['name']?.toString() ?? 'Plan'
              : planValue?.toString() ?? 'Plan',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

class PlatformCompanyRequest {
  const PlatformCompanyRequest({
    required this.id,
    required this.companyName,
    required this.status,
    required this.country,
    required this.city,
    required this.email,
  });

  final int id;
  final String companyName;
  final String status;
  final String country;
  final String city;
  final String email;

  factory PlatformCompanyRequest.fromJson(Map<String, dynamic> json) {
    return PlatformCompanyRequest(
      id: (json['id'] as num?)?.toInt() ?? 0,
      companyName: json['company_name']?.toString() ?? '-',
      status: json['status']?.toString() ?? 'pending',
      country: json['country']?.toString() ?? '--',
      city: json['city']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
    );
  }
}

class PlatformMetrics {
  const PlatformMetrics({
    required this.totalCompanies,
    required this.activeCompanies,
    required this.trialCompanies,
    required this.mrr,
    required this.arr,
  });

  final int totalCompanies;
  final int activeCompanies;
  final int trialCompanies;
  final num mrr;
  final num arr;

  factory PlatformMetrics.fromJson(Map<String, dynamic> json) {
    final companies =
        (json['companies'] as Map?)?.cast<String, dynamic>() ?? {};
    final revenue = (json['revenue'] as Map?)?.cast<String, dynamic>() ?? {};
    return PlatformMetrics(
      totalCompanies: (companies['total'] as num?)?.toInt() ?? 0,
      activeCompanies: (companies['active'] as num?)?.toInt() ?? 0,
      trialCompanies: (companies['trial'] as num?)?.toInt() ?? 0,
      mrr: revenue['mrr'] as num? ?? 0,
      arr: revenue['arr'] as num? ?? 0,
    );
  }
}
