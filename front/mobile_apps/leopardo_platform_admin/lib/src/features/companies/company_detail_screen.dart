import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/leopardo_qr_card.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/shimmer_loading.dart';

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
        title: context.l10n.companydetailClientFile,
        subtitle: companyId,
        leading: IconButton(
          tooltip: context.l10n.commonBack,
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        detail.when(
          data: (data) =>
              _CompanyDetailContent(companyId: companyId, data: data),
          loading: () => const _CompanyDetailLoading(),
          error: (error, _) => MobileErrorPanel(
            message: error.toString(),
            onRetry: () => ref.invalidate(
              platformCompanyDetailProvider(companyId),
            ),
          ),
        ),
      ],
    );
  }
}

class _CompanyDetailLoading extends StatelessWidget {
  const _CompanyDetailLoading();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        MobilePanel(
          child: Row(
            children: [
              const ShimmerLoading(width: 48, height: 48, borderRadius: 24),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    ShimmerLoading(width: 160, height: 16),
                    SizedBox(height: 8),
                    ShimmerLoading(width: 120, height: 12),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const Row(
          children: [
            Expanded(child: ShimmerLoading(width: double.infinity, height: 64)),
            SizedBox(width: 10),
            Expanded(child: ShimmerLoading(width: double.infinity, height: 64)),
          ],
        ),
        const SizedBox(height: 18),
        ShimmerLoading(
          width: double.infinity,
          height: 220,
          borderRadius: 16,
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
                  LeopardoBadge(label: health.status, color: riskColor),
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
        MobileSectionLabel(context.l10n.companydetailProductAdoption),
        Row(
          children: [
            MobileMetricTile(
              value: '${health.activeEmployees}/${health.totalEmployees}',
              label: context.l10n.companydetailActiveEmployees,
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: '${health.attendanceLogs30d}',
              label: context.l10n.dashboardCheckins30d,
              color: AppColors.rh,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            MobileMetricTile(
              value: '${health.onboardingProgress}%',
              label: context.l10n.companydetailOnboarding,
              color: AppColors.success,
            ),
            const SizedBox(width: 10),
            MobileMetricTile(
              value: '${health.criticalAnomalies30d}',
              label: context.l10n.companydetailAnomaliesCritical,
              color: health.criticalAnomalies30d > 0
                  ? AppColors.danger
                  : AppColors.rh,
            ),
          ],
        ),
        const SizedBox(height: 18),
        MobileSectionLabel(context.l10n.companydetailClientReference),
        LeopardoQrCard(
          data: companyId,
          title: health.companyName,
          subtitle: context.l10n.companydetailTenantIdHint,
          copyLabel: context.l10n.companydetailCopyId,
        ),
        const SizedBox(height: 18),
        MobileSectionLabel(context.l10n.companydetailSubscription),
        MobilePanel(
          child: Column(
            children: [
              _InfoRow(
                  context.l10n.companydetailPlan, data.subscription.planName),
              _InfoRow(
                  context.l10n.companydetailStatus, data.subscription.status),
              _InfoRow(
                context.l10n.companydetailMonthlyPrice,
                '${data.subscription.monthlyPrice} ${data.subscription.currency}',
              ),
              _InfoRow(
                context.l10n.companydetailEmployeeLimit,
                data.subscription.maxEmployees?.toString() ??
                    context.l10n.companydetailUnlimited,
              ),
              _InfoRow(
                context.l10n.companydetailSubscriptionEnd,
                data.subscription.subscriptionEnd ??
                    context.l10n.companydetailUndefined,
              ),
              const SizedBox(height: 12),
              if (data.subscription.status == 'trial') ...[
                MobilePrimaryAction(
                  icon: Icons.verified_rounded,
                  label: context.l10n.companydetailActivateClient,
                  onPressed: () => _activateCompany(
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
                label: context.l10n.companydetailEditSubscription,
                onPressed: () => showModalBottomSheet<void>(
                  context: context,
                  isScrollControlled: true,
                  backgroundColor: MobileSurface.surface,
                  shape: const RoundedRectangleBorder(
                    borderRadius: BorderRadius.vertical(
                      top: Radius.circular(20),
                    ),
                  ),
                  builder: (_) => _SubscriptionSheet(
                    companyId: companyId,
                    subscription: data.subscription,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        MobileSectionLabel(context.l10n.companydetailActiveModules),
        MobilePanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: data.features.knownModules.map((module) {
                  final enabled = data.features.active[module] == true;
                  return LeopardoBadge(
                    label: module,
                    color: enabled ? AppColors.rh : MobileSurface.disabled,
                    icon: enabled
                        ? Icons.check_circle_rounded
                        : Icons.radio_button_unchecked_rounded,
                  );
                }).toList(),
              ),
              const SizedBox(height: 12),
              MobilePrimaryAction(
                icon: Icons.tune_rounded,
                label: context.l10n.companydetailEditModules,
                onPressed: () => showModalBottomSheet<void>(
                  context: context,
                  isScrollControlled: true,
                  backgroundColor: MobileSurface.surface,
                  shape: const RoundedRectangleBorder(
                    borderRadius: BorderRadius.vertical(
                      top: Radius.circular(20),
                    ),
                  ),
                  builder: (_) => _FeaturesSheet(
                    companyId: companyId,
                    features: data.features,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        MobileSectionLabel(context.l10n.companydetailNextActions),
        if (health.nextActions.isEmpty)
          MobilePanel(
            child: Text(
              context.l10n.companydetailNoUrgentActions,
              style: TextStyle(color: MobileSurface.secondary),
            ),
          )
        else
          ...health.nextActions.map(
            (action) => MobileListCard(
              icon: Icons.flag_rounded,
              iconColor: AppColors.warning,
              title: action,
              subtitle: context.l10n.companydetailRecommendedActionHint,
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
      ).showSnackBar(
          SnackBar(content: Text(context.l10n.companydetailPlanNotFound)));
      return;
    }

    // Capture l10n note before async gap (context may not be mounted after await).
    final activationNote = context.l10n.platformAdminActivationNote;
    try {
      await ref.read(platformRepositoryProvider).updateCompanySubscription(
            companyId: companyId,
            planId: subscription.planId,
            status: 'active',
            notes: activationNote,
          );
      ref.invalidate(platformCompanyDetailProvider(companyId));
      ref.invalidate(platformCompaniesProvider);
      ref.invalidate(platformMetricsProvider);
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(
          SnackBar(content: Text(context.l10n.companydetailClientActivated)));
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
    _status = _statuses.contains(widget.subscription.status)
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
      ).showSnackBar(
          SnackBar(content: Text(context.l10n.companydetailChoosePlan)));
      return;
    }

    setState(() => _submitting = true);
    try {
      await ref.read(platformRepositoryProvider).updateCompanySubscription(
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
      ).showSnackBar(SnackBar(
          content: Text(context.l10n.companydetailSubscriptionUpdated)));
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
    final l10n = context.l10n;
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
          final activePlans = items
              .where((plan) => plan.isActive || plan.id == _planId)
              .toList();
          _planId ??= activePlans
              .firstWhere(
                (plan) =>
                    plan.id == widget.subscription.planId ||
                    plan.name == widget.subscription.planName,
                orElse: () => activePlans.isNotEmpty
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
              Text(
                context.l10n.companydetailEditSubscription,
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
                decoration:
                    InputDecoration(labelText: context.l10n.companydetailPlan),
                items: activePlans
                    .map(
                      (plan) => DropdownMenuItem<int>(
                        value: plan.id,
                        child: Text(
                          l10n.companydetailPlanWithPrice(
                            plan.name,
                            plan.monthlyPrice.toString(),
                          ),
                        ),
                      ),
                    )
                    .toList(),
                onChanged: _submitting
                    ? null
                    : (value) => setState(() => _planId = value),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                dropdownColor: MobileSurface.surface,
                decoration: InputDecoration(
                    labelText: context.l10n.companydetailStatus),
                items: _statuses
                    .map(
                      (status) => DropdownMenuItem<String>(
                        value: status,
                        child: Text(status),
                      ),
                    )
                    .toList(),
                onChanged: _submitting
                    ? null
                    : (value) => setState(() => _status = value ?? _status),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _notes,
                maxLines: 2,
                style: const TextStyle(color: MobileSurface.text),
                decoration: InputDecoration(
                  labelText: context.l10n.companydetailOptionalInternalNote,
                ),
              ),
              const SizedBox(height: 16),
              MobilePrimaryAction(
                icon: Icons.save_rounded,
                label: _submitting
                    ? context.l10n.companydetailSaving
                    : context.l10n.commonSave,
                onPressed: _submitting ? null : _submit,
              ),
            ],
          );
        },
        loading: () =>
            MobileEmptyLoading(label: context.l10n.companydetailLoadingPlans),
        error: (error, _) => MobileErrorPanel(
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
      await ref.read(platformRepositoryProvider).updateCompanyFeatures(
            companyId: widget.companyId,
            features: _features,
          );
      ref.invalidate(platformCompanyDetailProvider(widget.companyId));
      if (!mounted) return;
      Navigator.pop(context);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(
          SnackBar(content: Text(context.l10n.companydetailModulesUpdated)));
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
          Text(
            context.l10n.companydetailEditModules,
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
              onChanged: locked || _submitting
                  ? null
                  : (value) => setState(() => _features[module] = value),
              activeThumbColor: AppColors.rh,
              activeTrackColor: AppColors.rh.withValues(alpha: 0.28),
              title: Text(
                module,
                style: const TextStyle(color: MobileSurface.text),
              ),
              subtitle: locked
                  ? Text(
                      context.l10n.companydetailCoreModuleAlwaysActive,
                      style: TextStyle(color: MobileSurface.secondary),
                    )
                  : null,
            );
          }),
          const SizedBox(height: 12),
          MobilePrimaryAction(
            icon: Icons.save_rounded,
            label: _submitting
                ? context.l10n.companydetailSaving
                : context.l10n.companydetailSaveModules,
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
