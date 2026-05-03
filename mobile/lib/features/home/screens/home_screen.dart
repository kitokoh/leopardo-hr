import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/theme/mobile_experience_icons.dart';
import 'package:leopardo_rh/core/widgets/alert_banner.dart';
import 'package:leopardo_rh/core/widgets/leopardo_badge.dart';
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
    final quickActions =
        stage == 'new'
            ? experience.quickActions.take(3).toList()
            : experience.quickActions;
    final activeModules = experience.activeModules;
    final upcomingModules = experience.upcomingModules;
    final firstName =
        employee?.firstName.isNotEmpty == true
            ? employee!.firstName
            : employee?.email.split('@').first ?? '';
    final canManageTeam = employee?.canManageTeam == true;
    final background = AppColors.backgroundFor(context);

    return Scaffold(
      backgroundColor: background,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.08),
              background,
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.04),
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                  children: [
                    _HeaderRow(
                      firstName: firstName,
                      stage: stage,
                      canManageTeam: canManageTeam,
                    ),
                    const SizedBox(height: 18),
                    _LeoCard(
                      firstName: firstName,
                      stage: stage,
                      canManageTeam: canManageTeam,
                    ),
                    const SizedBox(height: 14),
                    _AlertStack(
                      stage: stage,
                      canManageTeam: canManageTeam,
                      upcomingModules: upcomingModules,
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(
                      title: 'Actions rapides',
                      subtitle:
                          stage == 'new'
                              ? 'Leo vous montre l essentiel pour bien commencer.'
                              : 'Vos raccourcis les plus utiles sont regroupes ici.',
                    ),
                    const SizedBox(height: 12),
                    _QuickActionsGrid(actions: quickActions),
                    const SizedBox(height: 24),
                    _SectionTitle(
                      title: 'Modules actifs',
                      subtitle:
                          'Votre entreprise et votre role determinent ce que vous voyez.',
                    ),
                    const SizedBox(height: 12),
                    _ModulesScroller(modules: activeModules),
                    if (upcomingModules.isNotEmpty) ...[
                      const SizedBox(height: 24),
                      const _SectionTitle(
                        title: 'Bientot dans Leopardo',
                        subtitle:
                            'La feuille de route reste visible, sans polluer les actions du jour.',
                      ),
                      const SizedBox(height: 12),
                      _UpcomingModules(modules: upcomingModules),
                    ],
                    if (canManageTeam) ...[
                      const SizedBox(height: 24),
                      const _ManagerDigestCard(),
                    ],
                  ],
                ),
              ),
              _ChatInputBar(stage: stage),
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
        IconButton.filledTonal(
          onPressed: () => context.push('/settings'),
          icon: const Icon(Icons.tune),
          tooltip: 'Parametres',
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
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final dateLabel = DateFormat.EEEE(
      'fr_FR',
    ).add_d().add_MMMM().format(DateTime.now());

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.borderFor(context)),
        boxShadow: [
          BoxShadow(
            color: AppColors.rh.withValues(alpha: 0.06),
            blurRadius: 24,
            offset: const Offset(0, 12),
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
            style: AppTypography.display.copyWith(color: text, fontSize: 30),
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

class _LeoCard extends StatelessWidget {
  const _LeoCard({
    required this.firstName,
    required this.stage,
    required this.canManageTeam,
  });

  final String firstName;
  final String stage;
  final bool canManageTeam;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final shortName = firstName.isEmpty ? 'vous' : firstName;
    final guidance =
        stage == 'new'
            ? 'On commence simple: Leo met en avant quelques actions utiles et laisse l interface s ouvrir progressivement.'
            : 'Leo garde le contexte, puis vous bascule vers la bonne action sans vous perdre dans un dashboard massif.';
    final focus =
        canManageTeam
            ? 'Aujourd hui, gardez l oeil sur le pointage, les validations RH et l activite de votre equipe.'
            : 'Aujourd hui, tout part du pointage, puis de la consultation de votre mois et de vos documents RH.';

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.tint(context, AppColors.ia, lightAlpha: 0.15),
            AppColors.surfaceFor(context),
            AppColors.tint(context, AppColors.rh, lightAlpha: 0.06),
          ],
        ),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
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
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Leo vous ouvre la journee',
                      style: AppTypography.subtitle.copyWith(color: text),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Conversationnelle, mobile-first et guidee.',
                      style: AppTypography.caption.copyWith(color: muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Bonjour $shortName. $focus',
            style: AppTypography.body.copyWith(color: text),
          ),
          const SizedBox(height: 10),
          Text(guidance, style: AppTypography.bodySmall.copyWith(color: muted)),
        ],
      ),
    );
  }
}

class _AlertStack extends StatelessWidget {
  const _AlertStack({
    required this.stage,
    required this.canManageTeam,
    required this.upcomingModules,
  });

  final String stage;
  final bool canManageTeam;
  final List<MobileModule> upcomingModules;

  @override
  Widget build(BuildContext context) {
    final alerts = <Widget>[
      AlertBanner(
        message:
            stage == 'new'
                ? 'Leo garde une home volontairement simple pour vos premiers usages.'
                : 'Votre mobile reste la surface principale: RH, pointage et suivi personnel vivent ici.',
        level: AlertLevel.info,
        icon: Icons.phone_iphone,
      ),
    ];

    if (canManageTeam) {
      alerts.add(
        const Padding(
          padding: EdgeInsets.only(top: 10),
          child: AlertBanner(
            message:
                'Les workflows equipe et invitations restent disponibles sans quitter l experience mobile.',
            level: AlertLevel.success,
            icon: Icons.groups_2_outlined,
          ),
        ),
      );
    }

    if (upcomingModules.isNotEmpty) {
      alerts.add(
        Padding(
          padding: const EdgeInsets.only(top: 10),
          child: AlertBanner(
            message:
                '${upcomingModules.length} modules restent visibles dans la feuille de route, sans se melanger aux actions critiques du jour.',
            level: AlertLevel.warning,
            icon: Icons.upcoming_outlined,
          ),
        ),
      );
    }

    return Column(children: alerts);
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: AppTypography.title.copyWith(color: text)),
        const SizedBox(height: 4),
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
      borderRadius: BorderRadius.circular(24),
      onTap: () => context.push(action.route),
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
    final muted = AppColors.textSecondaryFor(context);

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

class _UpcomingModules extends StatelessWidget {
  const _UpcomingModules({required this.modules});

  final List<MobileModule> modules;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children:
          modules.map((module) => _UpcomingModulePill(module: module)).toList(),
    );
  }
}

class _UpcomingModulePill extends StatelessWidget {
  const _UpcomingModulePill({required this.module});

  final MobileModule module;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(module.domain);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Container(
      width: 170,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: AppColors.tint(
                context,
                color,
                lightAlpha: 0.16,
                darkAlpha: 0.22,
              ),
              shape: BoxShape.circle,
            ),
            child: Icon(
              MobileExperienceIcons.forModule(module.key),
              size: 18,
              color: color,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  module.title,
                  style: AppTypography.bodySmall.copyWith(
                    color: text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  'Bientot disponible',
                  style: AppTypography.caption.copyWith(color: muted),
                ),
              ],
            ),
          ),
        ],
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

class _ChatInputBar extends StatelessWidget {
  const _ChatInputBar({required this.stage});

  final String stage;

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
        border: Border(top: BorderSide(color: AppColors.borderFor(context))),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.tint(
                context,
                AppColors.ia,
                lightAlpha: 0.14,
                darkAlpha: 0.24,
              ),
            ),
            child: const Icon(
              Icons.auto_awesome,
              color: AppColors.ia,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Opacity(
              opacity: 0.82,
              child: TextField(
                enabled: false,
                decoration: InputDecoration(
                  isDense: true,
                  hintText:
                      stage == 'new'
                          ? 'Leo commencera bientot par vous guider pas a pas...'
                          : 'Leo arrive bientot dans cette conversation...',
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
