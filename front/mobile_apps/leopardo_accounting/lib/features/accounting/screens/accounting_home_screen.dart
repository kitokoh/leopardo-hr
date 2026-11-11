import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_accounting/core/i18n/app_strings.dart';
import 'package:leopardo_accounting/core/layout/responsive.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';

/// Écran d'accueil — hub de navigation (liste documents, création facture,
/// suivi des impayés). Issue #5236.
///
/// #9381 — adapté tablette : grille de cartes (1/2/3 colonnes selon la classe
/// de largeur) au lieu d'une colonne pleine largeur, et contenu centré avec une
/// largeur maximale pour ne pas étirer les lignes sur une dalle de 1 000 px.
class AccountingHomeScreen extends ConsumerWidget {
  const AccountingHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);

    final actions = <_HomeAction>[
      _HomeAction(
        icon: Icons.description_outlined,
        color: AppColors.rh,
        title: l10n.t('documents'),
        subtitle: l10n.t('documentsSubtitle'),
        route: '/documents',
      ),
      _HomeAction(
        icon: Icons.add_circle_outline,
        color: AppColors.success,
        title: l10n.t('createInvoice'),
        subtitle: l10n.t('createInvoiceSubtitle'),
        route: '/create-invoice',
      ),
      _HomeAction(
        icon: Icons.payments_outlined,
        color: AppColors.warning,
        title: l10n.t('unpaid'),
        subtitle: l10n.t('unpaidSubtitle'),
        route: '/unpaid',
      ),
    ];

    final columns = Responsive.gridColumns(context);
    final padding = Responsive.horizontalPadding(context);

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
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: Responsive.maxContentWidth),
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(horizontal: padding, vertical: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l10n.t('home'),
                    style: AppTypography.title.copyWith(color: text),
                  ),
                  const SizedBox(height: 24),
                  // Grille adaptative : 1 colonne sur téléphone, 2 sur tablette
                  // portrait, 3 sur tablette paysage.
                  GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: actions.length,
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      // Hauteur fixe : les cartes restent alignées quelle que
                      // soit la longueur du sous-titre traduit.
                      mainAxisExtent: 96,
                    ),
                    itemBuilder: (context, index) {
                      final action = actions[index];
                      return _ActionCard(
                        icon: action.icon,
                        color: action.color,
                        title: action.title,
                        subtitle: action.subtitle,
                        onTap: () => context.push(action.route),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _HomeAction {
  const _HomeAction({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.route,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final String route;
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
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              CircleAvatar(
                backgroundColor: color.withValues(alpha: 0.15),
                child: Icon(icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.body.copyWith(color: text),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
