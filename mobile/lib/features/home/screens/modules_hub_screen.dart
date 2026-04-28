import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';

class ModulesHubScreen extends StatelessWidget {
  const ModulesHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final modules = [
      _ModuleItem(
        title: 'Absences',
        icon: Icons.calendar_today,
        color: AppColors.rh,
        onTap: () => context.push('/absences'),
      ),
      _ModuleItem(
        title: 'Avances',
        icon: Icons.payments,
        color: AppColors.finance,
        onTap: () => context.push('/salary-advances'),
      ),
      _ModuleItem(
        title: 'Fiches de paie',
        icon: Icons.description,
        color: AppColors.finance,
        onTap: () => context.push('/payrolls'),
      ),
      _ModuleItem(
        title: 'Évaluations',
        icon: Icons.assignment_turned_in,
        color: AppColors.ia,
        onTap: () => context.push('/evaluations'),
      ),
      _ModuleItem(
        title: 'Notifications',
        icon: Icons.notifications,
        color: AppColors.info,
        onTap: () => context.push('/notifications'),
      ),
      _ModuleItem(
        title: 'Projets & Tâches',
        icon: Icons.assignment,
        color: AppColors.ia,
        onTap: () {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Module Projets & Tâches bientôt disponible'),
            ),
          );
        },
      ),
    ];

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Modules RH',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: GridView.builder(
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
            childAspectRatio: 1.1,
          ),
          itemCount: modules.length,
          itemBuilder: (context, index) {
            final module = modules[index];
            return _ModuleCard(module: module);
          },
        ),
      ),
    );
  }
}

class _ModuleItem {
  final String title;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  _ModuleItem({
    required this.title,
    required this.icon,
    required this.color,
    required this.onTap,
  });
}

class _ModuleCard extends StatelessWidget {
  final _ModuleItem module;

  const _ModuleCard({required this.module});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.cardDark,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: module.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: module.color.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(module.icon, color: module.color, size: 32),
              ),
              const SizedBox(height: 12),
              Text(
                module.title,
                style: AppTypography.subtitle.copyWith(
                  color: AppColors.textDark,
                  fontSize: 14,
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
