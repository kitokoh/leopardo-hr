import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_hr/features/contracts/providers/contract_provider.dart';

class ContractScreen extends ConsumerWidget {
  const ContractScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final contractsAsync = ref.watch(contractsProvider);
    final l10n = context.l10n;

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          l10n.contractsMobileTitle,
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: l10n.contractsBackTooltip,
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async => await ref.refresh(contractsProvider.future),
        child: contractsAsync.when(
          data: (contracts) => contracts.isEmpty
              ? ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    const SizedBox(height: 80),
                    EmptyState(
                      icon: Icons.description_outlined,
                      title: l10n.contractsEmptyTitle,
                      description: l10n.contractsEmptyDescription,
                    ),
                  ],
                )
              : ListView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(20),
                  itemCount: contracts.length,
                  itemBuilder: (context, index) {
                    final contract = contracts[index];
                    return Card(
                      color: AppColors.cardDark,
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  contract.reference,
                                  style: AppTypography.subtitle.copyWith(
                                    color: AppColors.textDark,
                                  ),
                                ),
                                _StatusChip(status: contract.status),
                              ],
                            ),
                            const SizedBox(height: 8),
                            _InfoRow(
                              label: l10n.contractsLabelType,
                              value: contract.type.toUpperCase(),
                            ),
                            _InfoRow(
                              label: l10n.contractsLabelStartDate,
                              value: contract.startDate,
                            ),
                            _InfoRow(
                              label: l10n.contractsLabelEndDate,
                              value: contract.endDate ?? l10n.contractsStatusCdi,
                            ),
                            _InfoRow(
                              label: l10n.contractsLabelBaseSalary,
                              value:
                                  '${contract.baseSalary.toStringAsFixed(2)} ${contract.currency}',
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
          loading: () => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child: CircularProgressIndicator(
                  semanticsLabel: l10n.contractsLoading,
                ),
              ),
            ),
          ),
          error: (e, _) => SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: SizedBox(
              height: 400,
              child: Center(
                child: Text(
                  e.toString(),
                  style: const TextStyle(color: AppColors.danger),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final color = switch (status) {
      'active' => AppColors.success,
      'expired' => AppColors.danger,
      'draft' => AppColors.textMuted,
      _ => AppColors.warning,
    };
    final label = switch (status) {
      'active' => l10n.contractsStatusActive,
      'expired' => l10n.contractsStatusExpired,
      'draft' => l10n.contractsStatusDraft,
      _ => status.toUpperCase(),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textMutedDark,
            ),
          ),
          Text(
            value,
            style: AppTypography.bodySmall.copyWith(color: AppColors.textDark),
          ),
        ],
      ),
    );
  }
}
