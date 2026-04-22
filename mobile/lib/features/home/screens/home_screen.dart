import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/alert_banner.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

/// APV Design v3 — Home conversationnelle.
///
/// Le Home est le point d entree unique : salutation + alertes contextuelles
/// + actions rapides + barre de chat Leo (desactivee pour l instant, Phase 2).
///
/// Cette scaffolding est deliberement minimale pour ne pas introduire de
/// dependances IA avant le feature flag `leo_ai`. La Home actuelle reste
/// mobilisable comme point d entree employe, les managers peuvent aussi la
/// consulter (les quick actions s adaptent a `canManageTeam`).
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final employee = ref.watch(authProvider).employee;
    final firstName = employee?.firstName.isNotEmpty == true
        ? employee!.firstName
        : employee?.email.split('@').first ?? '';
    final canManageTeam = employee?.canManageTeam == true;
    final greeting = _greetingForHour(DateTime.now().hour);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                children: [
                  _HomeHeader(greeting: greeting, name: firstName),
                  const SizedBox(height: 20),
                  const _LeoSuggestionBanner(),
                  const SizedBox(height: 24),
                  Text(
                    'Que voulez-vous faire ?',
                    style: AppTypography.subtitle.copyWith(
                      color: AppColors.textDark,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _QuickActionsGrid(canManageTeam: canManageTeam),
                ],
              ),
            ),
            const _ChatInputBar(),
          ],
        ),
      ),
    );
  }

  static String _greetingForHour(int h) {
    if (h < 12) return 'Bonjour';
    if (h < 18) return 'Bon apres-midi';
    return 'Bonsoir';
  }
}

class _HomeHeader extends StatelessWidget {
  const _HomeHeader({required this.greeting, required this.name});

  final String greeting;
  final String name;

  @override
  Widget build(BuildContext context) {
    final dateLabel = DateFormat.EEEE('fr_FR').add_d().add_MMMM().format(DateTime.now());
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          name.isEmpty ? greeting : '$greeting, $name',
          style: AppTypography.display.copyWith(color: AppColors.textDark),
        ),
        const SizedBox(height: 4),
        Text(
          dateLabel,
          style: AppTypography.bodySmall.copyWith(color: AppColors.textMutedDark),
        ),
      ],
    );
  }
}

class _LeoSuggestionBanner extends StatelessWidget {
  const _LeoSuggestionBanner();

  @override
  Widget build(BuildContext context) {
    return const AlertBanner(
      message: 'Leo arrive bientot : il vous guidera par la voix et le texte. En attendant, utilisez les actions rapides ci-dessous.',
      level: AlertLevel.info,
      icon: Icons.auto_awesome,
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
        subtitle: 'Check-in / check-out',
        color: AppColors.rh,
        onTap: () => context.push('/attendance'),
      ),
      _QuickAction(
        icon: Icons.insights,
        label: 'Mon mois',
        subtitle: 'Heures, heures sup, du',
        color: AppColors.info,
        onTap: () => context.push('/me/monthly'),
      ),
      _QuickAction(
        icon: Icons.history,
        label: 'Historique',
        subtitle: 'Tous mes pointages',
        color: AppColors.textMutedDark,
        onTap: () => context.push('/history'),
      ),
      if (canManageTeam)
        _QuickAction(
          icon: Icons.group,
          label: 'Equipe',
          subtitle: 'Employes, invitations',
          color: AppColors.ia,
          onTap: () => context.push('/team'),
        ),
      _QuickAction(
        icon: Icons.settings,
        label: 'Parametres',
        subtitle: 'Compte et app',
        color: AppColors.textMutedDark,
        onTap: () => context.push('/settings'),
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final cols = constraints.maxWidth > 520 ? 3 : 2;
        return GridView.count(
          crossAxisCount: cols,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.25,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: actions.map((a) => _QuickActionCard(action: a)).toList(),
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
    return Material(
      color: AppColors.cardDark,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: action.onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: action.color.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(action.icon, color: action.color, size: 22),
              ),
              const SizedBox(height: 12),
              Text(
                action.label,
                style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
              ),
              const SizedBox(height: 2),
              Text(
                action.subtitle,
                style: AppTypography.caption.copyWith(color: AppColors.textMutedDark),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Barre de chat Leo en pied de Home. Desactivee tant que `leo_ai` n est pas
/// active cote backend — cf APV L.08 (modules actives via `companies.features`).
class _ChatInputBar extends StatelessWidget {
  const _ChatInputBar();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(
        16,
        8,
        16,
        8 + MediaQuery.of(context).padding.bottom,
      ),
      decoration: const BoxDecoration(
        color: AppColors.bgDark,
        border: Border(top: BorderSide(color: AppColors.borderDark)),
      ),
      child: Row(
        children: [
          Icon(Icons.auto_awesome, color: AppColors.ia.withValues(alpha: 0.7)),
          const SizedBox(width: 10),
          Expanded(
            child: Opacity(
              opacity: 0.6,
              child: TextField(
                enabled: false,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: 'Leo arrive bientot...',
                  hintStyle: AppTypography.bodySmall.copyWith(color: AppColors.textMutedDark),
                  filled: true,
                  fillColor: AppColors.cardDark,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Icon(Icons.mic_none, color: AppColors.textMutedDark),
        ],
      ),
    );
  }
}
