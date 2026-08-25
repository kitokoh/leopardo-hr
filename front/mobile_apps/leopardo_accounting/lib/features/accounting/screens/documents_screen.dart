import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';
import 'package:leopardo_accounting/features/accounting/models/accounting_document.dart';
import 'package:leopardo_accounting/features/accounting/providers/accounting_providers.dart';

/// Liste des documents comptables (issue #5236) — pull-to-refresh + statut.
class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final documents = ref.watch(documentsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('documents'))),
      body: documents.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) => _ErrorView(
          l10n: l10n,
          onRetry: () => ref.invalidate(documentsProvider),
        ),
        data: (items) {
          if (items.isEmpty) {
            return _EmptyView(l10n: l10n);
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(documentsProvider),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) =>
                  _DocumentTile(document: items[index], l10n: l10n),
            ),
          );
        },
      ),
    );
  }
}

class _DocumentTile extends StatelessWidget {
  const _DocumentTile({required this.document, required this.l10n});

  final AccountingDocument document;
  final AppStrings l10n;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final statusColor = _statusColor(document.status);
    final client = document.contactName ?? l10n.t('noClient');
    final typeLabel = l10n.t('type_${document.type}');

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: statusColor.withValues(alpha: 0.15),
        child: Icon(_statusIcon(document.status), color: statusColor),
      ),
      title: Text(
        '$typeLabel ${document.number}',
        style: AppTypography.body.copyWith(color: text),
      ),
      subtitle: Text(
        '$client · ${_formatAmount(document.totalTtc, document.currency)}',
        style: AppTypography.caption.copyWith(color: muted),
      ),
      trailing: Text(
        l10n.t('status_${document.status}'),
        style: AppTypography.caption.copyWith(color: statusColor),
      ),
    );
  }

  static String _formatAmount(double amount, String? currency) {
    final code = (currency == null || currency.isEmpty) ? '' : ' $currency';
    return '${amount.toStringAsFixed(2)}$code';
  }

  static Color _statusColor(String status) {
    switch (status) {
      case 'paid':
        return AppColors.success;
      case 'overdue':
        return AppColors.danger;
      case 'cancelled':
        return AppColors.warning;
      case 'draft':
        return AppColors.warning;
      default:
        return AppColors.rh;
    }
  }

  static IconData _statusIcon(String status) {
    switch (status) {
      case 'paid':
        return Icons.check_circle_outline;
      case 'overdue':
        return Icons.error_outline;
      case 'cancelled':
        return Icons.cancel_outlined;
      case 'draft':
        return Icons.edit_outlined;
      default:
        return Icons.send_outlined;
    }
  }
}

class _EmptyView extends StatelessWidget {
  const _EmptyView({required this.l10n});

  final AppStrings l10n;

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.folder_open, size: 48, color: muted),
          const SizedBox(height: 12),
          Text(l10n.t('emptyDocuments'), style: AppTypography.caption),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.l10n, required this.onRetry});

  final AppStrings l10n;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final danger = AppColors.danger;
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.cloud_off, size: 48, color: danger),
          const SizedBox(height: 12),
          Text(l10n.t('loadError'), style: AppTypography.caption),
          const SizedBox(height: 12),
          FilledButton.tonal(onPressed: onRetry, child: Text(l10n.t('retry'))),
        ],
      ),
    );
  }
}
