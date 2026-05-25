import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// APV Design v3 — EmptyState.
///
/// A utiliser chaque fois qu'une liste est vide, pour eviter un ecran blanc.
/// Tone : bienveillant, conversationnel (pas "Aucune donnee" mais
/// "Rien ici pour le moment, commencez par...").
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.title,
    this.description,
    this.icon,
    this.action,
  });

  final String title;
  final String? description;
  final IconData? icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Semantics(
          label: description != null ? '$title. $description' : title,
          container: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              ExcludeSemantics(
                child: Icon(
                  icon ?? Icons.inbox_outlined,
                  size: 56,
                  color: muted,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                title,
                style: AppTypography.title.copyWith(color: muted),
                textAlign: TextAlign.center,
              ),
              if (description != null) ...[
                const SizedBox(height: 8),
                Text(
                  description!,
                  style: AppTypography.bodySmall.copyWith(color: muted),
                  textAlign: TextAlign.center,
                ),
              ],
              if (action != null) ...[const SizedBox(height: 16), action!],
            ],
          ),
        ),
      ),
    );
  }
}
