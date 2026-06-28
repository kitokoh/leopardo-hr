import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran de bienvenue manager — accès direct, zéro friction.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final bg = AppColors.backgroundFor(context);
    final compact = MediaQuery.of(context).size.height < 700;
    final l10n = context.l10n;
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

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
              AppColors.tint(context, AppColors.finance, lightAlpha: 0.05),
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
                Row(
                  children: [
                    Container(
                      width: compact ? 50 : 62,
                      height: compact ? 50 : 62,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [AppColors.rh, AppColors.rhDark],
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.rh.withValues(alpha: 0.28),
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
                          style: AppTypography.title.copyWith(color: text),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          'Espace Manager',
                          style: AppTypography.caption.copyWith(
                            color: AppColors.rh,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                SizedBox(height: compact ? 24 : 36),

                // ── Titre ─────────────────────────────────────────────────
                Text(
                  l10n.welcomeHeroTitle,
                  style: AppTypography.display.copyWith(
                    color: text,
                    fontSize: compact ? 26 : 32,
                    height: 1.2,
                  ),
                ),
                SizedBox(height: compact ? 8 : 12),
                Text(
                  l10n.welcomeHeroDescription,
                  style: AppTypography.body.copyWith(color: muted),
                ),
                SizedBox(height: compact ? 20 : 28),

                // ── Capacités manager ─────────────────────────────────────
                _ManagerCapabilities(context: context),
                const Spacer(),

                // ── CTA principaux ────────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: () => context.go('/login'),
                    child: Text(l10n.login),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: OutlinedButton(
                    onPressed: () => context.go('/register'),
                    child: Text(l10n.employeeInvitationAccess),
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

class _ManagerCapabilities extends StatelessWidget {
  const _ManagerCapabilities({required this.context});

  final BuildContext context;

  @override
  Widget build(BuildContext ctx) {
    final items = [
      _CapItem(
        icon: Icons.people_alt_outlined,
        label: 'Mon équipe',
        color: AppColors.rh,
      ),
      _CapItem(
        icon: Icons.access_time_rounded,
        label: 'Présences',
        color: AppColors.info,
      ),
      _CapItem(
        icon: Icons.task_alt_rounded,
        label: 'Tâches',
        color: AppColors.finance,
      ),
      _CapItem(
        icon: Icons.auto_awesome_rounded,
        label: 'Leo IA',
        color: AppColors.ia,
      ),
    ];

    return Row(
      children: items
          .map(
            (item) => Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    vertical: 14,
                    horizontal: 6,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.tint(
                      ctx,
                      item.color,
                      lightAlpha: 0.12,
                      darkAlpha: 0.20,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: item.color.withValues(alpha: 0.20),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(item.icon, color: item.color, size: 22),
                      const SizedBox(height: 6),
                      Text(
                        item.label,
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppTypography.caption.copyWith(
                          color: item.color,
                          fontWeight: FontWeight.w600,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          )
          .toList(),
    );
  }
}

class _CapItem {
  const _CapItem({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;
}
