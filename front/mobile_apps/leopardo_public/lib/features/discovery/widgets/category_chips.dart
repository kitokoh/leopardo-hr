import 'package:flutter/material.dart';

import '../../../core/i18n/app_strings.dart';
import '../models/discovery_category.dart';
import 'search_field.dart';

/// Rangée de catégories DYNAMIQUES de l'accueil.
///
/// ## Ce que « dynamique » signifie exactement
///
/// Une catégorie n'apparaît que si la ville compte au moins N pages publiées
/// dedans, N venant de `platform_settings.discovery.category_min_pages` (réglage
/// Superadmin, `PlatformSettingsRepository`). Le serveur renvoie les catégories
/// sous le seuil avec `visible: false` : cette rangée ne montre QUE les
/// visibles.
///
/// **Aucune catégorie codée en dur ici.** Une puce « Pharmacie » ajoutée dans
/// l'app pour « faire complet » produirait une recherche vide dans toutes les
/// villes où le module n'existe pas — le bouton mort que tout le couloir
/// interdit.
///
/// ## Quand la rangée disparaît
///
/// Si aucune catégorie n'atteint le seuil, la rangée n'occupe pas d'espace
/// (l'appelant rend un `SizedBox.shrink`). Un bandeau vide « Catégories » avec
/// zéro puce ferait croire à un chargement bloqué.
class CategoryChips extends StatelessWidget {
  const CategoryChips({
    super.key,
    required this.strings,
    required this.categories,
    required this.selected,
    required this.onSelect,
  });

  final AppStrings strings;
  final List<DiscoveryCategory> categories;
  final String? selected;
  final ValueChanged<String?> onSelect;

  @override
  Widget build(BuildContext context) {
    if (categories.isEmpty) return const SizedBox.shrink();

    // Délègue au widget de liste partagé : une seule implémentation de la
    // puce, pour que les deux chemins (Accueil et écran d'une ville) ne
    // divergent jamais visuellement.
    return CategoryChipsList(
      strings: strings,
      categories: [
        for (final category in categories)
          (key: category.key, label: category.label, count: category.count),
      ],
      selected: selected,
      onSelect: onSelect,
    );
  }
}
