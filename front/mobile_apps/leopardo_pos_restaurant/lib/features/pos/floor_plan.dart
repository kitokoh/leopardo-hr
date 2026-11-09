import 'package:flutter/material.dart';

import '../../core/models/pos_models.dart';
import '../../core/theme/pos_theme.dart';

/// **Plan de salle** du POS Restaurant (#9214).
///
/// Un serveur ne choisit pas une table dans une liste deroulante : il la
/// designe sur le plan de salle, comme il la voit depuis le comptoir. Ce
/// widget affiche les tables en grille, avec leur etat (libre / occupee) et
/// leur nombre de places, et renvoie la table touchee.
///
/// Regles d'usage reel :
///  - **cible large** : une table se touche d'un doigt, debout, sans viser ;
///  - **etat visible** : une table occupee se distingue au premier regard ;
///  - **capacite lisible** : le serveur sait si la table peut asseoir 4 ou 8 ;
///  - **selection claire** : la table choisie est entouree, pas seulement
///    teintee (lisible en plein jour sur un ecran incline).
class FloorPlan extends StatelessWidget {
  const FloorPlan({
    super.key,
    required this.tables,
    required this.selectedId,
    required this.onSelect,
    this.covers = 1,
  });

  final List<PosTable> tables;
  final int? selectedId;
  final ValueChanged<int> onSelect;

  /// Nombre de couverts de la commande en cours : sert a signaler les tables
  /// trop petites pour le groupe.
  final int covers;

  @override
  Widget build(BuildContext context) {
    if (tables.isEmpty) {
      return const _FloorPlanEmpty();
    }

    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 150,
        mainAxisExtent: 110,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
      ),
      itemCount: tables.length,
      itemBuilder: (context, index) {
        final table = tables[index];

        return _TableTile(
          table: table,
          selected: table.id == selectedId,
          tooSmall: table.seats < covers,
          onTap: () => onSelect(table.id),
        );
      },
    );
  }
}

/// Une table du plan de salle.
class _TableTile extends StatelessWidget {
  const _TableTile({
    required this.table,
    required this.selected,
    required this.tooSmall,
    required this.onTap,
  });

  final PosTable table;
  final bool selected;
  final bool tooSmall;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    final background = selected
        ? scheme.primaryContainer
        : (table.occupied ? scheme.errorContainer : scheme.surfaceContainerHighest);
    final foreground = selected
        ? scheme.onPrimaryContainer
        : (table.occupied ? scheme.onErrorContainer : scheme.onSurface);

    return Semantics(
      button: true,
      selected: selected,
      label: '${table.label}, ${table.seats} places, '
          '${table.occupied ? 'occupee' : 'libre'}',
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(PosTheme.cardRadius),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(PosTheme.cardRadius),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(PosTheme.cardRadius),
              border: Border.all(
                color: selected ? scheme.primary : Colors.transparent,
                width: 3,
              ),
            ),
            padding: const EdgeInsets.all(10),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  table.occupied ? Icons.people : Icons.table_restaurant,
                  size: 26,
                  color: foreground,
                ),
                const SizedBox(height: 6),
                Text(
                  table.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: foreground,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.chair_outlined, size: 14, color: foreground),
                    const SizedBox(width: 4),
                    Text(
                      '${table.seats}',
                      style: TextStyle(fontSize: 13, color: foreground),
                    ),
                    if (tooSmall) ...[
                      const SizedBox(width: 6),
                      Icon(Icons.warning_amber_rounded, size: 14, color: scheme.error),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Plan de salle vide : dit quoi faire plutot que d'afficher une grille nue.
class _FloorPlanEmpty extends StatelessWidget {
  const _FloorPlanEmpty();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.table_restaurant_outlined, size: 44, color: scheme.outline),
            const SizedBox(height: 12),
            Text(
              'Aucune table configuree',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w700,
                color: scheme.onSurface,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Ajoutez des tables au plan de salle pour encaisser une commande sur place.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 15, color: scheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}
