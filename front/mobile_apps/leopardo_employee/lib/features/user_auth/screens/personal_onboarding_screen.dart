import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_employee/features/user_auth/providers/user_auth_provider.dart';

class PersonalOnboardingScreen extends ConsumerStatefulWidget {
  const PersonalOnboardingScreen({super.key});

  @override
  ConsumerState<PersonalOnboardingScreen> createState() =>
      _PersonalOnboardingScreenState();
}

class _PersonalOnboardingScreenState
    extends ConsumerState<PersonalOnboardingScreen> {
  final Set<String> _selected = <String>{};

  static const _options = <_StatusOption>[
    _StatusOption(
      key: 'student',
      title: 'Je suis étudiant(e)',
      description: 'Organiser mes diplômes, documents et mon CV.',
      icon: Icons.school_outlined,
      color: AppColors.ia,
    ),
    _StatusOption(
      key: 'employee',
      title: 'Je travaille',
      description: 'Garder mon espace professionnel et rejoindre une entreprise.',
      icon: Icons.badge_outlined,
      color: AppColors.rh,
    ),
    _StatusOption(
      key: 'entrepreneur',
      title: 'Je dirige une entreprise',
      description: 'Créer ou administrer un espace entreprise.',
      icon: Icons.business_outlined,
      color: AppColors.finance,
    ),
    _StatusOption(
      key: 'job_seeker',
      title: 'Je recherche un emploi',
      description: 'Préparer mon profil pour les opportunités à venir.',
      icon: Icons.work_outline,
      color: AppColors.info,
    ),
  ];

  @override
  void initState() {
    super.initState();
    final existing = ref.read(userAuthProvider).user?.personalStatuses ?? const [];
    _selected.addAll(existing);
  }

  Future<void> _continue() async {
    if (_selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sélectionnez au moins une situation.')),
      );
      return;
    }

    final ok = await ref
        .read(userAuthProvider.notifier)
        .savePersonalStatuses(_selected.toList());
    if (!mounted) return;
    if (ok) {
      context.go('/user-home');
    } else {
      final error = ref.read(userAuthProvider).error;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error ?? 'Impossible d’enregistrer votre profil.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(userAuthProvider);
    final bg = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        title: const Text('Votre profil Leopardo'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/user-home'),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
          children: [
            Text(
              'Comment souhaitez-vous utiliser Leopardo ?',
              style: AppTypography.title.copyWith(color: text),
            ),
            const SizedBox(height: 8),
            Text(
              'Vous pouvez sélectionner plusieurs réponses. Ces choix resteront modifiables dans les paramètres.',
              style: AppTypography.body.copyWith(color: muted),
            ),
            const SizedBox(height: 24),
            ..._options.map((option) {
              final selected = _selected.contains(option.key);
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: () => setState(() {
                    if (selected) {
                      _selected.remove(option.key);
                    } else {
                      _selected.add(option.key);
                    }
                  }),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: selected
                          ? option.color.withValues(alpha: 0.14)
                          : AppColors.surfaceFor(context),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: selected
                            ? option.color
                            : AppColors.borderFor(context),
                        width: selected ? 1.5 : 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundColor: option.color.withValues(alpha: 0.16),
                          foregroundColor: option.color,
                          child: Icon(option.icon),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                option.title,
                                style: AppTypography.subtitle.copyWith(
                                  color: text,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                option.description,
                                style: AppTypography.bodySmall.copyWith(color: muted),
                              ),
                            ],
                          ),
                        ),
                        Icon(
                          selected
                              ? Icons.check_circle
                              : Icons.radio_button_unchecked,
                          color: selected ? option.color : muted,
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(
                'Le pointage et les fonctions d’entreprise seront disponibles uniquement après acceptation de votre rattachement par une entreprise.',
                style: AppTypography.bodySmall.copyWith(color: text),
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: state.isLoading ? null : _continue,
                child: state.isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Continuer'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusOption {
  const _StatusOption({
    required this.key,
    required this.title,
    required this.description,
    required this.icon,
    required this.color,
  });

  final String key;
  final String title;
  final String description;
  final IconData icon;
  final Color color;
}
