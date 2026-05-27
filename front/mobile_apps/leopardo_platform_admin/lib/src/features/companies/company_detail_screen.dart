import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

final platformCompanyDetailProvider =
    FutureProvider.family<_CompanyDetailData, String>((ref, companyId) async {
      final repository = ref.watch(platformRepositoryProvider);
      final results = await Future.wait([
        repository.companyHealth(companyId),
        repository.companySubscription(companyId),
        repository.companyFeatures(companyId),
      ]);

      return _CompanyDetailData(
        health: results[0] as PlatformCompanyHealth,
        subscription: results[1] as PlatformCompanySubscription,
        features: results[2] as PlatformCompanyFeatures,
      );
    });

class CompanyDetailScreen extends ConsumerWidget {
  const CompanyDetailScreen({super.key, required this.companyId});

  final String companyId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(platformCompanyDetailProvider(companyId));

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Fiche client',
        subtitle: companyId,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        detail.when(
          data: (data) => _CompanyDetailContent(data: data),
          loading: () => const MobileEmptyLoading(label: 'Chargement client'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry:
                    () =>
                        ref.invalidate(platformCompanyDetailProvider(companyId)),
              ),
        ),
      ],
    );
  }
}

class _CompanyDetailContent extends StatelessWidget {
  const _CompanyDetailContent({required this.data});

  final _CompanyDetailData data;

  @override
  Widget build(BuildContext context) {
    final health = data.health;
    final riskColor = switch (health.riskLevel) {
      'high' => AppColors.danger,
      'medium' => AppColors.warning,
      'low' => AppColors.rh,
      _ => MobileSurface.disabled,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        MobilePanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const MobileIconBubble(
                    icon: Icons.business_rounded,
                    color: AppColors.rh,
                    size: 48,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          health.companyName,
                          style: const TextStyle(
                            color: MobileSurface.text,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${health.country} - ${health.timezone}',
                          style: const TextStyle(
                            color: MobileSurface.secondary,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  MobileStatusPill(label: health.status, color: riskColor),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  MobileMetricTile(
                    value: '${health.healthScore}%',
                    label: 'Sante',
                    color: riskColor,
                  ),
                  const SizedBox(width: 10),
                  MobileMetricTile(
                    value: health.riskLevel,
                    label: 'Risque',
                    color: riskColor,
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Adoption produit'),
        Row(
          children: [
            MobileMetricTile(
              value: '${health.activeEmployees}/${health.totalEmployees}',
              label: 'Employes actifs',
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: '${health.attendanceLogs30d}',
              label: 'Pointages 30j',
              color: AppColors.rh,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            MobileMetricTile(
              value: '${health.onboardingProgress}%',
              label: 'Onboarding',
              color: AppColors.success,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: '${health.criticalAnomalies30d}',
              label: 'Anomalies critiques',
              color:
                  health.criticalAnomalies30d > 0
                      ? AppColors.danger
                      : AppColors.rh,
            ),
          ],
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Abonnement'),
        MobilePanel(
          child: Column(
            children: [
              _InfoRow('Plan', data.subscription.planName),
              _InfoRow('Statut', data.subscription.status),
              _InfoRow(
                'Prix mensuel',
                '${data.subscription.monthlyPrice} ${data.subscription.currency}',
              ),
              _InfoRow(
                'Limite employes',
                data.subscription.maxEmployees?.toString() ?? 'Illimite',
              ),
              _InfoRow(
                'Fin abonnement',
                data.subscription.subscriptionEnd ?? 'Non definie',
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Modules actifs'),
        MobilePanel(
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            children:
                data.features.knownModules.map((module) {
                  final enabled = data.features.active[module] == true;
                  return MobileStatusPill(
                    label: module,
                    color: enabled ? AppColors.rh : MobileSurface.disabled,
                    icon:
                        enabled
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded,
                  );
                }).toList(),
          ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Prochaines actions'),
        if (health.nextActions.isEmpty)
          const MobilePanel(
            child: Text(
              'Aucune action urgente detectee pour ce client.',
              style: TextStyle(color: MobileSurface.secondary),
            ),
          )
        else
          ...health.nextActions.map(
            (action) => MobileListCard(
              icon: Icons.flag_rounded,
              iconColor: AppColors.warning,
              title: action,
              subtitle: 'Action recommandee par le cockpit plateforme.',
            ),
          ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                color: MobileSurface.secondary,
                fontSize: 12,
              ),
            ),
          ),
          Flexible(
            child: Text(
              value,
              style: const TextStyle(
                color: MobileSurface.text,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }
}

class _CompanyDetailData {
  const _CompanyDetailData({
    required this.health,
    required this.subscription,
    required this.features,
  });

  final PlatformCompanyHealth health;
  final PlatformCompanySubscription subscription;
  final PlatformCompanyFeatures features;
}
