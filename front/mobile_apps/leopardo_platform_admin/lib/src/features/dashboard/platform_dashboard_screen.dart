import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

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
        title: 'Administration plateforme',
        subtitle: auth.user?.email ?? 'Super-admin',
        actions: [
          IconButton(
            tooltip: 'Deconnexion',
            onPressed:
                () =>
                    ref.read(platformAuthControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      children: [
        metrics.when(
          data:
              (data) => MobilePanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Vue executive',
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
                          label: 'Entreprises',
                          color: AppColors.rh,
                        ),
                        const SizedBox(width: 10),
                        MobileMetricTile(
                          value: '${data.activeCompanies}',
                          label: 'Actives',
                          color: AppColors.info,
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        MobileMetricTile(
                          value: '${data.trialCompanies}',
                          label: 'Essais',
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
                  ],
                ),
              ),
          loading: () => const MobileEmptyLoading(label: 'Chargement cockpit'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry: () => ref.invalidate(platformMetricsProvider),
              ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Actions plateforme'),
        MobileListCard(
          icon: Icons.business_rounded,
          iconColor: AppColors.rh,
          title: 'Entreprises clientes',
          subtitle: 'Suivre les tenants, leur statut et leur plan.',
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/companies'),
        ),
        MobileListCard(
          icon: Icons.add_business_rounded,
          iconColor: AppColors.info,
          title: 'Creer une entreprise',
          subtitle: 'Provisionner un nouveau client et son manager principal.',
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/companies/new'),
        ),
        MobileListCard(
          icon: Icons.fact_check_rounded,
          iconColor: AppColors.warning,
          title: 'Demandes clients',
          subtitle: 'Approuver ou refuser les demandes de creation.',
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/company-requests'),
        ),
      ],
    );
  }
}
