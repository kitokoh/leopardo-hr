import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';

/// Écran d'accueil de l'agent/vendeur — hub de navigation (TRAVEL-701).
///
/// Parcours terrain : recherche de trajets → vente guichet → billets,
/// manifestes et caisse PDV.
class AgentHomeScreen extends ConsumerWidget {
  const AgentHomeScreen({super.key});

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
                l10n.t('agentHomeTitle'),
                style: AppTypography.title.copyWith(color: text),
              ),
              const SizedBox(height: 24),
              _ActionCard(
                icon: Icons.search,
                color: AppColors.security,
                title: l10n.t('searchTrips'),
                subtitle: l10n.t('searchTripsSubtitle'),
                onTap: () => context.push('/search'),
              ),
              const SizedBox(height: 12),
              _ActionCard(
                icon: Icons.confirmation_number_outlined,
                color: AppColors.success,
                title: l10n.t('bookingsTitle'),
                subtitle: l10n.t('bookingsSubtitle'),
                onTap: () => context.push('/bookings'),
              ),
              const SizedBox(height: 12),
              _ActionCard(
                icon: Icons.receipt_long_outlined,
                color: AppColors.rh,
                title: l10n.t('manifestTitle'),
                subtitle: l10n.t('manifestSubtitle'),
                onTap: () => context.push('/manifest'),
              ),
              const SizedBox(height: 12),
              _ActionCard(
                icon: Icons.point_of_sale,
                color: AppColors.warning,
                title: l10n.t('pdvTitle'),
                subtitle: l10n.t('pdvSubtitle'),
                onTap: () => context.push('/pdv'),
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
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: AppTypography.subtitle.copyWith(color: text),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}
