import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_barcode_scanner.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_history.dart';
import 'package:leopardo_core/core/pos/pos_input.dart';
import 'package:leopardo_core/core/pos/pos_option_sheet.dart';
import 'package:leopardo_core/core/pos/pos_printer_screen.dart';
import 'package:leopardo_core/core/pos/pos_settings_screen.dart';
import 'package:leopardo_core/core/pos/pos_states.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';
import 'package:leopardo_core/core/pos/pos_widgets.dart';

import '../../core/api/pos_api_client.dart';
import '../../core/models/pos_models.dart';
import '../../core/theme/pos_theme.dart';
import 'floor_plan.dart';
import 'payment_sheets.dart';
import 'pos_controller.dart';
import 'pos_providers.dart';

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
  final _searchController = TextEditingController();

  /// Detecteur de douchette materielle (USB / Bluetooth).
  ///
  /// Une douchette « tape » le code puis Entree, tres vite : sans ce filtre,
  /// le code atterrit dans le champ de recherche au lieu d'ajouter le plat.
  final _hardwareScanner = PosHardwareScannerDetector();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Traite une touche : douchette d'abord, raccourci ensuite.
  KeyEventResult _onKey(FocusNode node, KeyEvent event) {
    if (event is! KeyDownEvent) {
      return KeyEventResult.ignored;
    }

    final character = event.character;

    if (character != null && character.isNotEmpty) {
      final scanned = _hardwareScanner.feed(
        PosKeyStroke(character: character, at: DateTime.now()),
      );

      if (scanned != null) {
        _handleScan(scanned);

        return KeyEventResult.handled;
      }
    }

    final shortcut = posShortcutFor(event.logicalKey.keyLabel);

    if (shortcut == null) {
      return KeyEventResult.ignored;
    }

    _runShortcut(shortcut);

    return KeyEventResult.handled;
  }

  void _runShortcut(PosShortcut shortcut) {
    final controller = ref.read(posControllerProvider.notifier);
    final state = ref.read(posControllerProvider);

    switch (shortcut) {
      case PosShortcut.scan:
        _openScanner();
      case PosShortcut.pay:
        if (state.cart.isValid) {
          _pay(context, controller, 'cash');
        }
      case PosShortcut.park:
        _openPark();
      case PosShortcut.recall:
        _openRecall();
      case PosShortcut.receipt:
        _openReceipt();
      case PosShortcut.dismiss:
        _searchController.clear();
        controller.setSearchQuery('');
      case PosShortcut.removeLastLine:
        if (state.cart.lines.isNotEmpty) {
          controller.removeProduct(state.cart.lines.last.product.id);
        }
      case PosShortcut.incrementLastLine:
        if (state.cart.lines.isNotEmpty) {
          final line = state.cart.lines.last;
          controller.setQuantity(line.product.id, line.quantity + 1);
        }
      case PosShortcut.decrementLastLine:
        if (state.cart.lines.isNotEmpty) {
          final line = state.cart.lines.last;
          controller.setQuantity(line.product.id, line.quantity - 1);
        }
      case PosShortcut.discount:
        if (!state.cart.isEmpty) {
          _openDiscount();
        }
      case PosShortcut.tip:
        if (!state.cart.isEmpty) {
          _openTip();
        }
    }
  }

  /// Ajoute le plat correspondant a un code scanne (camera ou douchette).
  void _handleScan(String code) {
    final controller = ref.read(posControllerProvider.notifier);
    final found = controller.scanBarcode(code);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        duration: const Duration(seconds: 2),
        content: Text(
          found ? 'Article ajoute a la commande.' : 'Aucun article pour le code $code.',
        ),
      ),
    );
  }

  /// Tap sur un produit du menu : ouvre la feuille d'options quand le plat en
  /// a (cuisson, accompagnement, supplements) ; sinon ajout direct.
  ///
  /// Le backend recalculant les totaux, les options sont portees par la
  /// ligne : le serveur encaisse le montant que le client a accepte.
  Future<void> _onProductTap(PosProduct product) async {
    final controller = ref.read(posControllerProvider.notifier);

    if (product.options.isEmpty) {
      controller.addProduct(product);

      return;
    }

    final result = await showPosOptionSheet(
      context: context,
      productName: product.name,
      basePriceMinor: product.priceMinor,
      productOptions: product.options,
    );

    if (result == null || !mounted) {
      return;
    }

    controller.addProductWithOptions(product, result.selections);
  }

  /// Ouvre l'historique des ventes et permet de reimprimer un ticket (#9268).
  Future<void> _openHistory() async {
    final state = ref.read(posControllerProvider);

    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => PosSalesHistoryScreen(
          history: state.history,
          printer: ref.read(posPrinterProvider),
          shopName: 'Leopardo Restaurant',
        ),
      ),
    );
  }

  /// Protege par PIN : un vendeur ne doit pas pouvoir detourner la caisse
  /// vers un autre serveur au milieu du service.
  Future<void> _openSettings() async {
    final store = ref.read(posLocalStoreProvider);
    final current = ref.read(posConfigProvider) ??
        const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1');

    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => PosSettingsScreen(
          initialConfig: current,
          locationLabel: 'Succursale',
          pinHash: ref.read(posPinHashProvider),
          printerName: store.printer?.name,
          onOpenPrinter: _openPrinterPicker,
          onSave: (config) async {
            await store.saveConfig(config);
            ref.invalidate(posConfigProvider);
          },
          onPinChanged: (pin) async {
            await store.savePinHash(PosPinCode.hash(pin, salt: 'leopardo-pos'));
            ref.invalidate(posPinHashProvider);
          },
          probe: (url) => checkPosBackendHealth(
            apiBaseUrl: url,
            performRequest: (uri) async {
              final dio = Dio(
                BaseOptions(
                  connectTimeout: const Duration(seconds: 8),
                  receiveTimeout: const Duration(seconds: 8),
                ),
              );

              try {
                final response = await dio.get<dynamic>(uri.toString());

                return response.statusCode ?? 0;
              } on DioException catch (error) {
                final status = error.response?.statusCode;

                if (status != null) {
                  return status;
                }

                if (error.type == DioExceptionType.connectionTimeout ||
                    error.type == DioExceptionType.receiveTimeout) {
                  throw const PosHealthTimeout();
                }

                rethrow;
              }
            },
          ),
        ),
      ),
    );
  }

  /// Ouvre le choix de l'imprimante (#9264).
  Future<void> _openPrinterPicker() async {
    final store = ref.read(posLocalStoreProvider);
    final service = ref.read(posPrinterProvider);

    await Navigator.of(context).push<PosPrinterDevice>(
      MaterialPageRoute(
        builder: (_) => PosPrinterPickerScreen(
          service: service,
          onSelected: (device) async {
            await store.savePrinter(id: device.id, name: device.name);
          },
        ),
      ),
    );
  }

  /// Ouvre le scanner camera partage (boissons et produits emballes).
  Future<void> _openScanner() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(
        builder: (_) => PosBarcodeScannerScreen(
          title: 'Scanner un article',
          hint: 'Scannez une boisson ou un produit emballe.',
          accentColor: Theme.of(context).colorScheme.primary,
          cardRadius: PosTheme.cardRadius,
        ),
      ),
    );

    if (code == null || !mounted) {
      return;
    }

    _handleScan(code);
  }

  /// Met la commande en cours de cote.
  Future<void> _openPark() async {
    final label = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const PosParkTicketSheet(
        suggestions: ['Table occupee', 'Client au bar', 'Commande a emporter'],
      ),
    );

    if (label == null || !mounted) {
      return;
    }

    final ok = ref.read(posControllerProvider.notifier).parkCurrentTicket(label);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(ok ? 'Commande mise en attente.' : 'Mise en attente impossible.'),
      ),
    );
  }

  /// Rappelle une commande mise de cote.
  Future<void> _openRecall() async {
    final state = ref.read(posControllerProvider);

    final id = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => PosRecallTicketSheet(tickets: state.parked),
    );

    if (id == null || !mounted) {
      return;
    }

    final ok = ref.read(posControllerProvider.notifier).recallTicket(id);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(ok ? 'Commande rappelee.' : 'Rappel impossible.')),
    );
  }

  /// Apercu du ticket avant encaissement.
  Future<void> _openReceipt() async {
    final state = ref.read(posControllerProvider);

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => PosReceiptPreview(
        receipt: state.receipt,
        onPrint: () => _printReceipt(state.receipt),
        onConfirm: () {
          Navigator.of(context).pop();
          _pay(context, ref.read(posControllerProvider.notifier), 'cash');
        },
      ),
    );
  }

  /// Imprime le ticket sur l'imprimante thermique (#9264).
  ///
  /// Sans imprimante configuree, on propose d'en choisir une au lieu de
  /// laisser l'operateur devant un echec incomprehensible.
  Future<void> _printReceipt(PosReceipt receipt) async {
    final service = ref.read(posPrinterProvider);

    if (service.selectedPrinter == null) {
      final go = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Aucune imprimante'),
          content: const Text(
            'Aucune imprimante n\u2019est configuree sur cette tablette. '
            'Voulez-vous en choisir une maintenant ?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Plus tard'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Choisir'),
            ),
          ],
        ),
      );

      if (go != true || !mounted) {
        return;
      }

      await _openPrinterPicker();

      if (!mounted || service.selectedPrinter == null) {
        return;
      }
    }

    final bytes = const PosEscPosGenerator().render(
      receipt: receipt,
      shopName: 'Leopardo Restaurant',
      ticketNumber: receipt.tableLabel,
    );

    final outcome = await service.print(bytes);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(outcome.message),
        backgroundColor: outcome.success ? null : Theme.of(context).colorScheme.error,
      ),
    );
  }

  /// Annule une ligne avec un motif obligatoire.
  Future<void> _voidLine(CartLine line) async {
    final result = await showModalBottomSheet<({PosVoidReason reason, String comment})>(
      context: context,
      isScrollControlled: true,
      builder: (_) => PosVoidReasonSheet(itemName: line.product.name),
    );

    if (result == null || !mounted) {
      return;
    }

    ref.read(posControllerProvider.notifier).voidLine(
          productId: line.product.id,
          reason: result.reason,
          comment: result.comment.isEmpty ? null : result.comment,
        );
  }

  /// Remise depuis un raccourci clavier.
  Future<void> _openDiscount() async {
    final state = ref.read(posControllerProvider);

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
      ref.read(posControllerProvider.notifier).setDiscount(value);
    }
  }

  /// Pourboire depuis un raccourci clavier.
  Future<void> _openTip() async {
    final state = ref.read(posControllerProvider);

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
      ref.read(posControllerProvider.notifier).setTip(value);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(posControllerProvider);
    final controller = ref.read(posControllerProvider.notifier);

    return Focus(
      autofocus: true,
      onKeyEvent: _onKey,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Caisse — Restaurant'),
          actions: [
            PosConnectionChip(offline: state.offline, pending: state.pendingOperations),
            const SizedBox(width: 8),
            IconButton(
              tooltip: 'Scanner un article (F2)',
              onPressed: _openScanner,
              icon: const Icon(Icons.qr_code_scanner),
            ),
            IconButton(
              tooltip: 'Mettre en attente (F6)',
              onPressed: state.cart.isEmpty ? null : _openPark,
              icon: Badge(
                isLabelVisible: state.parked.length > 0,
                label: Text('${state.parked.length}'),
                child: const Icon(Icons.pause_circle_outline),
              ),
            ),
            IconButton(
              tooltip: 'Rappeler une commande (F7)',
              onPressed: _openRecall,
              icon: const Icon(Icons.playlist_play),
            ),
            IconButton(
              tooltip: 'Apercu du ticket (F8)',
              onPressed: state.cart.isEmpty ? null : _openReceipt,
              icon: const Icon(Icons.receipt_long),
            ),
            IconButton(
              key: const Key('pos-open-history'),
              tooltip: 'Historique des ventes',
              onPressed: _openHistory,
              icon: const Icon(Icons.history),
            ),
            IconButton(
              key: const Key('pos-open-settings'),
              tooltip: 'Reglages de la tablette',
              onPressed: _openSettings,
              icon: const Icon(Icons.settings_outlined),
            ),
            const SizedBox(width: 8),
          ],
        ),
        body: Column(
          children: [
            // Bandeau de synchronisation : progression, echecs, action
            // manuelle. Une file muette laisse le serveur dans le doute.
            if (state.offline || state.pendingOperations > 0 || state.sync.total > 0)
              PosSyncBanner(
                progress: state.sync,
                onSyncNow: controller.flushQueue,
                onDismissConflicts: controller.clearError,
              ),
            if (state.error != null)
              PosErrorBanner(
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
                            products: state.visibleProducts
                                .where(
                                  (product) =>
                                      _selectedCategoryId == null ||
                                      product.categoryId == _selectedCategoryId,
                                )
                                .toList(),
                            recentProducts: state.recentProducts,
                            favouritesOnly: state.showFavouritesOnly,
                            favouriteIds: {
                              for (final product in state.products)
                                if (state.affinity.isFavourite(product.id)) product.id,
                            },
                            searchController: _searchController,
                            onSearch: controller.setSearchQuery,
                            onScan: controller.scanBarcode,
                            onOpenScanner: _openScanner,
                            onTap: _onProductTap,
                            onToggleFavourite: controller.toggleFavourite,
                            onToggleFavouritesOnly: controller.toggleFavouritesOnly,
                            onClearSearch: () {
                              _searchController.clear();
                              controller.setSearchQuery('');
                            },
                          ),
                        ),
                        const VerticalDivider(width: 1),
                        SizedBox(
                          width: 420,
                          child: _CartPanel(
                            state: state,
                            onRemove: controller.removeProduct,
                            onSetQuantity: controller.setQuantity,
                            onOrderType: controller.setOrderType,
                            onTable: controller.selectTable,
                            onCovers: controller.setCovers,
                            onDiscount: controller.setDiscount,
                            onTip: controller.setTip,
                            onClear: controller.clearCart,
                            onPay: (method) => _pay(context, controller, method),
                            onSplitPay: () => _splitPay(context, controller),
                            onVoid: _voidLine,
                            onReceipt: _openReceipt,
                          ),
                        ),
                      ],
                    ),
            ),
          ],
        ),
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
  const _ProductGrid({
    required this.products,
    required this.recentProducts,
    required this.favouritesOnly,
    required this.favouriteIds,
    required this.searchController,
    required this.onSearch,
    required this.onScan,
    required this.onOpenScanner,
    required this.onTap,
    required this.onToggleFavourite,
    required this.onToggleFavouritesOnly,
    required this.onClearSearch,
  });

  final List<PosProduct> products;
  final List<PosProduct> recentProducts;
  final bool favouritesOnly;
  final Set<int> favouriteIds;
  final TextEditingController searchController;
  final void Function(String) onSearch;
  final bool Function(String) onScan;
  final VoidCallback onOpenScanner;
  final ValueChanged<PosProduct> onTap;
  final void Function(int) onToggleFavourite;
  final VoidCallback onToggleFavouritesOnly;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final searching = searchController.text.trim().isNotEmpty;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: searchController,
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
                    hintText: 'Rechercher un plat (nom, code, code-barres)',
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
              FilterChip(
                selected: favouritesOnly,
                onSelected: (_) => onToggleFavouritesOnly(),
                avatar: Icon(favouritesOnly ? Icons.star : Icons.star_border, size: 20),
                label: const Text('Favoris'),
              ),
              const SizedBox(width: 10),
              FilledButton.icon(
                onPressed: onOpenScanner,
                icon: const Icon(Icons.qr_code_scanner),
                label: const Text('Scanner'),
              ),
            ],
          ),
        ),
        // Bandeau « recents » : les plats habituels, accessibles en un appui.
        if (!searching && recentProducts.isNotEmpty)
          SizedBox(
            height: 56,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: 8, top: 6, bottom: 6),
                  child: Center(
                    child: Text(
                      'Recents',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ),
                for (final product in recentProducts)
                  Padding(
                    padding: const EdgeInsets.only(right: 8, top: 6, bottom: 6),
                    child: ActionChip(
                      avatar: const Icon(Icons.history, size: 18),
                      label: Text(product.name),
                      onPressed: () => onTap(product),
                    ),
                  ),
              ],
            ),
          ),
        Expanded(
          child: products.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          favouritesOnly ? Icons.star_border : Icons.restaurant_menu,
                          size: 56,
                          color: scheme.outline,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          searching
                              ? 'Aucun plat ne correspond'
                              : (favouritesOnly
                                  ? 'Aucun favori'
                                  : 'Aucun plat dans cette categorie'),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w700,
                            color: scheme.onSurface,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          searching
                              ? 'Aucun plat ne correspond a « ${searchController.text} ».'
                              : (favouritesOnly
                                  ? 'Appuyez longuement sur un plat pour l\'epingler ici.'
                                  : 'Choisissez une autre categorie du menu.'),
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 16, color: scheme.onSurfaceVariant),
                        ),
                        const SizedBox(height: 16),
                        if (searching)
                          OutlinedButton.icon(
                            onPressed: onClearSearch,
                            icon: const Icon(Icons.close),
                            label: const Text('Effacer la recherche'),
                          )
                        else if (favouritesOnly)
                          OutlinedButton.icon(
                            onPressed: onToggleFavouritesOnly,
                            icon: const Icon(Icons.grid_view),
                            label: const Text('Voir tous les plats'),
                          ),
                      ],
                    ),
                  ),
                )
              : GridView.builder(
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
                    final isFavourite = favouriteIds.contains(product.id);

                    return Semantics(
                      button: true,
                      label: '${product.name}, ${_formatMinor(product.priceMinor)}'
                          '${isFavourite ? ', favori' : ''}',
                      child: Card(
                        child: InkWell(
                          onTap: product.available ? () => onTap(product) : null,
                          // Appui long : epingle le plat en favori.
                          onLongPress: () => onToggleFavourite(product.id),
                          borderRadius: BorderRadius.circular(PosTheme.cardRadius),
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        product.name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          fontSize: 17,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                    if (isFavourite)
                                      Icon(Icons.star, size: 20, color: scheme.tertiary),
                                  ],
                                ),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      _formatMinor(product.priceMinor),
                                      style: TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.w700,
                                        color: scheme.primary,
                                      ),
                                    ),
                                    if (!product.available)
                                      Text(
                                        'Indisponible',
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                          color: scheme.error,
                                        ),
                                      ),
                                  ],
                                ),
                              ],
                            ),
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

/// Panneau du panier : lignes, type de commande, plan de salle, remise,
/// pourboire, encaissement.
class _CartPanel extends StatelessWidget {
  const _CartPanel({
    required this.state,
    required this.onRemove,
    required this.onSetQuantity,
    required this.onOrderType,
    required this.onTable,
    required this.onCovers,
    required this.onDiscount,
    required this.onTip,
    required this.onClear,
    required this.onPay,
    required this.onSplitPay,
    required this.onVoid,
    required this.onReceipt,
  });

  final PosState state;
  final ValueChanged<int> onRemove;
  final void Function(int, int) onSetQuantity;
  final ValueChanged<OrderType> onOrderType;
  final ValueChanged<int> onTable;
  final ValueChanged<int> onCovers;
  final ValueChanged<int> onDiscount;
  final ValueChanged<int> onTip;
  final VoidCallback onClear;
  final ValueChanged<String> onPay;
  final VoidCallback onSplitPay;
  final Future<void> Function(CartLine) onVoid;
  final Future<void> Function() onReceipt;

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

                    // Balayage vers la gauche = retirer la ligne ; appui long
                    // = menu d'actions (quantite, annulation motivee).
                    return PosSwipeableLine(
                      isFavourite: state.affinity.isFavourite(line.product.id),
                      onRemove: () => onRemove(line.product.id),
                      onAction: (action) {
                        switch (action) {
                          case PosLineAction.increase:
                            onSetQuantity(line.product.id, line.quantity + 1);
                          case PosLineAction.decrease:
                            onSetQuantity(line.product.id, line.quantity - 1);
                          case PosLineAction.setQuantity:
                            _editQuantity(context, line);
                          case PosLineAction.remove:
                            onRemove(line.product.id);
                          case PosLineAction.toggleFavourite:
                            onRemove(line.product.id);
                        }
                      },
                      child: ListTile(
                        title: Text(line.product.name),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${line.quantity} × ${_formatMinor(line.product.priceMinor)}',
                            ),
                            // Options du plat : affichees sous la ligne pour que
                            // le serveur et le client voient la cuisson, les
                            // supplements choisis et leur coût (unités mineures).
                            for (final option in line.optionLabels)
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Text(
                                  '  + $option',
                                  style: TextStyle(
                                    fontSize: 13,
                                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                                  ),
                                ),
                              ),
                          ],
                        ),
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
                            // Annulation motivee : la ligne sort du total mais
                            // reste tracee sur le ticket.
                            IconButton(
                              onPressed: () => onVoid(line),
                              icon: const Icon(Icons.block),
                              tooltip: 'Annuler la ligne (motif)',
                            ),
                          ],
                        ),
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
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: cart.isEmpty ? null : onReceipt,
                icon: const Icon(Icons.receipt_long),
                label: const Text('Apercu du ticket'),
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

  /// Saisie de quantite au pave numerique depuis le menu de ligne.
  Future<void> _editQuantity(BuildContext context, CartLine line) async {
    final value = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (_) => AmountEntrySheet(
        title: 'Quantite',
        label: 'Nombre de portions',
        initialMinor: line.quantity,
        helperText: line.product.name,
      ),
    );

    if (value != null) {
      onSetQuantity(line.product.id, value);
    }
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
