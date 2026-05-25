import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/theme/mobile_experience_icons.dart';
import 'package:leopardo_rh/core/widgets/alert_banner.dart';
import 'package:leopardo_rh/core/widgets/leopardo_badge.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/models/mobile_experience.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final employee = ref.watch(authProvider).employee;
    final experience =
        employee?.mobileExperience ??
        const MobileExperience(
          stage: 'regular',
          modules: <MobileModule>[],
          quickActions: <MobileQuickAction>[],
        );
    final stage = experience.stage;
    final quickActions = experience.quickActions.take(4).toList();
    final activeModules = experience.activeModules.take(6).toList();
    final firstName =
        employee?.firstName.isNotEmpty == true
            ? employee!.firstName
            : employee?.email.split('@').first ?? '';
    final canManageTeam = employee?.canManageTeam == true;
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.rh.withValues(alpha: 0.08),
              background,
              AppColors.ia.withValues(alpha: 0.05),
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                  children: [
                    _HeaderRow(
                      firstName: firstName,
                      stage: stage,
                      canManageTeam: canManageTeam,
                    ),
                    const SizedBox(height: 14),
                    _AlertStack(stage: stage, canManageTeam: canManageTeam),
                    const SizedBox(height: 20),
                    _SectionTitle(
                      title: 'Actions rapides',
                      subtitle:
                          stage == 'new'
                              ? 'Les premiers gestes utiles, sans surcharge.'
                              : 'Les raccourcis du jour, prets a l emploi.',
                    ),
                    const SizedBox(height: 12),
                    _QuickActionsGrid(actions: quickActions),
                    const SizedBox(height: 20),
                    _SectionTitle(
                      title: 'Modules actifs',
                      subtitle:
                          'Uniquement les espaces ouverts pour votre profil.',
                    ),
                    const SizedBox(height: 12),
                    _ModulesScroller(modules: activeModules),
                    if (canManageTeam) ...[
                      const SizedBox(height: 20),
                      const _ManagerDigestCard(),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderRow extends StatelessWidget {
  const _HeaderRow({
    required this.firstName,
    required this.stage,
    required this.canManageTeam,
  });

  final String firstName;
  final String stage;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: _HeroHeader(
            firstName: firstName,
            stage: stage,
            canManageTeam: canManageTeam,
          ),
        ),
        const SizedBox(width: 12),
        Container(
          decoration: MobileSurface.cardDecoration(
            color: MobileSurface.chip,
            radius: 14,
          ),
          child: IconButton(
            onPressed: () => context.push('/settings'),
            icon: const Icon(Icons.tune, color: MobileSurface.secondary),
            tooltip: 'Parametres',
          ),
        ),
      ],
    );
  }
}

class _HeroHeader extends StatelessWidget {
  const _HeroHeader({
    required this.firstName,
    required this.stage,
    required this.canManageTeam,
  });

  final String firstName;
  final String stage;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    const text = MobileSurface.text;
    const muted = MobileSurface.muted;
    final dateLabel = DateFormat.EEEE(
      'fr_FR',
    ).add_d().add_MMMM().format(DateTime.now());

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: MobileSurface.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: MobileSurface.border, width: 0.7),
        boxShadow: [
          BoxShadow(
            color: AppColors.rh.withValues(alpha: 0.05),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              LeopardoBadge.domain(
                'rh',
                canManageTeam ? 'RH / manager' : 'Experience employe',
                icon: canManageTeam ? Icons.group : Icons.smartphone,
              ),
              LeopardoBadge.forStatus(
                stage == 'new' ? 'pending' : 'active',
                stage == 'new' ? 'Nouveau parcours' : 'Flux complet',
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            firstName.isEmpty
                ? _greetingForHour(DateTime.now().hour)
                : '${_greetingForHour(DateTime.now().hour)}, $firstName',
            style: AppTypography.display.copyWith(color: text, fontSize: 28),
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

class _AlertStack extends StatelessWidget {
  const _AlertStack({required this.stage, required this.canManageTeam});

  final String stage;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    return AlertBanner(
      message:
          canManageTeam
              ? 'Pointage, validations et equipe restent accessibles en deux gestes.'
              : stage == 'new'
              ? 'Commencez par pointer, puis explorez vos documents et absences.'
              : 'Votre journee RH tient ici: pointage, absences et documents.',
      level: canManageTeam ? AlertLevel.success : AlertLevel.info,
      icon: canManageTeam ? Icons.groups_2_outlined : Icons.phone_iphone,
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    const text = MobileSurface.text;
    const muted = MobileSurface.secondary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: AppTypography.subtitle.copyWith(color: text)),
        const SizedBox(height: 3),
        Text(subtitle, style: AppTypography.bodySmall.copyWith(color: muted)),
      ],
    );
  }
}

class _QuickActionsGrid extends StatelessWidget {
  const _QuickActionsGrid({required this.actions});

  final List<MobileQuickAction> actions;

  @override
  Widget build(BuildContext context) {
    if (actions.isEmpty) {
      return const SizedBox.shrink();
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth > 540 ? 3 : 2;

        return GridView.count(
          crossAxisCount: columns,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.04,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children:
              actions
                  .map((action) => _QuickActionCard(action: action))
                  .toList(),
        );
      },
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  const _QuickActionCard({required this.action});

  final MobileQuickAction action;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(action.domain);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () => context.push(action.route),
      child: Ink(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: MobileSurface.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: MobileSurface.border, width: 0.7),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.tint(
                      context,
                      color,
                      lightAlpha: 0.16,
                      darkAlpha: 0.24,
                    ),
                  ),
                  child: Icon(
                    MobileExperienceIcons.forAction(action.key, action.icon),
                    color: color,
                  ),
                ),
                const Spacer(),
                Icon(Icons.arrow_outward, color: muted, size: 18),
              ],
            ),
            const Spacer(),
            Text(
              action.title,
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 6),
            Text(
              action.description,
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

class _ModulesScroller extends StatelessWidget {
  const _ModulesScroller({required this.modules});

  final List<MobileModule> modules;

  @override
  Widget build(BuildContext context) {
    if (modules.isEmpty) {
      return const SizedBox.shrink();
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final module in modules) ...[
            _ModuleCard(module: module),
            const SizedBox(width: 10),
          ],
        ],
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module});

  final MobileModule module;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(module.domain);
    final text = AppColors.textPrimaryFor(context);
    const muted = MobileSurface.secondary;

    return InkWell(
      onTap: module.isActive ? () => context.push(module.route!) : null,
      borderRadius: BorderRadius.circular(24),
      child: Ink(
        width: 206,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: AppColors.surfaceFor(context),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: AppColors.borderFor(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.tint(
                  context,
                  color,
                  lightAlpha: 0.16,
                  darkAlpha: 0.24,
                ),
              ),
              child: Icon(
                MobileExperienceIcons.forModule(module.key),
                color: color,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              module.title,
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 6),
            Text(
              module.description,
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
        borderRadius: BorderRadius.circular(28),
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
            'La home reste legere: elle montre les signaux utiles, puis renvoie vers les modules pour agir.',
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
          Text(value, style: AppTypography.title.copyWith(color: text)),
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
