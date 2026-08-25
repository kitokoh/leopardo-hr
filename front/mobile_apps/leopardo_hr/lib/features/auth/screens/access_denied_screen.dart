import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran « accès refusé » — T116 (QA omnichannel 2026-08-15).
///
/// Un utilisateur authentifié mais sans le rôle de cette app (non-manager dans
/// l'app Manager) est redirigé ici au lieu de boucler `/welcome` ↔ `/`.
class AccessDeniedScreen extends ConsumerWidget {
  const AccessDeniedScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: colors.surface,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.lock_outline, size: 64, color: AppColors.rh),
                const SizedBox(height: 16),
                Text(
                  context.l10n.accessDeniedTitle,
                  style: AppTypography.title,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  context.l10n.accessDeniedBodyHr,
                  style: AppTypography.body,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: () async {
                    await ref.read(authProvider.notifier).logout();
                    if (context.mounted) context.go('/welcome');
                  },
                  icon: const Icon(Icons.logout),
                  label: Text(context.l10n.accessDeniedLogout),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
