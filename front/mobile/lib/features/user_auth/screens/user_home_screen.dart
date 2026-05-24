import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/features/user_auth/providers/user_auth_provider.dart';

class UserHomeScreen extends ConsumerWidget {
  const UserHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(userAuthProvider);
    final user = state.user;
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      backgroundColor: bg,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            HapticFeedback.lightImpact();
            await ref.read(userAuthProvider.notifier).checkAuth();
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            children: [
              _buildHeader(
                context,
                user.fullName,
                user.email,
                user.avatarUrl,
                text,
                muted,
                ref,
              ),
              const SizedBox(height: 24),
              _buildQuickActions(context, muted),
              const SizedBox(height: 24),
              if (user.employeeLinks.isNotEmpty) ...[
                _buildSection('Mes entreprises', muted),
                const SizedBox(height: 8),
                ...user.employeeLinks.map(
                  (link) => _EmployeeLinkCard(
                    companyName: link.companyName ?? 'Entreprise',
                    onTap: () {
                      HapticFeedback.lightImpact();
                    },
                  ),
                ),
                const SizedBox(height: 24),
              ],
              if (user.companyRequests.isNotEmpty) ...[
                _buildSection('Demandes en cours', muted),
                const SizedBox(height: 8),
                ...user.companyRequests.map(
                  (req) => _CompanyRequestCard(
                    companyName: req.companyName,
                    status: req.status,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(
    BuildContext context,
    String name,
    String email,
    String? avatarUrl,
    Color text,
    Color muted,
    WidgetRef ref,
  ) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: AppColors.surfaceFor(context),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppColors.borderFor(context)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: AppColors.rh,
            backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
            child:
                avatarUrl == null
                    ? Text(
                      name.isNotEmpty ? name[0].toUpperCase() : '?',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w600,
                      ),
                    )
                    : null,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Bonjour, $name',
                  style: AppTypography.title.copyWith(color: text),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  email,
                  style: AppTypography.caption.copyWith(color: muted),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout_outlined),
            tooltip: 'Deconnexion',
            onPressed: () async {
              HapticFeedback.mediumImpact();
              await ref.read(userAuthProvider.notifier).logout();
              if (context.mounted) context.go('/welcome');
            },
          ),
        ],
      ),
    ).animate().fadeIn(duration: 400.ms).slideY(begin: -0.05);
  }

  Widget _buildQuickActions(BuildContext context, Color muted) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSection('Mon espace', muted),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _QuickActionCard(
                icon: Icons.door_sliding_outlined,
                label: 'Placard',
                color: AppColors.cabinet,
                onTap: () {
                  HapticFeedback.lightImpact();
                  context.push('/cabinet');
                },
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _QuickActionCard(
                icon: Icons.business_outlined,
                label: 'Creer entreprise',
                color: AppColors.ia,
                onTap: () {
                  HapticFeedback.lightImpact();
                  context.push('/company-request');
                },
              ),
            ),
          ],
        ),
      ],
    ).animate().fadeIn(delay: 200.ms, duration: 400.ms);
  }

  Widget _buildSection(String title, Color muted) {
    return Text(title, style: AppTypography.subtitle.copyWith(color: muted));
  }
}

class _QuickActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickActionCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surfaceFor(context),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.borderFor(context)),
          ),
          child: Column(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(height: 10),
              Text(
                label,
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textPrimaryFor(context),
                  fontWeight: FontWeight.w600,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmployeeLinkCard extends StatelessWidget {
  final String companyName;
  final VoidCallback onTap;

  const _EmployeeLinkCard({required this.companyName, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: AppColors.rh.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(Icons.business, color: AppColors.rh, size: 20),
        ),
        title: Text(companyName, style: AppTypography.subtitle),
        subtitle: Text(
          'Espace employe actif',
          style: AppTypography.caption.copyWith(
            color: AppColors.textSecondaryFor(context),
          ),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}

class _CompanyRequestCard extends StatelessWidget {
  final String companyName;
  final String status;

  const _CompanyRequestCard({required this.companyName, required this.status});

  @override
  Widget build(BuildContext context) {
    final statusColor = switch (status) {
      'approved' => AppColors.success,
      'rejected' => AppColors.danger,
      _ => AppColors.warning,
    };
    final statusLabel = switch (status) {
      'approved' => 'Approuve',
      'rejected' => 'Refuse',
      _ => 'En attente',
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: statusColor.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(Icons.pending_actions, color: statusColor, size: 20),
        ),
        title: Text(companyName, style: AppTypography.subtitle),
        subtitle: Text(
          statusLabel,
          style: AppTypography.caption.copyWith(color: statusColor),
        ),
      ),
    );
  }
}
