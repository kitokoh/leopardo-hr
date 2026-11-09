import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/pos_models.dart';
import '../../core/theme/pos_theme.dart';
import 'pos_controller.dart';

/// Écran principal de la caisse tablette Restaurant (#9214).
///
/// Disposition **paysage** en trois colonnes, pensée pour une tablette posée
/// sur un comptoir :
///  - gauche : catégories du menu ;
///  - centre : grille de produits (cibles larges) ;
///  - droite : panier, type de commande, table, couverts, encaissement.
///
/// Le bandeau supérieur dit toujours l'état réel : en ligne, hors ligne, ou
/// N opérations en attente. Une caisse qui ment sur son état est pire qu'une
/// caisse qui refuse de fonctionner.
class PosScreen extends ConsumerStatefulWidget {
  const PosScreen({super.key});

  @override
  ConsumerState<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends ConsumerState<PosScreen> {
  int? _selectedCategoryId;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(posControllerProvider);
    final controller = ref.read(posControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Caisse — Restaurant'),
        actions: [
          _StatusChip(state: state),
          const SizedBox(width: 12),
          if (state.pendingOperations > 0)
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: OutlinedButton.icon(
                onPressed: controller.flushQueue,
                icon: const Icon(Icons.sync),
                label: Text('Synchroniser (${state.pendingOperations})'),
              ),
            ),
        ],
      ),
      body: state.loading
          ? const Center(child: CircularProgressIndicator())
          : Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _CategoryRail(
                  categories: state.categories,
                  selectedId: _selectedCategoryId,
                  onSelect: (id) => setState(() => _selectedCategoryId = id),
                ),
                const VerticalDivider(width: 1),
                Expanded(
                  flex: 3,
                  child: _ProductGrid(
                    products: state.productsFor(_selectedCategoryId),
                    onTap: controller.addProduct,
                  ),
                ),
                const VerticalDivider(width: 1),
                SizedBox(
                  width: 380,
                  child: _CartPanel(
                    state: state,
                    onRemove: controller.removeProduct,
                    onOrderType: controller.setOrderType,
                    onTable: controller.selectTable,
                    onCovers: controller.setCovers,
                    onClear: controller.clearCart,
                    onPay: (method) => _pay(context, controller, method),
                  ),
                ),
              ],
            ),
    );
  }

  Future<void> _pay(BuildContext context, PosController controller, String method) async {
    final ok = await controller.submitAndPay(method: method);

    if (!context.mounted) {
      return;
    }

    final state = ref.read(posControllerProvider);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? (state.offline
                  ? 'Encaissement mis en file (hors ligne) — il sera synchronisé.'
                  : 'Encaissement enregistré.')
              : (state.error ?? 'Encaissement impossible.'),
        ),
      ),
    );
  }
}

/// Bandeau d'état : en ligne / hors ligne / file d'attente.
class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.state});

  final PosState state;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final offline = state.offline || state.pendingOperations > 0;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: offline ? scheme.errorContainer : scheme.primaryContainer,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            offline ? Icons.cloud_off : Icons.cloud_done,
            size: 18,
            color: offline ? scheme.onErrorContainer : scheme.onPrimaryContainer,
          ),
          const SizedBox(width: 8),
          Text(
            offline ? 'Hors ligne' : 'En ligne',
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: offline ? scheme.onErrorContainer : scheme.onPrimaryContainer,
            ),
          ),
        ],
      ),
    );
  }
}

/// Colonne des catégories du menu.
class _CategoryRail extends StatelessWidget {
  const _CategoryRail({
    required this.categories,
    required this.selectedId,
    required this.onSelect,
  });

  final List<PosCategory> categories;
  final int? selectedId;
  final ValueChanged<int?> onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 200,
      child: ListView(
        padding: const EdgeInsets.symmetric(vertical: 12),
        children: [
          _CategoryTile(
            label: 'Tout le menu',
            selected: selectedId == null,
            onTap: () => onSelect(null),
          ),
          for (final category in categories)
            _CategoryTile(
              label: category.name,
              selected: selectedId == category.id,
              onTap: () => onSelect(category.id),
            ),
        ],
      ),
    );
  }
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      child: Material(
        color: selected ? scheme.primaryContainer : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            constraints: const BoxConstraints(minHeight: PosTheme.minTouchTarget),
            alignment: Alignment.centerLeft,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Text(
              label,
              style: TextStyle(
                fontSize: 16,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                color: selected ? scheme.onPrimaryContainer : scheme.onSurface,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Grille de produits — cibles larges, lisibles à bout de bras.
class _ProductGrid extends StatelessWidget {
  const _ProductGrid({required this.products, required this.onTap});

  final List<PosProduct> products;
  final ValueChanged<PosProduct> onTap;

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) {
      return const Center(child: Text('Aucun produit dans cette catégorie.'));
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 220,
        mainAxisSpacing: 14,
        crossAxisSpacing: 14,
        childAspectRatio: 1.35,
      ),
      itemCount: products.length,
      itemBuilder: (context, index) {
        final product = products[index];

        return Card(
          child: InkWell(
            onTap: product.available ? () => onTap(product) : null,
            borderRadius: BorderRadius.circular(PosTheme.cardRadius),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
                  ),
                  Text(
                    _formatMinor(product.priceMinor),
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Panneau du panier : lignes, type de commande, table, couverts, encaissement.
class _CartPanel extends StatelessWidget {
  const _CartPanel({
    required this.state,
    required this.onRemove,
    required this.onOrderType,
    required this.onTable,
    required this.onCovers,
    required this.onClear,
    required this.onPay,
  });

  final PosState state;
  final ValueChanged<int> onRemove;
  final ValueChanged<OrderType> onOrderType;
  final ValueChanged<int> onTable;
  final ValueChanged<int> onCovers;
  final VoidCallback onClear;
  final ValueChanged<String> onPay;

  @override
  Widget build(BuildContext context) {
    final cart = state.cart;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            children: [
              const Text('Commande', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
              const Spacer(),
              if (!cart.isEmpty)
                TextButton(onPressed: onClear, child: const Text('Vider')),
            ],
          ),
        ),
        _OrderTypeSelector(selected: cart.orderType, onSelect: onOrderType),
        if (cart.orderType == OrderType.dineIn)
          _TableSelector(
            tables: state.tables,
            selectedId: cart.tableId,
            covers: cart.covers,
            onTable: onTable,
            onCovers: onCovers,
          ),
        const Divider(height: 1),
        Expanded(
          child: cart.isEmpty
              ? const Center(child: Text('Panier vide'))
              : ListView.separated(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: cart.lines.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final line = cart.lines[index];

                    return ListTile(
                      title: Text(line.product.name),
                      subtitle: Text('${line.quantity} × ${_formatMinor(line.product.priceMinor)}'),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            _formatMinor(line.lineTotalMinor),
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          IconButton(
                            onPressed: () => onRemove(line.product.id),
                            icon: const Icon(Icons.remove_circle_outline),
                            tooltip: 'Retirer un',
                          ),
                        ],
                      ),
                    );
                  },
                ),
        ),
        const Divider(height: 1),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const Text('Total', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                  const Spacer(),
                  Text(
                    _formatMinor(cart.subtotalMinor),
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: cart.isValid ? () => onPay('cash') : null,
                icon: const Icon(Icons.payments),
                label: const Text('Encaisser — espèces'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: cart.isValid ? () => onPay('card') : null,
                icon: const Icon(Icons.credit_card),
                label: const Text('Encaisser — carte'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Sélecteur du type de commande (sur place / à emporter / livraison).
///
/// Ces valeurs étaient **codées en dur** dans le POS web
/// (`order_type: 'dine_in'`) : le restaurateur ne pouvait pas vendre à
/// emporter.
class _OrderTypeSelector extends StatelessWidget {
  const _OrderTypeSelector({required this.selected, required this.onSelect});

  final OrderType selected;
  final ValueChanged<OrderType> onSelect;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SegmentedButton<OrderType>(
        segments: [
          for (final type in OrderType.values)
            ButtonSegment(value: type, label: Text(type.label)),
        ],
        selected: {selected},
        onSelectionChanged: (selection) => onSelect(selection.first),
        showSelectedIcon: false,
      ),
    );
  }
}

/// Sélecteur de table + nombre de couverts (obligatoire pour `dine_in`).
class _TableSelector extends StatelessWidget {
  const _TableSelector({
    required this.tables,
    required this.selectedId,
    required this.covers,
    required this.onTable,
    required this.onCovers,
  });

  final List<PosTable> tables;
  final int? selectedId;
  final int covers;
  final ValueChanged<int> onTable;
  final ValueChanged<int> onCovers;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<int>(
            initialValue: selectedId,
            decoration: const InputDecoration(labelText: 'Table'),
            items: [
              for (final table in tables)
                DropdownMenuItem(
                  value: table.id,
                  child: Text('${table.label} — ${table.seats} places'),
                ),
            ],
            onChanged: (value) {
              if (value != null) {
                onTable(value);
              }
            },
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Text('Couverts', style: TextStyle(fontSize: 16)),
              const Spacer(),
              IconButton(
                onPressed: covers > 1 ? () => onCovers(covers - 1) : null,
                icon: const Icon(Icons.remove),
              ),
              Text('$covers', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              IconButton(
                onPressed: () => onCovers(covers + 1),
                icon: const Icon(Icons.add),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Formate un montant en unités mineures (entiers) en affichage lisible.
String _formatMinor(int minor) {
  final units = minor ~/ 100;
  final cents = (minor % 100).toString().padLeft(2, '0');

  return '$units,$cents';
}
