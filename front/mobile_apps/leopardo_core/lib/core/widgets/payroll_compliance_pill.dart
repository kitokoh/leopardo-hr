import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/payroll.dart';

/// Issue #2143 — indicateur discret du niveau de conformité paie
/// (production/pilot/placeholder/unknown) : niveau localisé via les clés ARB
/// `payrollConfidence*` (jamais de chaîne hardcodée), message d'avertissement
/// du contrat en sous-texte avec source + date de vérification experte.
///
/// Rétro-compatible : le parent n'affiche ce widget QUE si le payload expose
/// le bloc `compliance` (absent → aucun affichage, aucune erreur).
class PayrollCompliancePill extends StatelessWidget {
  const PayrollCompliancePill({
    super.key,
    required this.compliance,
    this.countryCode,
  });

  final PayrollCompliance compliance;
  final String? countryCode;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final level = compliance.level;

    final String label;
    final String message;
    final Color color;
    final IconData icon;
    switch (level) {
      case 'production':
        label = l10n.payrollConfidenceLevelProduction;
        message = l10n.payrollConfidenceProductionMessage(countryCode ?? '');
        color = AppColors.success;
        icon = Icons.verified_outlined;
      case 'placeholder':
        label = l10n.payrollConfidenceLevelPlaceholder;
        message = l10n.payrollConfidencePlaceholderMessage(countryCode ?? '');
        color = AppColors.danger;
        icon = Icons.warning_amber_rounded;
      case 'unknown':
        label = l10n.payrollConfidenceLevelUnknown;
        message = l10n.payrollConfidenceUnknownMessage(countryCode ?? '');
        color = AppColors.textMuted;
        icon = Icons.help_outline;
      case 'pilot':
      default:
        label = l10n.payrollConfidenceLevelPilot;
        message = l10n.payrollConfidencePilotMessage(countryCode ?? '');
        color = AppColors.warning;
        icon = Icons.shield_outlined;
    }

    // Les accesseurs ARB interpolent déjà `{country}` (contrat #1872) — le
    // pays vient du payload (`country_code`) ; absent → chaîne vide.
    final detail = [
      if (compliance.source != null && compliance.source!.isNotEmpty)
        compliance.source,
      if (compliance.verificationDate != null &&
          compliance.verificationDate!.isNotEmpty)
        compliance.verificationDate,
    ].join(' · ');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.30), width: 0.7),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  style: AppTypography.caption.copyWith(
                    color: color,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.caption.copyWith(
                    color: AppColors.textMuted,
                    fontSize: 10,
                  ),
                ),
                if (detail.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    detail,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTypography.caption.copyWith(
                      color: AppColors.textMuted,
                      fontSize: 9,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
