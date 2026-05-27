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

  final String id;
  final String name;
  final String status;
  final String country;
  final String plan;
  final String createdAt;

  factory PlatformCompany.fromJson(Map<String, dynamic> json) {
    final planValue = json['plan'];
    return PlatformCompany(
      id: json['id']?.toString() ?? '',
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

class PlatformCompanyHealth {
  const PlatformCompanyHealth({
    required this.companyName,
    required this.status,
    required this.country,
    required this.timezone,
    required this.planName,
    required this.healthScore,
    required this.riskLevel,
    required this.activeEmployees,
    required this.totalEmployees,
    required this.attendanceLogs30d,
    required this.criticalAnomalies30d,
    required this.onboardingProgress,
    required this.nextActions,
  });

  final String companyName;
  final String status;
  final String country;
  final String timezone;
  final String planName;
  final int healthScore;
  final String riskLevel;
  final int activeEmployees;
  final int totalEmployees;
  final int attendanceLogs30d;
  final int criticalAnomalies30d;
  final int onboardingProgress;
  final List<String> nextActions;

  factory PlatformCompanyHealth.fromJson(Map<String, dynamic> json) {
    final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? json;
    final company = (data['company'] as Map?)?.cast<String, dynamic>() ?? {};
    final plan = (data['plan'] as Map?)?.cast<String, dynamic>() ?? {};
    final adoption = (data['adoption'] as Map?)?.cast<String, dynamic>() ?? {};
    final employees =
        (adoption['employees'] as Map?)?.cast<String, dynamic>() ?? {};
    final attendance =
        (adoption['attendance'] as Map?)?.cast<String, dynamic>() ?? {};
    final anomalies =
        (adoption['anomalies'] as Map?)?.cast<String, dynamic>() ?? {};
    final onboarding =
        (adoption['onboarding'] as Map?)?.cast<String, dynamic>() ?? {};
    final actions = data['next_actions'];

    return PlatformCompanyHealth(
      companyName: company['name']?.toString() ?? 'Client',
      status: company['status']?.toString() ?? 'unknown',
      country: company['country']?.toString() ?? '--',
      timezone: company['timezone']?.toString() ?? '--',
      planName: plan['name']?.toString() ?? 'Plan',
      healthScore: (adoption['health_score'] as num?)?.toInt() ?? 0,
      riskLevel: adoption['risk_level']?.toString() ?? 'unknown',
      activeEmployees: (employees['active'] as num?)?.toInt() ?? 0,
      totalEmployees: (employees['total'] as num?)?.toInt() ?? 0,
      attendanceLogs30d: (attendance['logs_30d'] as num?)?.toInt() ?? 0,
      criticalAnomalies30d: (anomalies['critical_30d'] as num?)?.toInt() ?? 0,
      onboardingProgress:
          (onboarding['progress_percent'] as num?)?.toInt() ?? 0,
      nextActions:
          actions is List
              ? actions
                  .whereType<Map>()
                  .map((item) => item['label']?.toString() ?? '')
                  .where((label) => label.isNotEmpty)
                  .toList()
              : const [],
    );
  }
}

class PlatformCompanySubscription {
  const PlatformCompanySubscription({
    required this.status,
    required this.planName,
    required this.currency,
    required this.monthlyPrice,
    required this.maxEmployees,
    required this.subscriptionEnd,
  });

  final String status;
  final String planName;
  final String currency;
  final num monthlyPrice;
  final int? maxEmployees;
  final String? subscriptionEnd;

  factory PlatformCompanySubscription.fromJson(Map<String, dynamic> json) {
    final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? json;
    final plan = (data['plan'] as Map?)?.cast<String, dynamic>() ?? {};

    return PlatformCompanySubscription(
      status: data['status']?.toString() ?? 'unknown',
      planName: plan['name']?.toString() ?? 'Plan',
      currency: data['currency']?.toString() ?? 'DZD',
      monthlyPrice: plan['price_monthly'] as num? ?? 0,
      maxEmployees: (plan['max_employees'] as num?)?.toInt(),
      subscriptionEnd: data['subscription_end']?.toString(),
    );
  }
}

class PlatformCompanyFeatures {
  const PlatformCompanyFeatures({
    required this.active,
    required this.knownModules,
  });

  final Map<String, bool> active;
  final List<String> knownModules;

  factory PlatformCompanyFeatures.fromJson(Map<String, dynamic> json) {
    final data = (json['data'] as Map?)?.cast<String, dynamic>() ?? json;
    final features = (data['features'] as Map?)?.cast<String, dynamic>() ?? {};
    final modules = data['known_modules'];

    return PlatformCompanyFeatures(
      active: features.map((key, value) => MapEntry(key, value == true)),
      knownModules:
          modules is List
              ? modules.map((item) => item.toString()).toList()
              : features.keys.toList(),
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
