import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran de bienvenue employee — accès direct, zéro friction.
/// Pas de carousel : logo + tagline + 2 CTA principaux visibles d'emblée.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final bg = AppColors.backgroundFor(context);
    final compact = MediaQuery.of(context).size.height < 700;
    final l10n = context.l10n;

    return Scaffold(
      backgroundColor: bg,
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              AppColors.tint(context, AppColors.rh, lightAlpha: 0.12),
              bg,
              AppColors.tint(context, AppColors.ia, lightAlpha: 0.06),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: EdgeInsets.symmetric(
              horizontal: 24,
              vertical: compact ? 16 : 28,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // ── Logo + marque ─────────────────────────────────────────
                _BrandHeader(compact: compact),
                SizedBox(height: compact ? 20 : 36),

                // ── Titre accrocheur ──────────────────────────────────────
                Text(
                  l10n.welcomeHeroTitle,
                  style: AppTypography.display.copyWith(
                    color: AppColors.textPrimaryFor(context),
                    fontSize: compact ? 26 : 32,
                    height: 1.2,
                  ),
                ),
                SizedBox(height: compact ? 8 : 12),
                Text(
                  l10n.welcomeHeroDescription,
                  style: AppTypography.body.copyWith(
                    color: AppColors.textSecondaryFor(context),
                  ),
                ),
                SizedBox(height: compact ? 20 : 32),

                // ── Modules disponibles ───────────────────────────────────
                _ModuleRow(),
                const Spacer(),

                // ── CTA principaux ────────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => context.go('/login'),
                    child: Text(l10n.login),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () => context.go('/register'),
                    child: Text(l10n.employeeInvitationAccess),
                  ),
                ),
                SizedBox(height: compact ? 12 : 16),
                Center(
                  child: TextButton.icon(
                    onPressed: () => context.go('/user-register'),
                    icon: const Icon(Icons.person_add_outlined, size: 16),
                    label: Text(l10n.createPersonalAccount),
                    style: TextButton.styleFrom(
                      foregroundColor: AppColors.ia,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader({required this.compact});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final logoSize = compact ? 52.0 : 64.0;

    return Row(
      children: [
        // Logo circulaire dégradé
        Container(
          width: logoSize,
          height: logoSize,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.rh, AppColors.rhDark],
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.rh.withValues(alpha: 0.30),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Center(
            child: Text(
              'L',
              style: TextStyle(
                fontFamily: AppTypography.fontFamily,
                fontWeight: FontWeight.w800,
                fontSize: compact ? 24 : 30,
                color: Colors.white,
              ),
            ),
          ),
        ),
        const SizedBox(width: 16),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Leopardo RH',
              style: AppTypography.title.copyWith(
                color: text,
                fontSize: compact ? 18 : 20,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              context.l10n.welcomeBrandSubtitle,
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ],
        ),
      ],
    );
  }
}

class _ModuleRow extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final modules = [
      _ModuleTile(
        icon: Icons.fingerprint_rounded,
        label: 'Pointage',
        color: AppColors.rh,
      ),
      _ModuleTile(
        icon: Icons.calendar_month_outlined,
        label: 'Congés',
        color: AppColors.info,
      ),
      _ModuleTile(
        icon: Icons.payments_outlined,
        label: 'Paie',
        color: AppColors.finance,
      ),
      _ModuleTile(
        icon: Icons.auto_awesome_rounded,
        label: 'Leo IA',
        color: AppColors.ia,
      ),
    ];

    return Row(
      children: modules
          .map(
            (m) => Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: m,
              ),
            ),
          )
          .toList(),
    );
  }
}

class _ModuleTile extends StatelessWidget {
  const _ModuleTile({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      decoration: BoxDecoration(
        color: AppColors.tint(context, color, lightAlpha: 0.12, darkAlpha: 0.20),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: color.withValues(alpha: 0.20),
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: AppTypography.caption.copyWith(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 11,
            ),
          ),
        ],
      ),
    );
  }
}
