import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import '../dashboard/platform_dashboard_screen.dart';
import '../platform/platform_models.dart';
import 'company_screen.dart';

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

final platformPlansProvider = FutureProvider<List<PlatformPlan>>((ref) {
  return ref.watch(platformRepositoryProvider).plans();
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
          data:
              (data) => _CompanyDetailContent(companyId: companyId, data: data),
          loading: () => const MobileEmptyLoading(label: 'Chargement client'),
          error:
              (error, _) => MobileErrorPanel(
                message: error.toString(),
                onRetry:
                    () => ref.invalidate(
                      platformCompanyDetailProvider(companyId),
                    ),
              ),
        ),
      ],
    );
  }
}

class _CompanyDetailContent extends ConsumerWidget {
  const _CompanyDetailContent({required this.companyId, required this.data});

  final String companyId;
  final _CompanyDetailData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
              const SizedBox(height: 12),
              if (data.subscription.status == 'trial') ...[
                MobilePrimaryAction(
                  icon: Icons.verified_rounded,
                  label: 'Activer client',
                  onPressed:
                      () => _activateCompany(
                        context: context,
                        ref: ref,
                        companyId: companyId,
                        subscription: data.subscription,
                      ),
                ),
                const SizedBox(height: 10),
              ],
              MobilePrimaryAction(
                icon: Icons.edit_note_rounded,
                label: 'Modifier abonnement',
                onPressed:
                    () => showModalBottomSheet<void>(
                      context: context,
                      isScrollControlled: true,
                      backgroundColor: MobileSurface.surface,
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.vertical(
                          top: Radius.circular(20),
                        ),
                      ),
                      builder:
                          (_) => _SubscriptionSheet(
                            companyId: companyId,
                            subscription: data.subscription,
                          ),
                    ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Modules actifs'),
        MobilePanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
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
              const SizedBox(height: 12),
              MobilePrimaryAction(
                icon: Icons.tune_rounded,
                label: 'Modifier modules',
                onPressed:
                    () => showModalBottomSheet<void>(
                      context: context,
                      isScrollControlled: true,
                      backgroundColor: MobileSurface.surface,
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.vertical(
                          top: Radius.circular(20),
                        ),
                      ),
                      builder:
                          (_) => _FeaturesSheet(
                            companyId: companyId,
                            features: data.features,
                          ),
                    ),
              ),
            ],
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

  Future<void> _activateCompany({
    required BuildContext context,
    required WidgetRef ref,
    required String companyId,
    required PlatformCompanySubscription subscription,
  }) async {
    if (subscription.planId <= 0) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Plan actuel introuvable')));
      return;
    }

    try {
      await ref
          .read(platformRepositoryProvider)
          .updateCompanySubscription(
            companyId: companyId,
            planId: subscription.planId,
            status: 'active',
            notes: 'Activation directe depuis app mobile platform admin.',
          );
      ref.invalidate(platformCompanyDetailProvider(companyId));
      ref.invalidate(platformCompaniesProvider);
      ref.invalidate(platformMetricsProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Client active')));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }
}

class _SubscriptionSheet extends ConsumerStatefulWidget {
  const _SubscriptionSheet({
    required this.companyId,
    required this.subscription,
  });

  final String companyId;
  final PlatformCompanySubscription subscription;

  @override
  ConsumerState<_SubscriptionSheet> createState() => _SubscriptionSheetState();
}

class _SubscriptionSheetState extends ConsumerState<_SubscriptionSheet> {
  static const _statuses = ['active', 'trial', 'suspended', 'expired'];

  int? _planId;
  late String _status;
  final _notes = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _status =
        _statuses.contains(widget.subscription.status)
            ? widget.subscription.status
            : 'active';
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_planId == null || _planId == 0) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Choisir un plan')));
      return;
    }

    setState(() => _submitting = true);
    try {
      await ref
          .read(platformRepositoryProvider)
          .updateCompanySubscription(
            companyId: widget.companyId,
            planId: _planId!,
            status: _status,
            notes: _notes.text,
          );
      ref.invalidate(platformCompanyDetailProvider(widget.companyId));
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Abonnement mis a jour')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final plans = ref.watch(platformPlansProvider);

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        18,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: plans.when(
        data: (items) {
          final activePlans =
              items
                  .where((plan) => plan.isActive || plan.id == _planId)
                  .toList();
          _planId ??=
              activePlans
                  .firstWhere(
                    (plan) =>
                        plan.id == widget.subscription.planId ||
                        plan.name == widget.subscription.planName,
                    orElse:
                        () =>
                            activePlans.isNotEmpty
                                ? activePlans.first
                                : const PlatformPlan(
                                  id: 0,
                                  name: 'Plan',
                                  monthlyPrice: 0,
                                  yearlyPrice: 0,
                                  maxEmployees: null,
                                  isActive: true,
                                ),
                  )
                  .id;

          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _SheetHandle(),
              const Text(
                'Modifier abonnement',
                style: TextStyle(
                  color: MobileSurface.text,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<int>(
                initialValue: _planId == 0 ? null : _planId,
                dropdownColor: MobileSurface.surface,
                decoration: const InputDecoration(labelText: 'Plan'),
                items:
                    activePlans
                        .map(
                          (plan) => DropdownMenuItem<int>(
                            value: plan.id,
                            child: Text(
                              '${plan.name} - ${plan.monthlyPrice}/mois',
                            ),
                          ),
                        )
                        .toList(),
                onChanged:
                    _submitting
                        ? null
                        : (value) => setState(() => _planId = value),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                dropdownColor: MobileSurface.surface,
                decoration: const InputDecoration(labelText: 'Statut'),
                items:
                    _statuses
                        .map(
                          (status) => DropdownMenuItem<String>(
                            value: status,
                            child: Text(status),
                          ),
                        )
                        .toList(),
                onChanged:
                    _submitting
                        ? null
                        : (value) => setState(() => _status = value ?? _status),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _notes,
                maxLines: 2,
                style: const TextStyle(color: MobileSurface.text),
                decoration: const InputDecoration(
                  labelText: 'Note interne optionnelle',
                ),
              ),
              const SizedBox(height: 16),
              MobilePrimaryAction(
                icon: Icons.save_rounded,
                label: _submitting ? 'Enregistrement...' : 'Enregistrer',
                onPressed: _submitting ? null : _submit,
              ),
            ],
          );
        },
        loading: () => const MobileEmptyLoading(label: 'Chargement plans'),
        error:
            (error, _) => MobileErrorPanel(
              message: error.toString(),
              onRetry: () => ref.invalidate(platformPlansProvider),
            ),
      ),
    );
  }
}

class _FeaturesSheet extends ConsumerStatefulWidget {
  const _FeaturesSheet({required this.companyId, required this.features});

  final String companyId;
  final PlatformCompanyFeatures features;

  @override
  ConsumerState<_FeaturesSheet> createState() => _FeaturesSheetState();
}

class _FeaturesSheetState extends ConsumerState<_FeaturesSheet> {
  late final Map<String, bool> _features = {
    for (final module in widget.features.knownModules)
      module: module == 'rh' ? true : widget.features.active[module] == true,
  };
  bool _submitting = false;

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      await ref
          .read(platformRepositoryProvider)
          .updateCompanyFeatures(
            companyId: widget.companyId,
            features: _features,
          );
      ref.invalidate(platformCompanyDetailProvider(widget.companyId));
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Modules mis a jour')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        18,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SheetHandle(),
          const Text(
            'Modifier modules',
            style: TextStyle(
              color: MobileSurface.text,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          ..._features.keys.map((module) {
            final locked = module == 'rh';
            return SwitchListTile.adaptive(
              value: _features[module] == true,
              onChanged:
                  locked || _submitting
                      ? null
                      : (value) => setState(() => _features[module] = value),
              activeThumbColor: AppColors.rh,
              activeTrackColor: AppColors.rh.withValues(alpha: 0.28),
              title: Text(
                module,
                style: const TextStyle(color: MobileSurface.text),
              ),
              subtitle:
                  locked
                      ? const Text(
                        'Module socle toujours actif',
                        style: TextStyle(color: MobileSurface.secondary),
                      )
                      : null,
            );
          }),
          const SizedBox(height: 12),
          MobilePrimaryAction(
            icon: Icons.save_rounded,
            label: _submitting ? 'Enregistrement...' : 'Enregistrer modules',
            onPressed: _submitting ? null : _submit,
          ),
        ],
      ),
    );
  }
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 36,
        height: 4,
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          color: MobileSurface.border,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
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
