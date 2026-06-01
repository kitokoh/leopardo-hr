import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/branding/tenant_branding.dart';
import 'package:leopardo_core/core/branding/tenant_branding_repository.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';

final tenantBrandingProvider = FutureProvider<TenantBranding?>((ref) async {
  final employee = ref.watch(authProvider.select((state) => state.employee));
  if (employee == null) {
    return null;
  }

  try {
    return await TenantBrandingRepository(
      ref.watch(apiClientProvider),
    ).read().timeout(const Duration(seconds: 6));
  } catch (_) {
    return null;
  }
});
