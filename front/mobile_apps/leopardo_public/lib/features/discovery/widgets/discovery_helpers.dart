import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/discovery_category.dart';
import '../models/discovery_item.dart';

/// Convertit un `DiscoveryItem` (ou son JSON) en carte `BusinessProfile`.
///
/// ## Pourquoi ce fichier existe
///
/// La fiche reçoit ses arguments de DEUX façons :
///
/// 1. **Depuis la liste** : `context.push('/business/type/slug', extra: item)`
///    → l'item est déjà en mémoire, premier rendu immédiat.
/// 2. **Depuis un lien profond** (QR code, partage) : GoRouter ne reçoit que
///    `type` et `slug`, aucun `extra`. La fiche DOIT alors fonctionner seule.
///
/// Sans ce pont, un `state.extra as DiscoveryItem` planterait sur tout accès
/// direct par URL — c'est-à-dire exactement le cas d'usage principal du couloir
/// (un QR code scanné dans la rue).
DiscoveryItem? discoveryItemFromExtra(Object? extra) {
  if (extra is DiscoveryItem) return extra;
  if (extra is Map) return DiscoveryItem.tryParse(extra);
  return null;
}

/// Affiche l'onglet de catégories avec le nombre, sans jamais mentir sur les
/// catégories sous le seuil.
Widget buildCategorySection({
  required BuildContext context,
  required AppStrings strings,
  required AsyncValue<DiscoveryCategories> async,
  required String? selected,
  required ValueChanged<String?> onSelect,
}) {
  return async.when(
    loading: () => const SizedBox(height: 44),
    error: (_, __) => const SizedBox.shrink(),
    data: (categories) {
      if (categories.visible.isEmpty) {
        // Le seuil est connu du serveur : si 0 catégorie passe, on n'affiche
        // rien plutôt qu'un bandeau « Catégories » vide.
        return const SizedBox.shrink();
      }
      return SizedBox(
        height: 44,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: categories.visible.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final category = categories.visible[index];
            final isSelected = category.key == selected;

            return FilterChip(
              label: Text(
                '${category.label} · ${category.count}',
                style: AppTypography.caption.copyWith(
                  color: isSelected
                      ? AppColors.textPrimaryFor(context)
                      : AppColors.textSecondaryFor(context),
                ),
              ),
              selected: isSelected,
              showCheckmark: false,
              onSelected: (_) => onSelect(isSelected ? null : category.key),
            );
          },
        ),
      );
    },
  );
}
