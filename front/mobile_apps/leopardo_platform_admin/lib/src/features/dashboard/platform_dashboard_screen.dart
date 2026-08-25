import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/mobile_list_glass_card.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';

import '../../core/platform_providers.dart';
import '../auth/platform_auth_controller.dart';
import '../platform/platform_models.dart';

final platformMetricsProvider = FutureProvider<PlatformMetrics>((ref) {
  return ref.watch(platformRepositoryProvider).metrics();
});

class PlatformDashboardScreen extends ConsumerWidget {
  const PlatformDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(platformAuthControllerProvider);
    final metrics = ref.watch(platformMetricsProvider);

    return MobilePage(
      appBar: MobileTopBar(
        title: context.l10n.dashboardPlatformAdministration,
        subtitle: auth.user?.email ?? context.l10n.dashboardSuperAdmin,
        actions: [
          IconButton(
            tooltip: context.l10n.navigationLogout,
            onPressed: () =>
                ref.read(platformAuthControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      children: [
        metrics.when(
          data: (data) => GlassCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.dashboardExecutiveView,
                  style: TextStyle(
                    color: MobileSurface.text,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    MobileMetricTile(
                      value: '${data.totalCompanies}',
                      label: context.l10n.navigationCompanies,
                      color: AppColors.rh,
                    ),
                    const SizedBox(width: 10),
                    MobileMetricTile(
                      value: '${data.activeCompanies}',
                      label: context.l10n.dashboardActiveLabel,
                      color: AppColors.info,
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    MobileMetricTile(
                      value: '${data.trialCompanies}',
                      label: context.l10n.dashboardTrials,
                      color: AppColors.warning,
                    ),
                    const SizedBox(width: 10),
                    MobileMetricTile(
                      value: '${data.mrr}',
                      label: 'MRR',
                      color: AppColors.success,
                    ),
                  ],
                ),
                // S-6 (#1666) : adoption des widgets partagés (LeopardoBadge)
                // — statut plateforme lisible d'un coup d'œil.
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    LeopardoBadge.present(
                        label: '${data.activeCompanies} actives'),
                    LeopardoBadge.onLeave(
                        label: '${data.trialCompanies} essais'),
                    LeopardoBadge(
                      label: '${data.totalCompanies} totales',
                      color: AppColors.rh,
                    ),
                  ],
                ),
              ],
            ),
          ),
          loading: () =>
              MobileEmptyLoading(label: context.l10n.dashboardLoadingCockpit),
          error: (error, _) => MobileErrorPanel(
            message: error.toString(),
            onRetry: () => ref.invalidate(platformMetricsProvider),
          ),
        ),
        const SizedBox(height: 18),
        MobileSectionLabel(context.l10n.dashboardPlatformActions),
        MobileListGlassCard(
          icon: Icons.business_rounded,
          iconColor: AppColors.rh,
          title: context.l10n.dashboardClientCompanies,
          subtitle: context.l10n.dashboardClientCompaniesHint,
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/companies'),
        ),
        MobileListGlassCard(
          icon: Icons.add_business_rounded,
          iconColor: AppColors.info,
          title: context.l10n.dashboardCreateCompany,
          subtitle: context.l10n.dashboardCreateCompanyHint,
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/companies/new'),
        ),
        MobileListGlassCard(
          icon: Icons.fact_check_rounded,
          iconColor: AppColors.warning,
          title: context.l10n.dashboardClientrequests,
          subtitle: context.l10n.dashboardClientRequestsHint,
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/company-requests'),
        ),
        // #3912 — Support & Edge
        MobileListGlassCard(
          icon: Icons.support_agent_rounded,
          iconColor: AppColors.danger,
          title: context.l10n.dashboardSupportClient,
          subtitle: context.l10n.dashboardTicketsHint,
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/support-tickets'),
        ),
        MobileListGlassCard(
          icon: Icons.router_rounded,
          iconColor: AppColors.rh,
          title: context.l10n.dashboardEdgeNodes,
          subtitle: context.l10n.dashboardEdgeNodesHint,
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/edge-nodes'),
        ),
      ],
    );
  }
}
