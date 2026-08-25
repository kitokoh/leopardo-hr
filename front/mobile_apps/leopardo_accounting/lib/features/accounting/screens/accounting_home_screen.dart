import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';
import 'package:leopardo_accounting/features/auth/providers/auth_provider.dart';

/// Écran d'accueil — hub de navigation (liste documents, création facture,
/// suivi des impayés). Issue #5236.
class AccountingHomeScreen extends ConsumerWidget {
  const AccountingHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('appName')),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: l10n.t('logout'),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
      ),
      backgroundColor: bg,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l10n.t('home'),
                style: AppTypography.title.copyWith(color: text),
              ),
              const SizedBox(height: 24),
              _ActionCard(
                icon: Icons.description_outlined,
                color: AppColors.rh,
                title: l10n.t('documents'),
                subtitle: l10n.t('documentsSubtitle'),
                onTap: () => context.push('/documents'),
              ),
              const SizedBox(height: 12),
              _ActionCard(
                icon: Icons.add_circle_outline,
                color: AppColors.success,
                title: l10n.t('createInvoice'),
                subtitle: l10n.t('createInvoiceSubtitle'),
                onTap: () => context.push('/create-invoice'),
              ),
              const SizedBox(height: 12),
              _ActionCard(
                icon: Icons.payments_outlined,
                color: AppColors.warning,
                title: l10n.t('unpaid'),
                subtitle: l10n.t('unpaidSubtitle'),
                onTap: () => context.push('/unpaid'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.15),
          child: Icon(icon, color: color),
        ),
        title: Text(title, style: AppTypography.body.copyWith(color: text)),
        subtitle: Text(
          subtitle,
          style: AppTypography.caption.copyWith(color: muted),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
