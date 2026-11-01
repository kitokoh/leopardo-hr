import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/business_profile.dart';

/// Carte du menu (restauration uniquement).
///
/// ## Repli quand `price` est absent
///
/// Un `BusinessMenuItem` sans prix n'affiche PAS « 0,00 » : il n'affiche rien à
/// droite. Un prix inventé est une promesse commerciale fausse ; l'absence de
/// prix peut simplement signifier « selon le marché » ou « voir sur place ».
///
/// ## Devise
///
/// La devise vient de `profile.currency`. Sans elle, on affiche le montant sans
/// symbole plutôt qu'un « $ » supposé — l'app est destinée à plusieurs pays.
class BusinessMenuCard extends StatelessWidget {
  const BusinessMenuCard({
    super.key,
    required this.strings,
    required this.sections,
    this.currency,
  });

  final AppStrings strings;
  final List<BusinessMenuSection> sections;
  final String? currency;

  @override
  Widget build(BuildContext context) {
    final visibleSections =
        sections.where((s) => s.items.isNotEmpty).toList(growable: false);

    if (visibleSections.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          strings.t('businessMenu'),
          style: AppTypography.title.copyWith(fontSize: 15),
        ),
        const SizedBox(height: 8),
        for (final section in visibleSections) ...[
          Padding(
            padding: const EdgeInsets.only(top: 6, bottom: 4),
            child: Text(
              section.name,
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
                fontWeight: FontWeight.w600,
                letterSpacing: 0.4,
              ),
            ),
          ),
          for (final item in section.items)
            _MenuRow(item: item, currency: currency),
        ],
      ],
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.item, this.currency});

  final BusinessMenuItem item;
  final String? currency;

  @override
  Widget build(BuildContext context) {
    final price = item.price == null
        ? null
        : (currency == null
            ? item.price!.toStringAsFixed(2)
            : '${item.price!.toStringAsFixed(2)} $currency');

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: AppTypography.bodySmall.copyWith(
                    color: AppColors.textPrimaryFor(context),
                  ),
                ),
                if (item.description != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      item.description!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.caption.copyWith(
                        color: AppColors.textSecondaryFor(context),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (price != null) ...[
            const SizedBox(width: 12),
            Text(
              price,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textPrimaryFor(context),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
