import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';
import 'package:leopardo_accounting/features/accounting/models/accounting_document.dart';
import 'package:leopardo_accounting/features/accounting/providers/accounting_providers.dart';

/// Suivi des impayés (issue #5236) : documents envoyés/en retard avec le
/// reste à payer (total TTC − payé), triés par échéance croissante.
class UnpaidScreen extends ConsumerWidget {
  const UnpaidScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final unpaid = ref.watch(unpaidProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('unpaid'))),
      body: unpaid.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.cloud_off, size: 48, color: AppColors.danger),
              const SizedBox(height: 12),
              Text(l10n.t('loadError'), style: AppTypography.caption),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: () => ref.invalidate(unpaidProvider),
                child: Text(l10n.t('retry')),
              ),
            ],
          ),
        ),
        data: (items) {
          if (items.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.verified_outlined,
                    size: 48,
                    color: AppColors.success,
                  ),
                  const SizedBox(height: 12),
                  Text(l10n.t('emptyUnpaid'), style: AppTypography.caption),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(unpaidProvider),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) =>
                  _UnpaidTile(document: items[index], l10n: l10n),
            ),
          );
        },
      ),
    );
  }
}

class _UnpaidTile extends StatelessWidget {
  const _UnpaidTile({required this.document, required this.l10n});

  final AccountingDocument document;
  final AppStrings l10n;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final isOverdue = document.status == 'overdue';
    final typeLabel = l10n.t('type_${document.type}');

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: (isOverdue ? AppColors.danger : AppColors.warning)
            .withValues(alpha: 0.15),
        child: Icon(
          isOverdue ? Icons.error_outline : Icons.schedule,
          color: isOverdue ? AppColors.danger : AppColors.warning,
        ),
      ),
      title: Text(
        '$typeLabel ${document.number}',
        style: AppTypography.body.copyWith(color: text),
      ),
      subtitle: Text(
        '${l10n.t('dueDate')} : ${document.dueDate ?? '—'}',
        style: AppTypography.caption.copyWith(color: muted),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            '${l10n.t('remaining')} : ${_formatAmount(document.remaining, document.currency)}',
            style: AppTypography.bodySmall.copyWith(
              color: isOverdue ? AppColors.danger : AppColors.textPrimaryFor(context),
            ),
          ),
          Text(
            l10n.t('status_${document.status}'),
            style: AppTypography.caption.copyWith(
              color: isOverdue ? AppColors.danger : AppColors.warning,
            ),
          ),
        ],
      ),
    );
  }

  static String _formatAmount(double amount, String? currency) {
    final code = (currency == null || currency.isEmpty) ? '' : ' $currency';
    return '${amount.toStringAsFixed(2)}$code';
  }
}
