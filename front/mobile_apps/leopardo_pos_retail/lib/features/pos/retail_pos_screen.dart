import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/retail_models.dart';
import '../../core/theme/retail_theme.dart';
import 'cash_movement_sheet.dart';
import 'close_session_sheet.dart';
import 'refund_sheet.dart';
import 'retail_pos_controller.dart';

/// Caisse tablette Retail — disposition 3 colonnes (#9215).
///
/// `rayons | produits | panier` : c'est la disposition d'un vrai poste de
/// caisse. Le vendeur garde le panier sous les yeux pendant qu'il cherche un
/// article, et le total reste visible sans faire defiler.
class RetailPosScreen extends ConsumerStatefulWidget {
  const RetailPosScreen({super.key, required this.locationId});

  final int locationId;

  @override
  ConsumerState<RetailPosScreen> createState() => _RetailPosScreenState();
}

class _RetailPosScreenState extends ConsumerState<RetailPosScreen> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(retailPosControllerProvider);
    final notifier = ref.read(retailPosControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Caisse'),
        actions: [
          if (state.offline)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 12),
              child: Chip(
                avatar: Icon(Icons.cloud_off, size: 18),
                label: Text('Hors ligne'),
              ),
            ),
          if (state.pendingOperations > 0)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: TextButton.icon(
                onPressed: notifier.flushQueue,
                icon: const Icon(Icons.sync),
                label: Text('${state.pendingOperations} a synchroniser'),
              ),
            ),
          IconButton(
            tooltip: 'Mouvement de caisse',
            onPressed: state.sessionOpen ? _openCashMovement : null,
            icon: const Icon(Icons.account_balance_wallet_outlined),
          ),
          IconButton(
            tooltip: 'Remboursement',
            onPressed: state.lastOrderId != null ? _openRefund : null,
            icon: const Icon(Icons.undo),
          ),
          IconButton(
            tooltip: 'Cloture de caisse',
            onPressed: state.sessionOpen ? _openClose : null,
            icon: const Icon(Icons.lock_outline),
          ),
        ],
      ),
      body: state.loading && state.products.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _CategoryRail(
                  categories: state.categories,
                  selectedId: state.selectedCategoryId,
                  onSelect: notifier.selectCategory,
                ),
                const VerticalDivider(width: 1),
                Expanded(
                  flex: 3,
                  child: _ProductGrid(
                    products: state.visibleProducts,
                    searchController: _searchController,
                    onSearch: notifier.setSearchQuery,
                    onScan: notifier.scanBarcode,
                    onPick: notifier.addProduct,
                  ),
                ),
                const VerticalDivider(width: 1),
                SizedBox(
                  width: 380,
                  child: _CartPanel(
                    state: state,
                    onRemove: notifier.removeProduct,
                    onSetQuantity: notifier.setQuantity,
                    onSetDiscount: notifier.setDiscount,
                    onClear: notifier.clearCart,
                    onPay: _pay,
                  ),
                ),
              ],
            ),
    );
  }

  Future<void> _pay(RetailPaymentMethod method) async {
    final notifier = ref.read(retailPosControllerProvider.notifier);
    final ok = await notifier.submitAndPay(method: method);

    if (!mounted) {
      return;
    }

    final state = ref.read(retailPosControllerProvider);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? (state.offline
                  ? 'Encaissement enregistre hors ligne — sera synchronise.'
                  : 'Encaissement valide.')
              : (state.error ?? 'Encaissement refuse.'),
        ),
      ),
    );
  }

  Future<void> _openCashMovement() async {
    final movement = await showModalBottomSheet<RetailCashMovement>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const CashMovementSheet(),
    );

    if (movement == null || !mounted) {
      return;
    }

    final ok = await ref.read(retailPosControllerProvider.notifier).recordCashMovement(movement);

    if (!mounted) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);

    messenger.showSnackBar(
      SnackBar(content: Text(ok ? 'Mouvement enregistre.' : 'Mouvement refuse.')),
    );
  }

  Future<void> _openRefund() async {
    final refund = await showModalBottomSheet<RetailRefund>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const RefundSheet(),
    );

    if (refund == null || !mounted) {
      return;
    }

    final ok = await ref.read(retailPosControllerProvider.notifier).refundLastOrder(refund);

    if (!mounted) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);

    messenger.showSnackBar(
      SnackBar(content: Text(ok ? 'Remboursement enregistre.' : 'Remboursement refuse.')),
    );
  }

  Future<void> _openClose() async {
    final state = ref.read(retailPosControllerProvider);
    final session = state.session;

    if (session == null) {
      return;
    }

    final result = await showModalBottomSheet<CloseSessionResult>(
      context: context,
      isScrollControlled: true,
      builder: (_) => CloseSessionSheet(session: session),
    );

    if (result == null || !mounted) {
      return;
    }

    final closed = await ref.read(retailPosControllerProvider.notifier).closeSession(
          countedCashMinor: result.countedCashMinor,
          varianceReason: result.varianceReason,
        );

    if (!mounted) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);

    if (closed == null) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Cloture refusee.')),
      );

      return;
    }

    final variance = RetailSession.variance(
      countedCashMinor: result.countedCashMinor,
      expectedCashMinor: closed.expectedCashMinor,
    );

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          variance == 0
              ? 'Caisse cloturee, aucun ecart.'
              : 'Caisse cloturee, ecart de $variance.',
        ),
      ),
    );
  }
}

/// Colonne 1 : rayons.
class _CategoryRail extends StatelessWidget {
  const _CategoryRail({
    required this.categories,
    required this.selectedId,
    required this.onSelect,
  });

  final List<RetailCategory> categories;
  final int? selectedId;
  final void Function(int?) onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 200,
      child: ListView(
        padding: const EdgeInsets.symmetric(vertical: 8),
        children: [
          ListTile(
            selected: selectedId == null,
            leading: const Icon(Icons.apps),
            title: const Text('Tout'),
            onTap: () => onSelect(null),
          ),
          for (final category in categories)
            ListTile(
              selected: selectedId == category.id,
              leading: const Icon(Icons.category_outlined),
              title: Text(category.name, overflow: TextOverflow.ellipsis),
              onTap: () => onSelect(category.id),
            ),
        ],
      ),
    );
  }
}

/// Colonne 2 : recherche + grille produit.
class _ProductGrid extends StatelessWidget {
  const _ProductGrid({
    required this.products,
    required this.searchController,
    required this.onSearch,
    required this.onScan,
    required this.onPick,
  });

  final List<RetailProduct> products;
  final TextEditingController searchController;
  final void Function(String) onSearch;
  final bool Function(String) onScan;
  final void Function(RetailProduct) onPick;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            controller: searchController,
            onChanged: onSearch,
            onSubmitted: (value) {
              // Un code-barres scanne arrive comme une soumission : on tente
              // d'abord la correspondance exacte, sinon on garde la recherche.
              if (!onScan(value)) {
                onSearch(value);
              }
            },
            decoration: const InputDecoration(
              hintText: 'Rechercher un produit ou scanner un code-barres',
              prefixIcon: Icon(Icons.search),
              suffixIcon: Icon(Icons.qr_code_scanner),
            ),
          ),
        ),
        Expanded(
          child: products.isEmpty
              ? const Center(child: Text('Aucun produit'))
              : GridView.builder(
                  padding: const EdgeInsets.all(12),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 220,
                    mainAxisExtent: RetailTheme.productTileHeight,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemCount: products.length,
                  itemBuilder: (context, index) {
                    final product = products[index];

                    return Card(
                      child: InkWell(
                        borderRadius: BorderRadius.circular(RetailTheme.cardRadius),
                        onTap: () => onPick(product),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                product.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    '${product.priceMinor}',
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  if (product.isScannable)
                                    const Icon(Icons.qr_code, size: 18),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

/// Colonne 3 : panier + total + encaissement.
class _CartPanel extends StatelessWidget {
  const _CartPanel({
    required this.state,
    required this.onRemove,
    required this.onSetQuantity,
    required this.onSetDiscount,
    required this.onClear,
    required this.onPay,
  });

  final RetailPosState state;
  final void Function(int) onRemove;
  final void Function(int, int) onSetQuantity;
  final void Function(int) onSetDiscount;
  final VoidCallback onClear;
  final Future<void> Function(RetailPaymentMethod) onPay;

  @override
  Widget build(BuildContext context) {
    final cart = state.cart;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Panier', style: Theme.of(context).textTheme.titleLarge),
              if (!cart.isEmpty)
                TextButton.icon(
                  onPressed: onClear,
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Vider'),
                ),
            ],
          ),
        ),
        Expanded(
          child: cart.isEmpty
              ? const Center(child: Text('Panier vide'))
              : ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  itemCount: cart.lines.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final line = cart.lines[index];

                    return ListTile(
                      title: Text(line.product.name, overflow: TextOverflow.ellipsis),
                      subtitle: Text('${line.product.priceMinor} x ${line.quantity}'),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            onPressed: () => onRemove(line.product.id),
                            icon: const Icon(Icons.remove_circle_outline),
                          ),
                          Text(
                            '${line.quantity}',
                            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                          ),
                          IconButton(
                            onPressed: () => onSetQuantity(line.product.id, line.quantity + 1),
                            icon: const Icon(Icons.add_circle_outline),
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
              const SizedBox(height: 8),
              _TotalRow(label: 'Total', value: cart.totalMinor, emphasized: true),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: state.loading || cart.isEmpty ? null : () => onPay(RetailPaymentMethod.cash),
                icon: const Icon(Icons.payments),
                label: const Text('Encaisser especes'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: state.loading || cart.isEmpty ? null : () => onPay(RetailPaymentMethod.card),
                icon: const Icon(Icons.credit_card),
                label: const Text('Carte'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: state.loading || cart.isEmpty
                    ? null
                    : () => onPay(RetailPaymentMethod.mobileMoney),
                icon: const Icon(Icons.phone_android),
                label: const Text('Mobile Money'),
              ),
            ],
          ),
        ),
      ],
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
            '$value',
            style: TextStyle(
              fontSize: emphasized ? 24 : 16,
              fontWeight: emphasized ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
