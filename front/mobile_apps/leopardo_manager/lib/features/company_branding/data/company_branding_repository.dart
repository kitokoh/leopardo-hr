import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class CompanyBrandingRepository {
  CompanyBrandingRepository(this.apiClient);

  final ApiClient apiClient;

  static const _timeout = Duration(seconds: 10);

  Future<CompanyBrandingResponse> read() async {
    final response = await apiClient.requestWithRetry(
      '/company/branding',
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );

    return CompanyBrandingResponse.fromJson(extractDataMap(response.data));
  }

  Future<CompanyBrandingResponse> update(CompanyBrandingPayload payload) async {
    final response = await apiClient.requestWithRetry(
      '/company/branding',
      method: 'PATCH',
      data: payload.toJson(),
      maxRetriesOverride: 0,
      timeoutOverride: _timeout,
    );

    return CompanyBrandingResponse.fromJson(extractDataMap(response.data));
  }
}

class CompanyBrandingResponse {
  const CompanyBrandingResponse({
    required this.companyId,
    required this.branding,
  });

  final String companyId;
  final CompanyBranding branding;

  factory CompanyBrandingResponse.fromJson(Map<String, dynamic> json) {
    return CompanyBrandingResponse(
      companyId: json['company_id']?.toString() ?? '',
      branding: CompanyBranding.fromJson(
        (json['branding'] as Map?)?.cast<String, dynamic>() ??
            const <String, dynamic>{},
      ),
    );
  }
}

class CompanyBranding {
  const CompanyBranding({
    required this.displayName,
    required this.primaryColor,
    required this.accentColor,
    required this.brandMode,
    this.logoUrl,
  });

  final String displayName;
  final String? logoUrl;
  final String primaryColor;
  final String accentColor;
  final String brandMode;

  factory CompanyBranding.fromJson(Map<String, dynamic> json) {
    return CompanyBranding(
      displayName: json['display_name']?.toString() ?? 'Entreprise',
      logoUrl: _emptyToNull(json['logo_url']),
      primaryColor: json['primary_color']?.toString() ?? '#10B981',
      accentColor: json['accent_color']?.toString() ?? '#2563EB',
      brandMode: json['brand_mode']?.toString() ?? 'default',
    );
  }

  static String? _emptyToNull(dynamic value) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? null : text;
  }
}

class CompanyBrandingPayload {
  const CompanyBrandingPayload({
    required this.displayName,
    required this.primaryColor,
    required this.accentColor,
    required this.brandMode,
    this.logoUrl,
  });

  final String displayName;
  final String? logoUrl;
  final String primaryColor;
  final String accentColor;
  final String brandMode;

  Map<String, dynamic> toJson() {
    return {
      'display_name': displayName.trim(),
      'logo_url': logoUrl?.trim().isEmpty == true ? null : logoUrl?.trim(),
      'primary_color': primaryColor.trim().toUpperCase(),
      'accent_color': accentColor.trim().toUpperCase(),
      'brand_mode': brandMode,
    };
  }
}
