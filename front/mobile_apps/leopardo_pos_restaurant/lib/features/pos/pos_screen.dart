import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/pos_models.dart';
import '../../core/theme/pos_theme.dart';
import 'floor_plan.dart';
import 'payment_sheets.dart';
import 'pos_controller.dart';

/// Écran principal de la caisse tablette Restaurant (#9214).
///
/// Disposition **paysage** en trois colonnes, pensée pour une tablette posée
/// sur un comptoir :
///  - gauche : catégories du menu ;
///  - centre : grille de produits (cibles larges) ;
///  - droite : panier, type de commande, plan de salle, remise, pourboire,
///    encaissement (simple ou partagé).
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
      body: Column(
        children: [
          if (state.offline || state.pendingOperations > 0)
            _OfflineBanner(
              pending: state.pendingOperations,
              onSync: controller.flushQueue,
            ),
          if (state.error != null)
            _ErrorBanner(
              message: state.error!,
              onDismiss: controller.clearError,
            ),
          Expanded(
            child: state.loading
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
                        width: 420,
                        child: _CartPanel(
                          state: state,
                          onRemove: controller.removeProduct,
                          onOrderType: controller.setOrderType,
                          onTable: controller.selectTable,
                          onCovers: controller.setCovers,
                          onDiscount: controller.setDiscount,
                          onTip: controller.setTip,
                          onClear: controller.clearCart,
                          onPay: (method) => _pay(context, controller, method),
                          onSplitPay: () => _splitPay(context, controller),
                        ),
                      ),
                    ],
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

  /// Encaissement partagé : plusieurs moyens pour une même addition.
  Future<void> _splitPay(BuildContext context, PosController controller) async {
    final total = ref.read(posControllerProvider).cart.totalMinor;

    final split = await showModalBottomSheet<SplitPayment>(
      context: context,
      isScrollControlled: true,
      builder: (_) => SplitPaymentSheet(totalMinor: total),
    );

    if (split == null || !context.mounted) {
      return;
    }

    // Chaque part est encaissée avec son propre moyen de paiement. La
    // première part porte la commande ; les suivantes la complètent.
    var allOk = true;

    for (final part in split.splits) {
      final ok = await controller.submitAndPay(method: part.method.wireValue);

      if (!ok) {
        allOk = false;
        break;
      }
    }

    if (!context.mounted) {
      return;
    }

    final state = ref.read(posControllerProvider);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          allOk
              ? (state.offline
                  ? 'Paiement partagé mis en file (hors ligne).'
                  : 'Paiement partagé encaissé.')
              : (state.error ?? 'Paiement partagé refusé.'),
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

/// Bandeau hors ligne : rassure (la vente continue, rien n'est perdu).
class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner({required this.pending, required this.onSync});

  final int pending;
  final VoidCallback onSync;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: scheme.tertiaryContainer,
      child: Row(
        children: [
          Icon(Icons.cloud_off, color: scheme.onTertiaryContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              pending == 0
                  ? 'Hors ligne : le menu est en cache, le service continue.'
                  : 'Hors ligne : $pending opération(s) en attente. Rien n\'est perdu.',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: scheme.onTertiaryContainer,
              ),
            ),
          ),
          if (pending > 0)
            TextButton.icon(
              onPressed: onSync,
              icon: const Icon(Icons.sync),
              label: const Text('Synchroniser'),
            ),
        ],
      ),
    );
  }
}

/// Bandeau d'erreur lisible à distance.
class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, required this.onDismiss});

  final String message;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: scheme.errorContainer,
      child: Row(
        children: [
          Icon(Icons.error_outline, color: scheme.onErrorContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: scheme.onErrorContainer,
              ),
            ),
          ),
          IconButton(
            tooltip: 'Fermer',
            onPressed: onDismiss,
            icon: Icon(Icons.close, color: scheme.onErrorContainer),
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
      final scheme = Theme.of(context).colorScheme;

      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.restaurant_menu, size: 56, color: scheme.outline),
              const SizedBox(height: 16),
              Text(
                'Aucun plat dans cette catégorie',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Choisissez une autre catégorie du menu.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, color: scheme.onSurfaceVariant),
              ),
            ],
          ),
        ),
      );
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

/// Panneau du panier : lignes, type de commande, plan de salle, remise,
/// pourboire, encaissement.
class _CartPanel extends StatelessWidget {
  const _CartPanel({
    required this.state,
    required this.onRemove,
    required this.onOrderType,
    required this.onTable,
    required this.onCovers,
    required this.onDiscount,
    required this.onTip,
    required this.onClear,
    required this.onPay,
    required this.onSplitPay,
  });

  final PosState state;
  final ValueChanged<int> onRemove;
  final ValueChanged<OrderType> onOrderType;
  final ValueChanged<int> onTable;
  final ValueChanged<int> onCovers;
  final ValueChanged<int> onDiscount;
  final ValueChanged<int> onTip;
  final VoidCallback onClear;
  final ValueChanged<String> onPay;
  final VoidCallback onSplitPay;

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
              const SizedBox(width: 8),
              if (!cart.isEmpty)
                Chip(
                  label: Text('${cart.itemCount} article(s)'),
                  visualDensity: VisualDensity.compact,
                ),
              const Spacer(),
              if (!cart.isEmpty)
                TextButton(onPressed: onClear, child: const Text('Vider')),
            ],
          ),
        ),
        _OrderTypeSelector(selected: cart.orderType, onSelect: onOrderType),
        if (cart.orderType == OrderType.dineIn)
          _TableSection(
            tables: state.tables,
            selectedId: cart.tableId,
            covers: cart.covers,
            onTable: onTable,
            onCovers: onCovers,
          ),
        const Divider(height: 1),
        Expanded(
          child: cart.isEmpty
              ? const _EmptyCart()
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
              _TotalRow(label: 'Sous-total', value: cart.subtotalMinor),
              if (cart.discountMinor > 0)
                _TotalRow(label: 'Remise', value: -cart.discountMinor),
              if (cart.tipMinor > 0)
                _TotalRow(label: 'Pourboire', value: cart.tipMinor),
              const SizedBox(height: 6),
              _TotalRow(label: 'Total', value: cart.totalMinor, emphasized: true),
              if (!cart.discountValid)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    'La remise dépasse le sous-total.',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: cart.isEmpty ? null : () => _editDiscount(context),
                      icon: const Icon(Icons.percent),
                      label: Text(
                        cart.discountMinor > 0
                            ? 'Remise ${_formatMinor(cart.discountMinor)}'
                            : 'Remise',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: cart.isEmpty ? null : () => _editTip(context),
                      icon: const Icon(Icons.volunteer_activism_outlined),
                      label: Text(
                        cart.tipMinor > 0
                            ? 'Pourboire ${_formatMinor(cart.tipMinor)}'
                            : 'Pourboire',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
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
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: cart.isValid ? onSplitPay : null,
                icon: const Icon(Icons.call_split),
                label: const Text('Paiement partagé'),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _editDiscount(BuildContext context) async {
    final value = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (_) => AmountEntrySheet(
        title: 'Remise',
        label: 'Montant de la remise',
        initialMinor: state.cart.discountMinor,
        maxMinor: state.cart.subtotalMinor,
        helperText: 'Sous-total : ${_formatMinor(state.cart.subtotalMinor)}',
      ),
    );

    if (value != null) {
      onDiscount(value);
    }
  }

  Future<void> _editTip(BuildContext context) async {
    final value = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (_) => AmountEntrySheet(
        title: 'Pourboire',
        label: 'Montant du pourboire',
        initialMinor: state.cart.tipMinor,
        helperText: 'Le pourboire s\'ajoute au total de l\'addition.',
      ),
    );

    if (value != null) {
      onTip(value);
    }
  }
}

/// Panier vide : dit quoi faire.
class _EmptyCart extends StatelessWidget {
  const _EmptyCart();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.receipt_long_outlined, size: 48, color: scheme.outline),
            const SizedBox(height: 12),
            Text(
              'Aucun article',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: scheme.onSurface,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Touchez un plat du menu pour l\'ajouter à la commande.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 15, color: scheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
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

/// Plan de salle + nombre de couverts (obligatoire pour `dine_in`).
class _TableSection extends StatelessWidget {
  const _TableSection({
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
          Row(
            children: [
              const Text('Table', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
              const Spacer(),
              const Text('Couverts', style: TextStyle(fontSize: 16)),
              IconButton(
                tooltip: 'Un couvert de moins',
                onPressed: covers > 1 ? () => onCovers(covers - 1) : null,
                icon: const Icon(Icons.remove),
              ),
              Text(
                '$covers',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              IconButton(
                tooltip: 'Un couvert de plus',
                onPressed: () => onCovers(covers + 1),
                icon: const Icon(Icons.add),
              ),
            ],
          ),
          const SizedBox(height: 6),
          // Le plan de salle remplace la liste deroulante : le serveur
          // designe la table comme il la voit depuis le comptoir.
          SizedBox(
            height: 240,
            child: FloorPlan(
              tables: tables,
              selectedId: selectedId,
              onSelect: onTable,
              covers: covers,
            ),
          ),
        ],
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({required this.label, required this.value, this.emphasized = false});

  final String label;
  final int value;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: emphasized ? 20 : 16,
              fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
          Text(
            _formatMinor(value),
            style: TextStyle(
              fontSize: emphasized ? 26 : 16,
              fontWeight: emphasized ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// Formate un montant en unités mineures (entiers) en affichage lisible.
String _formatMinor(int minor) {
  final negative = minor < 0;
  final absolute = minor.abs();
  final units = absolute ~/ 100;
  final cents = (absolute % 100).toString().padLeft(2, '0');

  return '${negative ? '-' : ''}$units,$cents';
}
