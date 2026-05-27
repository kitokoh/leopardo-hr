import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/theme/mobile_experience_icons.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/models/mobile_experience.dart';

final managerDigestProvider = FutureProvider.autoDispose<ManagerDigest>((
  ref,
) async {
  final apiClient = ref.watch(apiClientProvider);
  final response = await apiClient.dio.get('/dashboard/manager-digest');
  final raw = response.data;

  if (raw is Map && raw['data'] is Map) {
    return ManagerDigest.fromJson(
      Map<String, dynamic>.from(raw['data'] as Map),
    );
  }

  return const ManagerDigest.empty();
});

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
    final quickActions = experience.quickActions.take(3).toList();
    final activeModules = experience.activeModules.take(4).toList();
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
                    const SizedBox(height: 18),
                    _SectionTitle(
                      title: 'Actions rapides',
                      subtitle:
                          stage == 'new'
                              ? 'Les premiers gestes vraiment utiles.'
                              : 'Vos trois gestes RH du jour.',
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

class ManagerDigest {
  const ManagerDigest({
    required this.teamScope,
    required this.teamSize,
    required this.present,
    required this.late,
    required this.openSessions,
    required this.pendingActions,
    required this.pendingAbsences,
    required this.pendingSalaryAdvances,
    required this.pendingCorrections,
  });

  const ManagerDigest.empty()
    : teamScope = 'company',
      teamSize = 0,
      present = 0,
      late = 0,
      openSessions = 0,
      pendingActions = 0,
      pendingAbsences = 0,
      pendingSalaryAdvances = 0,
      pendingCorrections = 0;

  final String teamScope;
  final int teamSize;
  final int present;
  final int late;
  final int openSessions;
  final int pendingActions;
  final int pendingAbsences;
  final int pendingSalaryAdvances;
  final int pendingCorrections;

  factory ManagerDigest.fromJson(Map<String, dynamic> json) {
    return ManagerDigest(
      teamScope: json['team_scope']?.toString() ?? 'company',
      teamSize: _asInt(json['team_size']),
      present: _asInt(json['present']),
      late: _asInt(json['late']),
      openSessions: _asInt(json['open_sessions']),
      pendingActions: _asInt(json['pending_actions']),
      pendingAbsences: _asInt(json['pending_absences']),
      pendingSalaryAdvances: _asInt(json['pending_salary_advances']),
      pendingCorrections: _asInt(json['pending_corrections']),
    );
  }

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }
}

class _ManagerDigestCard extends ConsumerWidget {
  const _ManagerDigestCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final digest = ref.watch(managerDigestProvider);
    final resolvedDigest = digest.asData?.value;

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
          Row(
            children: [
              Expanded(
                child: Text(
                  'A surveiller aujourd hui',
                  style: AppTypography.subtitle.copyWith(color: text),
                ),
              ),
              IconButton(
                onPressed: () => ref.invalidate(managerDigestProvider),
                icon: const Icon(
                  Icons.refresh,
                  size: 18,
                  color: MobileSurface.secondary,
                ),
                tooltip: 'Actualiser',
              ),
            ],
          ),
          digest.when(
            loading: () => const _ManagerDigestLoading(),
            error:
                (error, _) => _ManagerDigestError(
                  onRetry: () => ref.invalidate(managerDigestProvider),
                ),
            data: (data) => _ManagerDigestContent(data: data),
          ),
          const SizedBox(height: 14),
          Text(
            'Scope ${resolvedDigest?.teamScope == 'managed_team' ? 'equipe directe' : 'entreprise'} - ${resolvedDigest?.teamSize ?? 0} profils actifs.',
            style: AppTypography.bodySmall.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _ManagerDigestContent extends StatelessWidget {
  const _ManagerDigestContent({required this.data});

  final ManagerDigest data;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: _DigestTile(
                color: AppColors.success,
                value: data.present.toString(),
                label: 'presents',
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _DigestTile(
                color: data.late > 0 ? AppColors.warning : AppColors.success,
                value: data.late.toString(),
                label: 'retards',
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _DigestTile(
                color:
                    data.pendingActions > 0
                        ? AppColors.info
                        : AppColors.success,
                value: data.pendingActions.toString(),
                label: 'actions RH',
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _DigestActionButton(
                icon: Icons.group_outlined,
                label: 'Presences',
                onTap: () => context.push('/manager/attendance'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _DigestActionButton(
                icon: Icons.warning_amber_rounded,
                label: 'Anomalies',
                onTap: () => context.push('/manager/anomalies'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _DigestActionButton(
                icon: Icons.fact_check_outlined,
                label: 'Corrections',
                onTap: () => context.push('/manager/corrections'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: _DigestActionButton(
                icon: Icons.schedule_outlined,
                label: 'Horaires',
                onTap: () => context.push('/schedules'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _DigestActionButton(
                icon: Icons.add_task_rounded,
                label: 'Taches',
                onTap: () => context.push('/tasks'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _DigestActionButton(
                icon: Icons.people_alt_outlined,
                label: 'Equipe',
                onTap: () => context.push('/team'),
              ),
            ),
          ],
        ),
        if (data.openSessions > 0) ...[
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(
                Icons.access_time_filled,
                size: 15,
                color: AppColors.warning,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  '${data.openSessions} session(s) encore ouvertes aujourd hui.',
                  style: AppTypography.caption.copyWith(
                    color: MobileSurface.secondary,
                  ),
                ),
              ),
            ],
          ),
        ],
        if (data.pendingCorrections > 0) ...[
          const SizedBox(height: 8),
          InkWell(
            onTap: () => context.push('/manager/corrections'),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  const Icon(
                    Icons.edit_calendar_outlined,
                    size: 15,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '${data.pendingCorrections} correction(s) de pointage attendent une decision.',
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _ManagerDigestLoading extends StatelessWidget {
  const _ManagerDigestLoading();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(999),
        child: LinearProgressIndicator(
          minHeight: 4,
          backgroundColor: MobileSurface.chip,
          valueColor: AlwaysStoppedAnimation<Color>(
            AppColors.rh.withValues(alpha: 0.9),
          ),
        ),
      ),
    );
  }
}

class _ManagerDigestError extends StatelessWidget {
  const _ManagerDigestError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        children: [
          const Icon(Icons.cloud_off, size: 16, color: AppColors.warning),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Signal equipe indisponible. Reessayez dans un instant.',
              style: AppTypography.caption.copyWith(color: MobileSurface.muted),
            ),
          ),
          TextButton(onPressed: onRetry, child: const Text('Reessayer')),
        ],
      ),
    );
  }
}

class _DigestActionButton extends StatelessWidget {
  const _DigestActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Ink(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: MobileSurface.chip,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: MobileSurface.border),
        ),
        child: Column(
          children: [
            Icon(icon, size: 16, color: AppColors.rh),
            const SizedBox(height: 4),
            Text(
              label,
              style: AppTypography.caption.copyWith(color: MobileSurface.text),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
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
