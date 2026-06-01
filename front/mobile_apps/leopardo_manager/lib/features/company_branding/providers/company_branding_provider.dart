import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/company_branding/data/company_branding_repository.dart';

final companyBrandingProvider = FutureProvider<CompanyBrandingResponse>((ref) {
  return ref.watch(companyBrandingRepositoryProvider).read();
});
