import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/theme/mobile_experience_icons.dart';
import 'package:leopardo_rh/core/widgets/mobile_surface.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/models/mobile_experience.dart';

class ModulesHubScreen extends ConsumerWidget {
  const ModulesHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final employee = ref.watch(authProvider).employee;
    final experience = employee?.mobileExperience;
    final activeModules = experience?.activeModules ?? const <MobileModule>[];
    final upcomingModules =
        experience?.upcomingModules ?? const <MobileModule>[];
    const text = MobileSurface.text;
    const muted = MobileSurface.secondary;
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      appBar: MobileTopBar(
        title: 'Modules RH',
        subtitle: 'Vos outils actifs',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.08),
              background,
            ],
          ),
        ),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: MobileSurface.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: MobileSurface.border, width: 0.7),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Experience modulaire',
                    style: AppTypography.title.copyWith(color: text),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Leopardo RH ouvre d abord les modules utiles a votre role, puis garde la feuille de route visible sans brouiller l usage quotidien.',
                    style: AppTypography.bodySmall.copyWith(color: muted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Modules actifs',
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 6),
            Text(
              'Disponibles maintenant pour votre entreprise et votre role.',
              style: AppTypography.bodySmall.copyWith(color: muted),
            ),
            const SizedBox(height: 14),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 14,
                mainAxisSpacing: 14,
                childAspectRatio: 0.92,
              ),
              itemCount: activeModules.length,
              itemBuilder: (context, index) {
                final module = activeModules[index];
                return _ModuleCard(
                  module: module,
                  onTap:
                      module.isActive
                          ? () => context.push(module.route!)
                          : null,
                );
              },
            ),
            if (upcomingModules.isNotEmpty) ...[
              const SizedBox(height: 26),
              Text(
                'Roadmap visible',
                style: AppTypography.title.copyWith(color: text),
              ),
              const SizedBox(height: 6),
              Text(
                'Ces modules restent dans la vision produit, sans casser la priorite MVP actuelle.',
                style: AppTypography.bodySmall.copyWith(color: muted),
              ),
              const SizedBox(height: 14),
              ...upcomingModules.map(
                (module) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _UpcomingRow(module: module),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module, required this.onTap});

  final MobileModule module;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(module.domain);
    const text = MobileSurface.text;
    const muted = MobileSurface.secondary;

    return Material(
      color: MobileSurface.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: MobileSurface.border, width: 0.7),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: AppColors.tint(
                    context,
                    color,
                    lightAlpha: 0.16,
                    darkAlpha: 0.24,
                  ),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  MobileExperienceIcons.forModule(module.key),
                  color: color,
                ),
              ),
              const Spacer(),
              Text(
                module.title,
                style: AppTypography.subtitle.copyWith(color: text),
              ),
              const SizedBox(height: 6),
              Text(
                module.description,
                style: AppTypography.bodySmall.copyWith(color: muted),
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _UpcomingRow extends StatelessWidget {
  const _UpcomingRow({required this.module});

  final MobileModule module;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.forDomain(module.domain);
    const text = MobileSurface.text;
    const muted = MobileSurface.secondary;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MobileSurface.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: MobileSurface.border, width: 0.7),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: AppColors.tint(
                context,
                color,
                lightAlpha: 0.16,
                darkAlpha: 0.24,
              ),
              shape: BoxShape.circle,
            ),
            child: Icon(
              MobileExperienceIcons.forModule(module.key),
              color: color,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  module.title,
                  style: AppTypography.subtitle.copyWith(color: text),
                ),
                const SizedBox(height: 4),
                Text(
                  module.description,
                  style: AppTypography.bodySmall.copyWith(color: muted),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text('Bientot', style: AppTypography.caption.copyWith(color: color)),
        ],
      ),
    );
  }
}
