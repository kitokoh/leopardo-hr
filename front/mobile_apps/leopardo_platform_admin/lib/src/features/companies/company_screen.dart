import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/shimmer_loading.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

final platformCompaniesProvider = FutureProvider<List<PlatformCompany>>((ref) {
  return ref.watch(platformRepositoryProvider).companies();
});

class CompanyScreen extends ConsumerWidget {
  const CompanyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final companies = ref.watch(platformCompaniesProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: context.l10n.navigationCompanies,
        subtitle: context.l10n.companiesTenantsPlatform,
        leading: IconButton(
          tooltip: context.l10n.commonBack,
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            tooltip: context.l10n.cabinetScreenCreate,
            onPressed: () => context.push('/platform/companies/new'),
            icon: const Icon(Icons.add_rounded),
          ),
        ],
      ),
      children: [
        companies.when(
          data: (items) => items.isEmpty
              ? MobilePanel(
                  child: Text(
                    context.l10n.companiesEmpty,
                    style: TextStyle(color: MobileSurface.secondary),
                  ),
                )
              : Column(
                  children: items
                      .map(
                        (company) => MobileListCard(
                          icon: Icons.business_rounded,
                          iconColor: AppColors.rh,
                          title: company.name,
                          subtitle:
                              '${company.country} / ${company.currency} - ${company.plan}',
                          trailing: LeopardoBadge.forStatus(
                            company.status,
                            company.status,
                          ),
                          onTap: company.id.isEmpty
                              ? null
                              : () => context.push(
                                    '/platform/companies/${Uri.encodeComponent(company.id)}',
                                  ),
                        ),
                      )
                      .toList(),
                ),
          loading: () => const _CompanyListLoading(),
          error: (error, _) => MobileErrorPanel(
            message: error.toString(),
            onRetry: () => ref.invalidate(platformCompaniesProvider),
          ),
        ),
      ],
    );
  }
}

class _CompanyListLoading extends StatelessWidget {
  const _CompanyListLoading();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(
        4,
        (index) => MobilePanel(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              const ShimmerLoading(width: 40, height: 40, borderRadius: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    ShimmerLoading(width: 140, height: 14),
                    SizedBox(height: 8),
                    ShimmerLoading(width: double.infinity, height: 12),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
