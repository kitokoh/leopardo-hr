import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';

/// Champ de recherche de l'accueil.
///
/// ## Deux détails qui changent l'usage réel
///
/// - **`TextInputAction.search`** : le clavier affiche « Rechercher », pas
///   « OK » — un testeur novice ne cherche pas une touche « OK ».
/// - **Bouton d'effacement** conditionnel : présent dès un caractère, il remet
///   la liste complète sans effacer à la main ni changer de ville.
///
/// Le champ ne déclenche PAS la recherche à chaque frappe : il remonte au
/// contrôleur, qui applique un anti-rebond (350 ms). Voir
/// `DiscoveryController.search`.
class DiscoverySearchField extends StatefulWidget {
  const DiscoverySearchField({
    super.key,
    required this.strings,
    required this.onChanged,
    required this.onSubmitted,
    this.initialQuery = '',
  });

  final AppStrings strings;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final String initialQuery;

  @override
  State<DiscoverySearchField> createState() => _DiscoverySearchFieldState();
}

class _DiscoverySearchFieldState extends State<DiscoverySearchField> {
  late final TextEditingController _controller =
      TextEditingController(text: widget.initialQuery);

  @override
  void didUpdateWidget(covariant DiscoverySearchField oldWidget) {
    super.didUpdateWidget(oldWidget);
    // La recherche peut être vidée par un autre chemin (bouton « élargir »).
    if (widget.initialQuery != oldWidget.initialQuery &&
        widget.initialQuery != _controller.text) {
      _controller.text = widget.initialQuery;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: _controller,
      builder: (context, value, _) {
        return TextField(
          controller: _controller,
          textInputAction: TextInputAction.search,
          onChanged: widget.onChanged,
          onSubmitted: widget.onSubmitted,
          decoration: InputDecoration(
            hintText: widget.strings.t('discoverySearchHint'),
            prefixIcon: Icon(
              Icons.search,
              color: AppColors.textSecondaryFor(context),
            ),
            suffixIcon: value.text.isEmpty
                ? null
                : IconButton(
                    tooltip: widget.strings.t('commonClose'),
                    icon: const Icon(Icons.clear, size: 20),
                    onPressed: () {
                      _controller.clear();
                      widget.onChanged('');
                      widget.onSubmitted('');
                    },
                  ),
            filled: true,
            fillColor: AppColors.surfaceFor(context),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: AppColors.borderFor(context)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(color: AppColors.borderFor(context)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.info, width: 1.5),
            ),
          ),
        );
      },
    );
  }
}

/// Rangée de catégories — DYNAMIQUES, jamais codées en dur.
///
/// Le libellé vient de `label` (le serveur renvoie aujourd'hui la clé, faute de
/// traduction : c'est un manque documenté du contrat, pas une invention de
/// l'app). L'écran n'ajoute donc pas de table de traduction locale qui
/// divergerait du serveur au premier ajout de catégorie.
class CategoryChipsList extends StatelessWidget {
  const CategoryChipsList({
    super.key,
    required this.strings,
    required this.categories,
    required this.selected,
    required this.onSelect,
  });

  final AppStrings strings;
  final List<({String key, String label, int count})> categories;
  final String? selected;
  final ValueChanged<String?> onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final category = categories[index];
          final isSelected = category.key == selected;

          return Semantics(
            button: true,
            selected: isSelected,
            label: '${category.label} (${category.count})',
            child: FilterChip(
              label: Text(
                category.label,
                style: AppTypography.caption.copyWith(
                  color: isSelected
                      ? AppColors.textPrimaryFor(context)
                      : AppColors.textSecondaryFor(context),
                ),
              ),
              selected: isSelected,
              onSelected: (_) => onSelect(category.key),
              showCheckmark: false,
            ),
          );
        },
      ),
    );
  }
}
