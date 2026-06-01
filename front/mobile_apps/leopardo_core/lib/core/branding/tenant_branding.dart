import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';

class TenantBranding {
  const TenantBranding({
    required this.companyId,
    required this.displayName,
    required this.primaryColor,
    required this.accentColor,
    required this.brandMode,
    this.logoUrl,
  });

  final String companyId;
  final String displayName;
  final String? logoUrl;
  final String primaryColor;
  final String accentColor;
  final String brandMode;

  factory TenantBranding.fromApi(Map<String, dynamic> json) {
    final branding =
        json['branding'] is Map
            ? (json['branding'] as Map).cast<String, dynamic>()
            : const <String, dynamic>{};

    return TenantBranding(
      companyId: json['company_id']?.toString() ?? '',
      displayName: _textOrFallback(
        branding['display_name'],
        fallback: 'Entreprise',
      ),
      logoUrl: _emptyToNull(branding['logo_url']),
      primaryColor: _textOrFallback(
        branding['primary_color'],
        fallback: '#10B981',
      ),
      accentColor: _textOrFallback(
        branding['accent_color'],
        fallback: '#2563EB',
      ),
      brandMode: _textOrFallback(branding['brand_mode'], fallback: 'default'),
    );
  }

  Color get safePrimaryColor => parseColor(primaryColor, AppColors.rh);
  Color get safeAccentColor => parseColor(accentColor, AppColors.info);

  bool get hasCustomIdentity {
    return displayName.trim().isNotEmpty ||
        logoUrl != null ||
        primaryColor.toUpperCase() != '#10B981';
  }

  static Color parseColor(String raw, Color fallback) {
    final value = raw.trim().replaceFirst('#', '');
    if (!RegExp(r'^[0-9A-Fa-f]{6}$').hasMatch(value)) {
      return fallback;
    }
    return Color(int.parse('FF$value', radix: 16));
  }

  static String? _emptyToNull(dynamic value) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? null : text;
  }

  static String _textOrFallback(dynamic value, {required String fallback}) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? fallback : text;
  }
}
