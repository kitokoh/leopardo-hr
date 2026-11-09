import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/retail_models.dart';
import '../../core/theme/retail_theme.dart';
import 'barcode_scanner_screen.dart';
import 'cash_movement_sheet.dart';
import 'close_session_sheet.dart';
import 'numeric_keypad.dart';
import 'pos_states.dart';
import 'refund_sheet.dart';
import 'retail_pos_controller.dart';

/// Caisse tablette Retail — disposition 3 colonnes (#9215).
///
/// `rayons | produits | panier` : c'est la disposition d'un vrai poste de
/// caisse. Le vendeur garde le panier sous les yeux pendant qu'il cherche un
/// article, et le total reste visible sans faire defiler.
///
/// Ergonomie de comptoir :
///  - **scan code-barres natif** (camera) : le geste central du vendeur ;
///  - **pave numerique tactile** pour les quantites et les remises, sans
///    clavier systeme qui masquerait le panier ;
///  - **etats explicites** : vide, hors ligne, erreur — jamais d'ecran muet.
class RetailPosScreen extends ConsumerStatefulWidget {
  const RetailPosScreen({super.key, required this.locationId});

  final int locationId;

  @override
  ConsumerState<RetailPosScreen> createState() => _RetailPosScreenState();
}

class _RetailPosScreenState extends ConsumerState<RetailPosScreen> {
  final _searchController = TextEditingController();
  final _searchFocus = FocusNode();

  @override
  void dispose() {
    _searchController.dispose();
    _searchFocus.dispose();
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
          PosConnectionChip(
            offline: state.offline,
            pending: state.pendingOperations,
          ),
          const SizedBox(width: 8),
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
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          if (state.offline || state.pendingOperations > 0)
            PosOfflineBanner(
              pendingOperations: state.pendingOperations,
              onSync: notifier.flushQueue,
              syncing: state.loading,
            ),
          if (state.error != null)
            PosErrorBanner(
              message: state.error!,
              onDismiss: notifier.clearError,
            ),
          Expanded(
            child: state.loading && state.products.isEmpty
                ? const PosLoadingState()
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
                          searchFocus: _searchFocus,
                          onSearch: notifier.setSearchQuery,
                          onScan: notifier.scanBarcode,
                          onOpenScanner: _openScanner,
                          onPick: notifier.addProduct,
                          onClearSearch: () {
                            _searchController.clear();
                            notifier.setSearchQuery('');
                          },
                        ),
                      ),
                      const VerticalDivider(width: 1),
                      SizedBox(
                        width: 400,
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
          ),
        ],
      ),
    );
  }

  /// Ouvre le scanner camera et ajoute l'article lu.
  ///
  /// Le scan est le geste le plus frequent en boutique : il est accessible
  /// depuis la barre de recherche ET depuis la barre d'actions rapides.
  Future<void> _openScanner() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const BarcodeScannerScreen()),
    );

    if (code == null || !mounted) {
      return;
    }

    final notifier = ref.read(retailPosControllerProvider.notifier);
    final found = notifier.scanBarcode(code);

    if (!mounted) {
      return;
    }

    final messenger = ScaffoldMessenger.of(context);

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          found
              ? 'Article ajoute au panier.'
              : 'Aucun article pour le code $code.',
        ),
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
              : 'Caisse cloturee, ecart de ${formatMinor(variance)}.',
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
          _CategoryTile(
            label: 'Tout',
            icon: Icons.apps,
            selected: selectedId == null,
            onTap: () => onSelect(null),
          ),
          for (final category in categories)
            _CategoryTile(
              label: category.name,
              icon: Icons.category_outlined,
              selected: selectedId == category.id,
              onTap: () => onSelect(category.id),
            ),
        ],
      ),
    );
  }
}

/// Une entree de rayon : cible tactile large, etat selectionne visible.
class _CategoryTile extends StatelessWidget {
  const _CategoryTile({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
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
            constraints: const BoxConstraints(minHeight: RetailTheme.minTouchTarget),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Icon(
                  icon,
                  size: 20,
                  color: selected ? scheme.onPrimaryContainer : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    label,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                      color: selected ? scheme.onPrimaryContainer : scheme.onSurface,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Colonne 2 : recherche + scan + grille produit.
class _ProductGrid extends StatelessWidget {
  const _ProductGrid({
    required this.products,
    required this.searchController,
    required this.searchFocus,
    required this.onSearch,
    required this.onScan,
    required this.onOpenScanner,
    required this.onPick,
    required this.onClearSearch,
  });

  final List<RetailProduct> products;
  final TextEditingController searchController;
  final FocusNode searchFocus;
  final void Function(String) onSearch;
  final bool Function(String) onScan;
  final VoidCallback onOpenScanner;
  final void Function(RetailProduct) onPick;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: searchController,
                  focusNode: searchFocus,
                  onChanged: onSearch,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (value) {
                    // Un code-barres scanne arrive comme une soumission : on
                    // tente d'abord la correspondance exacte, sinon on garde
                    // la recherche libre.
                    if (!onScan(value)) {
                      onSearch(value);
                    }
                  },
                  decoration: InputDecoration(
                    hintText: 'Rechercher un article (nom, SKU, code-barres)',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: searchController.text.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Effacer la recherche',
                            onPressed: onClearSearch,
                            icon: const Icon(Icons.close),
                          ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              // Le scan camera est une action de premier plan : bouton plein,
              // pas une icone perdue dans un menu.
              FilledButton.icon(
                onPressed: onOpenScanner,
                icon: const Icon(Icons.qr_code_scanner),
                label: const Text('Scanner'),
              ),
            ],
          ),
        ),
        Expanded(
          child: products.isEmpty
              ? PosEmptyState(
                  icon: Icons.inventory_2_outlined,
                  title: 'Aucun article',
                  message: searchController.text.isEmpty
                      ? 'Ce rayon ne contient aucun article disponible.'
                      : 'Aucun article ne correspond a « ${searchController.text} ».',
                  action: searchController.text.isEmpty
                      ? null
                      : OutlinedButton.icon(
                          onPressed: onClearSearch,
                          icon: const Icon(Icons.close),
                          label: const Text('Effacer la recherche'),
                        ),
                )
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

                    return _ProductTile(product: product, onPick: onPick);
                  },
                ),
        ),
      ],
    );
  }
}

/// Une tuile produit : nom, prix formate, indicateur de scannabilite.
class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product, required this.onPick});

  final RetailProduct product;
  final void Function(RetailProduct) onPick;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      button: true,
      label: '${product.name}, ${formatMinor(product.priceMinor)}',
      child: Card(
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
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      formatMinor(product.priceMinor),
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: scheme.primary,
                      ),
                    ),
                    if (product.isScannable)
                      Icon(Icons.qr_code, size: 18, color: scheme.onSurfaceVariant),
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
            children: [
              Text('Panier', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(width: 8),
              if (!cart.isEmpty)
                Chip(
                  label: Text('${cart.itemCount} article(s)'),
                  visualDensity: VisualDensity.compact,
                ),
              const Spacer(),
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
              ? const PosEmptyState(
                  icon: Icons.shopping_cart_outlined,
                  title: 'Panier vide',
                  message: 'Scannez un code-barres ou touchez un article pour commencer.',
                )
              : ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  itemCount: cart.lines.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final line = cart.lines[index];

                    return _CartLineTile(
                      line: line,
                      onRemove: onRemove,
                      onSetQuantity: onSetQuantity,
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
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: cart.isEmpty ? null : () => _openDiscount(context),
                icon: const Icon(Icons.percent),
                label: Text(
                  cart.discountMinor > 0
                      ? 'Remise : ${formatMinor(cart.discountMinor)}'
                      : 'Appliquer une remise',
                ),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: state.loading || cart.isEmpty
                    ? null
                    : () => onPay(RetailPaymentMethod.cash),
                icon: const Icon(Icons.payments),
                label: const Text('Encaisser especes'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: state.loading || cart.isEmpty
                    ? null
                    : () => onPay(RetailPaymentMethod.card),
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

  /// Saisie de la remise au pave numerique (pas de clavier systeme).
  Future<void> _openDiscount(BuildContext context) async {
    final value = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _DiscountSheet(
        subtotalMinor: state.cart.subtotalMinor,
        currentDiscountMinor: state.cart.discountMinor,
      ),
    );

    if (value != null) {
      onSetDiscount(value);
    }
  }
}

/// Une ligne du panier : quantite ajustable au doigt, total de ligne visible.
class _CartLineTile extends StatelessWidget {
  const _CartLineTile({
    required this.line,
    required this.onRemove,
    required this.onSetQuantity,
  });

  final RetailCartLine line;
  final void Function(int) onRemove;
  final void Function(int, int) onSetQuantity;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text(line.product.name, overflow: TextOverflow.ellipsis),
      subtitle: Text('${formatMinor(line.product.priceMinor)} l\'unite'),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Retirer un',
            onPressed: () => onRemove(line.product.id),
            icon: const Icon(Icons.remove_circle_outline),
          ),
          // La quantite est un bouton : la toucher ouvre le pave numerique
          // pour saisir directement « 12 » au lieu de taper 12 fois « + ».
          TextButton(
            onPressed: () => _editQuantity(context),
            child: Text(
              '${line.quantity}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
            ),
          ),
          IconButton(
            tooltip: 'Ajouter un',
            onPressed: () => onSetQuantity(line.product.id, line.quantity + 1),
            icon: const Icon(Icons.add_circle_outline),
          ),
          SizedBox(
            width: 76,
            child: Text(
              formatMinor(line.lineTotalMinor),
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _editQuantity(BuildContext context) async {
    final value = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _QuantitySheet(
        productName: line.product.name,
        initialQuantity: line.quantity,
      ),
    );

    if (value != null) {
      onSetQuantity(line.product.id, value);
    }
  }
}

/// Feuille de saisie de quantite au pave numerique.
class _QuantitySheet extends StatefulWidget {
  const _QuantitySheet({required this.productName, required this.initialQuantity});

  final String productName;
  final int initialQuantity;

  @override
  State<_QuantitySheet> createState() => _QuantitySheetState();
}

class _QuantitySheetState extends State<_QuantitySheet> {
  late String _input = '${widget.initialQuantity}';

  @override
  Widget build(BuildContext context) {
    final quantity = parseMinorUnits(_input);

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Quantite', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(widget.productName, style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 16),
          PosNumericField(value: _input, label: 'Quantite'),
          const SizedBox(height: 12),
          PosNumericKeypad(
            onDigit: (digit) => setState(() => _input = applyNumericKey(_input, digit)),
            onBackspace: () => setState(() => _input = applyBackspace(_input)),
            onClear: () => setState(() => _input = ''),
            onValidate: quantity > 0 ? () => Navigator.of(context).pop(quantity) : null,
            validateLabel: 'Valider la quantite',
            validateEnabled: quantity > 0,
          ),
        ],
      ),
    );
  }
}

/// Feuille de saisie de remise au pave numerique, bornee au sous-total.
class _DiscountSheet extends StatefulWidget {
  const _DiscountSheet({
    required this.subtotalMinor,
    required this.currentDiscountMinor,
  });

  final int subtotalMinor;
  final int currentDiscountMinor;

  @override
  State<_DiscountSheet> createState() => _DiscountSheetState();
}

class _DiscountSheetState extends State<_DiscountSheet> {
  late String _input =
      widget.currentDiscountMinor > 0 ? '${widget.currentDiscountMinor}' : '';

  @override
  Widget build(BuildContext context) {
    final discount = parseMinorUnits(_input);
    final exceeds = discount > widget.subtotalMinor;

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Remise', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(
            'Sous-total : ${formatMinor(widget.subtotalMinor)}',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          PosNumericField(
            value: _input,
            label: 'Montant de la remise',
            errorText: exceeds ? 'La remise depasse le sous-total.' : null,
          ),
          const SizedBox(height: 12),
          PosNumericKeypad(
            onDigit: (digit) => setState(() => _input = applyNumericKey(_input, digit)),
            onBackspace: () => setState(() => _input = applyBackspace(_input)),
            onClear: () => setState(() => _input = ''),
            onValidate: exceeds ? null : () => Navigator.of(context).pop(discount),
            validateLabel: 'Appliquer la remise',
            validateEnabled: !exceeds,
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
            formatMinor(value),
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

/// Formate un montant en unites mineures (entiers) en affichage lisible.
///
/// La caisse manipule des entiers (jamais de flottant) : la conversion en
/// affichage se fait ici, une seule fois, et gere le signe (une remise
/// s'affiche `-1,50`).
String formatMinor(int minor) {
  final negative = minor < 0;
  final absolute = minor.abs();
  final units = absolute ~/ 100;
  final cents = (absolute % 100).toString().padLeft(2, '0');

  return '${negative ? '-' : ''}$units,$cents';
}
