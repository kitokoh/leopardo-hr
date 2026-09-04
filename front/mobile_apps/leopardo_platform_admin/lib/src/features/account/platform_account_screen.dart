import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../auth/platform_auth_controller.dart';

class PlatformAccountScreen extends ConsumerWidget {
  const PlatformAccountScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(platformAuthControllerProvider);
    final user = auth.user;

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Mon compte',
        subtitle: user?.email ?? 'Super-admin plateforme',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        MobilePanel(
          child: Row(
            children: [
              const MobileIconBubble(
                icon: Icons.admin_panel_settings_rounded,
                color: AppColors.rh,
                size: 54,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user?.name ?? 'Super administrateur',
                      style: const TextStyle(
                        color: MobileSurface.text,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user?.email ?? 'Session plateforme',
                      style: const TextStyle(
                        color: MobileSurface.secondary,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Securite'),
        MobileListCard(
          icon:
              user?.twoFactorEnabled == true
                  ? Icons.verified_user_rounded
                  : Icons.security_update_warning_rounded,
          iconColor:
              user?.twoFactorEnabled == true
                  ? AppColors.success
                  : AppColors.warning,
          title:
              user?.twoFactorEnabled == true
                  ? 'Double authentification active'
                  : 'Double authentification inactive',
          subtitle:
              user?.twoFactorEnabled == true
                  ? 'Le compte plateforme est protege par un second facteur.'
                  : 'A activer avant exploitation commerciale large.',
        ),
        MobileListCard(
          icon: Icons.key_rounded,
          iconColor: AppColors.info,
          title: 'Session API plateforme',
          subtitle:
              'Token Sanctum super-admin utilise uniquement pour les routes /platform.',
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Perimetre'),
        MobileListCard(
          icon: Icons.business_center_rounded,
          iconColor: AppColors.rh,
          title: 'Administration clients',
          subtitle: 'Entreprises, plans, modules, health et demandes clients.',
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push('/platform/companies'),
        ),
        MobileListCard(
          icon: Icons.block_rounded,
          iconColor: AppColors.warning,
          title: 'Pas de donnees tenant directes',
          subtitle:
              'Cette app ne doit pas consommer pointage, absences ou tokens push tenant.',
        ),
        const SizedBox(height: 18),
        const MobileSectionLabel('Actions'),
        MobileListCard(
          icon: Icons.dashboard_customize_rounded,
          iconColor: AppColors.info,
          title: 'Retour cockpit',
          subtitle: 'Revenir a la vue executive de la plateforme.',
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go('/platform'),
        ),
        MobileListCard(
          icon: Icons.logout_rounded,
          iconColor: AppColors.danger,
          title: 'Deconnexion',
          subtitle: 'Fermer la session super-admin sur cet appareil.',
          onTap:
              () => ref.read(platformAuthControllerProvider.notifier).logout(),
        ),
      ],
    );
  }
}
