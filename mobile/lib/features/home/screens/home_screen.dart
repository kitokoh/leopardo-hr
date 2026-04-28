import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/alert_banner.dart';
import 'package:leopardo_rh/core/widgets/leopardo_badge.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

/// APV Design v3 — Home conversationnelle.
///
/// La home accueille l'utilisateur avec Leo, les modules visibles et des
/// actions rapides. Le chat reste desactive tant que l'IA n'est pas branchee.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final employee = ref.watch(authProvider).employee;
    final firstName = employee?.firstName.isNotEmpty == true
        ? employee!.firstName
        : employee?.email.split('@').first ?? '';
    final canManageTeam = employee?.canManageTeam == true;
    final text = AppColors.textPrimaryFor(context);

    return Scaffold(
      backgroundColor: AppColors.backgroundFor(context),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: _HomeHeader(
                          firstName: firstName,
                          canManageTeam: canManageTeam,
                        ),
                      ),
                      const SizedBox(width: 12),
                      IconButton.filledTonal(
                        onPressed: () => context.push('/settings'),
                        icon: const Icon(Icons.tune),
                        tooltip: 'Parametres',
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  _LeoConversationCard(
                    firstName: firstName,
                    canManageTeam: canManageTeam,
                  ),
                  const SizedBox(height: 16),
                  _HomeAlerts(canManageTeam: canManageTeam),
                  const SizedBox(height: 24),
                  Text(
                    'Modules visibles',
                    style: AppTypography.subtitle.copyWith(color: text),
                  ),
                  const SizedBox(height: 12),
                  _ModuleRow(canManageTeam: canManageTeam),
                  const SizedBox(height: 24),
                  Text(
                    'Actions recommandees',
                    style: AppTypography.subtitle.copyWith(color: text),
                  ),
                  const SizedBox(height: 12),
                  _QuickActionsGrid(canManageTeam: canManageTeam),
                  if (canManageTeam) ...[
                    const SizedBox(height: 24),
                    const _ManagerDigestCard(),
                  ],
                ],
              ),
            ),
            const _ChatInputBar(),
          ],
        ),
      ),
    );
  }
}

class _HomeHeader extends StatelessWidget {
  const _HomeHeader({
    required this.firstName,
    required this.canManageTeam,
  });

  final String firstName;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    final greeting = _greetingForHour(DateTime.now().hour);
    final dateLabel = DateFormat.EEEE('fr_FR')
        .add_d()
        .add_MMMM()
        .format(DateTime.now());
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              LeopardoBadge.domain(
                'rh',
                canManageTeam ? 'RH / Manager' : 'Employe mobile',
                icon: canManageTeam ? Icons.group : Icons.smartphone,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            firstName.isEmpty ? greeting : '$greeting, $firstName',
            style: AppTypography.display.copyWith(color: text),
          ),
          const SizedBox(height: 6),
          Text(
            dateLabel,
            style: AppTypography.bodySmall.copyWith(color: muted),
          ),
        ],
      ),
    );
  }

  static String _greetingForHour(int hour) {
    if (hour < 12) return 'Bonjour';
    if (hour < 18) return 'Bon apres-midi';
    return 'Bonsoir';
  }
}

class _LeoConversationCard extends StatelessWidget {
  const _LeoConversationCard({
    required this.firstName,
    required this.canManageTeam,
  });

  final String firstName;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.tint(context, AppColors.ia, lightAlpha: 0.13),
            AppColors.surfaceFor(context),
          ],
        ),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.tint(
                    context,
                    AppColors.ia,
                    lightAlpha: 0.18,
                    darkAlpha: 0.24,
                  ),
                ),
                child: const Icon(Icons.auto_awesome, color: AppColors.ia),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Leo vous ouvre la journee',
                  style: AppTypography.subtitle.copyWith(color: text),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            canManageTeam
                ? 'Bonjour ${firstName.isEmpty ? "manager" : firstName}. Commencez par le suivi du pointage, puis utilisez les actions RH pour piloter votre equipe.'
                : 'Bonjour ${firstName.isEmpty ? "a vous" : firstName}. Vous pouvez pointer, suivre votre mois et retrouver votre historique sans quitter cet ecran.',
            style: AppTypography.body.copyWith(color: text),
          ),
          const SizedBox(height: 10),
          Text(
            'Le chat vocal et textuel arrive bientot. En attendant, la home vous guide avec des actions simples et visibles.',
            style: AppTypography.bodySmall.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _HomeAlerts extends StatelessWidget {
  const _HomeAlerts({required this.canManageTeam});

  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AlertBanner(
          message: canManageTeam
              ? 'Leopardo RH reste mobile-first: le suivi d equipe est ici, et le back-office web n arrive qu en support.'
              : 'Votre application reste la source de verite pour le pointage et le suivi de vos heures.',
          level: AlertLevel.info,
          icon: Icons.phone_iphone,
        ),
        const SizedBox(height: 10),
        const AlertBanner(
          message: 'Finance, cameras et Leo complet sont deja presents dans la vision produit mais restent en attente d activation.',
          level: AlertLevel.warning,
          icon: Icons.upcoming_outlined,
        ),
      ],
    );
  }
}

class _ModuleRow extends StatelessWidget {
  const _ModuleRow({required this.canManageTeam});

  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    final modules = <_ModuleChipData>[
      _ModuleChipData(
        domain: 'rh',
        title: canManageTeam ? 'RH & equipe' : 'RH & pointage',
        subtitle: 'Actif maintenant',
        icon: canManageTeam ? Icons.groups : Icons.fingerprint,
        onTap: () => context.push(canManageTeam ? '/team' : '/attendance'),
      ),
      const _ModuleChipData(
        domain: 'finance',
        title: 'Finance',
        subtitle: 'Activable bientot',
        icon: Icons.account_balance_wallet_outlined,
      ),
      const _ModuleChipData(
        domain: 'security',
        title: 'Securite',
        subtitle: 'Cameras Phase 2',
        icon: Icons.shield_outlined,
      ),
      const _ModuleChipData(
        domain: 'ia',
        title: 'Leo IA',
        subtitle: 'Chat a venir',
        icon: Icons.auto_awesome,
      ),
    ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final module in modules) ...[
            _ModuleChip(data: module),
            const SizedBox(width: 10),
          ],
        ],
      ),
    );
  }
}

class _ModuleChipData {
  const _ModuleChipData({
    required this.domain,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.onTap,
  });

  final String domain;
  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;
}

class _ModuleChip extends StatelessWidget {
  const _ModuleChip({required this.data});

  final _ModuleChipData data;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(data.domain);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(22),
      child: Ink(
        width: 180,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.surfaceFor(context),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppColors.borderFor(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.tint(
                  context,
                  color,
                  lightAlpha: 0.16,
                  darkAlpha: 0.24,
                ),
              ),
              child: Icon(data.icon, color: color),
            ),
            const SizedBox(height: 14),
            Text(
              data.title,
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 4),
            Text(
              data.subtitle,
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActionsGrid extends StatelessWidget {
  const _QuickActionsGrid({required this.canManageTeam});

  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    final actions = <_QuickAction>[
      _QuickAction(
        icon: Icons.fingerprint,
        label: 'Pointer',
        subtitle: 'Demarrer ou terminer votre journee',
        color: AppColors.rh,
        onTap: () => context.push('/attendance'),
      ),
      _QuickAction(
        icon: Icons.stacked_bar_chart,
        label: 'Mon mois',
        subtitle: 'Heures, supplementaires et estime',
        color: AppColors.info,
        onTap: () => context.push('/me/monthly'),
      ),
      _QuickAction(
        icon: Icons.history,
        label: 'Historique',
        subtitle: 'Revoir tous mes pointages',
        color: AppColors.warning,
        onTap: () => context.push('/history'),
      ),
      if (canManageTeam)
        _QuickAction(
          icon: Icons.group,
          label: 'Equipe',
          subtitle: 'Employes et invitations',
          color: AppColors.ia,
          onTap: () => context.push('/team'),
        ),
      _QuickAction(
        icon: Icons.settings,
        label: 'Parametres',
        subtitle: 'Profil, securite et langue',
        color: AppColors.textMuted,
        onTap: () => context.push('/settings'),
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth > 540 ? 3 : 2;
        return GridView.count(
          crossAxisCount: columns,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.08,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: actions
              .map((action) => _QuickActionCard(action: action))
              .toList(),
        );
      },
    );
  }
}

class _QuickAction {
  const _QuickAction({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;
}

class _QuickActionCard extends StatelessWidget {
  const _QuickActionCard({required this.action});

  final _QuickAction action;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return InkWell(
      borderRadius: BorderRadius.circular(24),
      onTap: action.onTap,
      child: Ink(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.surfaceFor(context),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: AppColors.borderFor(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.tint(
                      context,
                      action.color,
                      lightAlpha: 0.16,
                      darkAlpha: 0.24,
                    ),
                  ),
                  child: Icon(action.icon, color: action.color),
                ),
                const Spacer(),
                Icon(Icons.arrow_outward, color: muted, size: 18),
              ],
            ),
            const Spacer(),
            Text(
              action.label,
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 6),
            Text(
              action.subtitle,
              style: AppTypography.bodySmall.copyWith(color: muted),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

class _ManagerDigestCard extends StatelessWidget {
  const _ManagerDigestCard();

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'A surveiller aujourd hui',
            style: AppTypography.subtitle.copyWith(color: text),
          ),
          const SizedBox(height: 14),
          Row(
            children: const [
              Expanded(
                child: _DigestTile(
                  color: AppColors.success,
                  value: '18',
                  label: 'presents',
                ),
              ),
              SizedBox(width: 10),
              Expanded(
                child: _DigestTile(
                  color: AppColors.warning,
                  value: '3',
                  label: 'retards',
                ),
              ),
              SizedBox(width: 10),
              Expanded(
                child: _DigestTile(
                  color: AppColors.info,
                  value: '2',
                  label: 'actions RH',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Le detail complet vit dans les ecrans RH, mais la home garde ici les signaux les plus utiles.',
            style: AppTypography.bodySmall.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _DigestTile extends StatelessWidget {
  const _DigestTile({
    required this.color,
    required this.value,
    required this.label,
  });

  final Color color;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.tint(
          context,
          color,
          lightAlpha: 0.12,
          darkAlpha: 0.18,
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: AppTypography.title.copyWith(color: text),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: AppTypography.caption.copyWith(color: color),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

class _ChatInputBar extends StatelessWidget {
  const _ChatInputBar();

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      padding: EdgeInsets.fromLTRB(
        16,
        10,
        16,
        10 + MediaQuery.of(context).padding.bottom,
      ),
      decoration: BoxDecoration(
        color: AppColors.backgroundFor(context),
        border: Border(
          top: BorderSide(color: AppColors.borderFor(context)),
        ),
      ),
      child: Row(
        children: [
          Icon(Icons.auto_awesome, color: AppColors.ia.withValues(alpha: 0.75)),
          const SizedBox(width: 10),
          Expanded(
            child: Opacity(
              opacity: 0.75,
              child: TextField(
                enabled: false,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: 'Leo arrive bientot...',
                  hintStyle: AppTypography.bodySmall.copyWith(color: muted),
                  filled: true,
                  fillColor: AppColors.surfaceFor(context),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(999),
                    borderSide: BorderSide.none,
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Icon(Icons.mic_none, color: muted),
        ],
      ),
    );
  }
}
