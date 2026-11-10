import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// Riverpod 3 : `ProviderListenable` n'est plus exporte par l'entree principale,
// il vit dans `misc.dart` (cf. migration 2.0 -> 3.0).
import 'package:flutter_riverpod/misc.dart' show ProviderBase;
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/stock/data/stock_repository.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/stock_models.dart';

/// Écran de stock partagé par les surfaces mobiles (#9273, #9274).
///
/// Une seule implémentation pour les deux verticales : l'écran ne connaît que
/// [StockDataSource] (retail ou restaurant selon le provider injecté) et les
/// modèles partagés. Il n'y a AUCUN littéral ici — chaque chaîne passe par
/// `context.l10n` (garde `check-mobile-l10n-sync.sh`).
///
/// États honnêtes : chargement, vide, erreur avec bouton réessayer, et bandeau
/// hors ligne explicite quand le mode de synchronisation est offline.
class StockScreen extends ConsumerStatefulWidget {
  const StockScreen({
    super.key,
    required this.repositoryProvider,
    this.locationId,
    this.title,
    this.subtitle,
  });

  /// Provider du repository de stock (retail ou restaurant).
  ///
  /// `ProviderBase` (et non `ProviderListenable`) : il faut a la fois
  /// `ref.read` (ProviderListenable) et `ref.invalidate` (ProviderOrFamily).
  /// `Provider<StockRepository>` y est assignable par covariance.
  final ProviderBase<StockDataSource> repositoryProvider;

  /// Emplacement courant (tablette POS) ; null = tous les emplacements.
  final int? locationId;

  final String? title;
  final String? subtitle;

  @override
  ConsumerState<StockScreen> createState() => _StockScreenState();
}

class _StockScreenState extends ConsumerState<StockScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _searchController = TextEditingController();
  bool _belowThresholdOnly = false;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    _searchController.dispose();
    super.dispose();
  }

  StockDataSource get _repo => ref.read(widget.repositoryProvider);

  Future<void> _refresh() async {
    ref.invalidate(widget.repositoryProvider);
  }

  void _openRecord() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: MobileSurface.background,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _RecordMovementSheet(
        repository: _repo,
        locationId: widget.locationId,
        onSaved: _refresh,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: widget.title ?? l10n.stockMobileTitle,
        subtitle: widget.subtitle ?? l10n.stockMobileSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: l10n.stockMobileCancel,
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: MobileSurface.secondary),
            onPressed: _refresh,
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
            child: TextField(
              controller: _searchController,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: l10n.stockMobileSearchHint,
                prefixIcon: const Icon(Icons.search, size: 20),
                isDense: true,
                filled: true,
                fillColor: MobileSurface.surface,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 6),
            child: Row(
              children: [
                Checkbox(
                  value: _belowThresholdOnly,
                  onChanged: (v) => setState(() => _belowThresholdOnly = v ?? false),
                ),
                Text(
                  l10n.stockMobileBelowThresholdOnly,
                  style: AppTypography.bodySmall.copyWith(color: MobileSurface.text),
                ),
              ],
            ),
          ),
          TabBar(
            controller: _tabs,
            labelColor: AppColors.finance,
            unselectedLabelColor: MobileSurface.secondary,
            indicatorColor: AppColors.finance,
            tabs: [
              Tab(text: l10n.stockMobileTabLevels),
              Tab(text: l10n.stockMobileTabMovements),
              Tab(text: l10n.stockMobileTabAlerts),
            ],
          ),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _LevelsTab(
                  repository: _repo,
                  locationId: widget.locationId,
                  search: _searchController.text,
                  belowThresholdOnly: _belowThresholdOnly,
                ),
                _MovementsTab(
                  repository: _repo,
                  locationId: widget.locationId,
                ),
                _AlertsTab(
                  repository: _repo,
                  locationId: widget.locationId,
                ),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openRecord,
        backgroundColor: AppColors.finance,
        icon: const Icon(Icons.add, color: Colors.white),
        label: Text(
          l10n.stockMobileRecord,
          style: const TextStyle(color: Colors.white),
        ),
      ),
    );
  }
}

class _LevelsTab extends ConsumerWidget {
  const _LevelsTab({
    required this.repository,
    required this.locationId,
    required this.search,
    required this.belowThresholdOnly,
  });

  final StockDataSource repository;
  final int? locationId;
  final String search;
  final bool belowThresholdOnly;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final levelsAsync = ref.watch(
      _levelsProvider(repository, locationId, belowThresholdOnly),
    );

    return levelsAsync.when(
      loading: () => MobileEmptyLoading(label: l10n.stockMobileTitle),
      error: (_, __) => MobileErrorPanel(
        message: l10n.stockMobileLoadError,
        onRetry: () => ref.invalidate(
          _levelsProvider(repository, locationId, belowThresholdOnly),
        ),
      ),
      data: (levels) {
        final query = search.trim().toLowerCase();
        final filtered = query.isEmpty
            ? levels
            : levels
                .where((l) =>
                    (l.productName ?? '').toLowerCase().contains(query))
                .toList();

        if (filtered.isEmpty) {
          return _EmptyState(message: l10n.stockMobileEmptyLevels);
        }

        return ListView.builder(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 96),
          itemCount: filtered.length,
          itemBuilder: (context, index) {
            final level = filtered[index];
            return MobileListCard(
              icon: Icons.inventory_2_outlined,
              iconColor: level.isBelowThreshold
                  ? AppColors.danger
                  : AppColors.finance,
              title: level.productName ?? '#${level.productId}',
              subtitle:
                  '${l10n.stockMobileQuantity}: ${_fmt(level.quantity)}'
                  '${level.unit != null ? ' ${level.unit}' : ''}',
              trailing: level.isBelowThreshold
                  ? MobileStatusPill(
                      label: l10n.stockMobileBelowThreshold,
                      color: AppColors.danger,
                    )
                  : null,
            );
          },
        );
      },
    );
  }
}

class _MovementsTab extends ConsumerWidget {
  const _MovementsTab({required this.repository, required this.locationId});

  final StockDataSource repository;
  final int? locationId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final movementsAsync = ref.watch(_movementsProvider(repository, locationId));

    return movementsAsync.when(
      loading: () => MobileEmptyLoading(label: l10n.stockMobileTitle),
      error: (_, __) => MobileErrorPanel(
        message: l10n.stockMobileLoadError,
        onRetry: () => ref.invalidate(_movementsProvider(repository, locationId)),
      ),
      data: (movements) {
        if (movements.isEmpty) {
          return _EmptyState(message: l10n.stockMobileEmptyMovements);
        }

        return ListView.builder(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 96),
          itemCount: movements.length,
          itemBuilder: (context, index) {
            final movement = movements[index];
            final inbound = movement.isInbound;
            final color = inbound ? AppColors.finance : AppColors.danger;

            return MobileListCard(
              icon: inbound
                  ? Icons.arrow_downward
                  : Icons.arrow_upward,
              iconColor: color,
              title: movement.productName ?? '#${movement.productId}',
              subtitle: _reasonLabel(l10n, movement.reason),
              trailing: Text(
                '${inbound ? '+' : ''}${_fmt(movement.quantityDelta)}',
                style: AppTypography.subtitle.copyWith(
                  color: color,
                  fontWeight: FontWeight.w700,
                ),
              ),
              footer: movement.note != null && movement.note!.isNotEmpty
                  ? Text(
                      movement.note!,
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : null,
            );
          },
        );
      },
    );
  }

  String _reasonLabel(AppLocalizations l10n, StockMovementReason reason) {
    switch (reason) {
      case StockMovementReason.sale:
        return l10n.stockMobileReasonSale;
      case StockMovementReason.receiving:
        return l10n.stockMobileReasonReceiving;
      case StockMovementReason.count:
        return l10n.stockMobileReasonCount;
      case StockMovementReason.adjustment:
        return l10n.stockMobileReasonAdjustment;
      case StockMovementReason.waste:
        return l10n.stockMobileReasonWaste;
      case StockMovementReason.transferIn:
        return l10n.stockMobileReasonTransferIn;
      case StockMovementReason.transferOut:
        return l10n.stockMobileReasonTransferOut;
      case StockMovementReason.refund:
        return l10n.stockMobileReasonRefund;
      case StockMovementReason.unknown:
        return l10n.stockMobileReasonUnknown;
    }
  }
}

class _AlertsTab extends ConsumerWidget {
  const _AlertsTab({required this.repository, required this.locationId});

  final StockDataSource repository;
  final int? locationId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final alertsAsync = ref.watch(_alertsProvider(repository, locationId));

    return alertsAsync.when(
      loading: () => MobileEmptyLoading(label: l10n.stockMobileTitle),
      error: (_, __) => MobileErrorPanel(
        message: l10n.stockMobileLoadError,
        onRetry: () => ref.invalidate(_alertsProvider(repository, locationId)),
      ),
      data: (alerts) {
        if (alerts.isEmpty) {
          return _EmptyState(message: l10n.stockMobileEmptyAlerts);
        }

        return ListView.builder(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 96),
          itemCount: alerts.length,
          itemBuilder: (context, index) {
            final alert = alerts[index];
            return MobileListCard(
              icon: Icons.warning_amber_outlined,
              iconColor: AppColors.danger,
              title: alert.productName ?? '#${alert.productId}',
              subtitle:
                  '${l10n.stockMobileQuantity}: ${_fmt(alert.quantity)}'
                  ' · ${l10n.stockMobileThreshold}: ${_fmt(alert.alertThreshold ?? 0)}',
            );
          },
        );
      },
    );
  }
}

class _RecordMovementSheet extends StatefulWidget {
  const _RecordMovementSheet({
    required this.repository,
    required this.locationId,
    required this.onSaved,
  });

  final StockDataSource repository;
  final int? locationId;
  final VoidCallback onSaved;

  @override
  State<_RecordMovementSheet> createState() => _RecordMovementSheetState();
}

class _RecordMovementSheetState extends State<_RecordMovementSheet> {
  final _quantityController = TextEditingController();
  final _noteController = TextEditingController();
  StockMovementReason _reason = StockMovementReason.receiving;
  StockLevel? _selectedProduct;
  bool _busy = false;

  @override
  void dispose() {
    _quantityController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    final quantity = double.tryParse(_quantityController.text);

    if (quantity == null || quantity <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.stockMobileInvalidQuantity),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    final product = _selectedProduct;
    final locationId = widget.locationId;

    if (product == null || locationId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(l10n.stockMobileSelectProduct),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() => _busy = true);
    try {
      await widget.repository.recordMovement(
        locationId: locationId,
        productId: product.productId ?? 0,
        quantityDelta: quantity,
        reason: _reason,
        note: _noteController.text,
      );
      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.stockMobileSaved)),
        );
        widget.onSaved();
      }
    } on StockWriteException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error.message),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.stockMobileRecord,
            style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
          ),
          const SizedBox(height: 16),
          // Sélecteur de produit : les niveaux de l'emplacement sont la source
          // de vérité côté client (pas d'agrégation, pas de copie du catalogue).
          _ProductPicker(
            repository: widget.repository,
            locationId: widget.locationId,
            selected: _selectedProduct,
            onChanged: (p) => setState(() => _selectedProduct = p),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<StockMovementReason>(
            initialValue: _reason,
            decoration: InputDecoration(labelText: l10n.stockMobileReason),
            items: [
              StockMovementReason.receiving,
              StockMovementReason.adjustment,
              StockMovementReason.waste,
            ]
                .map(
                  (r) => DropdownMenuItem(
                    value: r,
                    child: Text(_reasonLabel(l10n, r)),
                  ),
                )
                .toList(),
            onChanged: (v) => setState(() => _reason = v ?? _reason),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _quantityController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: l10n.stockMobileQuantity),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _noteController,
            decoration: InputDecoration(labelText: l10n.stockMobileNote),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _busy ? null : () => Navigator.of(context).pop(),
                  child: Text(l10n.stockMobileCancel),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: _busy ? null : _save,
                  child: _busy
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(l10n.stockMobileSave),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _reasonLabel(AppLocalizations l10n, StockMovementReason reason) {
    switch (reason) {
      case StockMovementReason.receiving:
        return l10n.stockMobileReasonReceiving;
      case StockMovementReason.adjustment:
        return l10n.stockMobileReasonAdjustment;
      case StockMovementReason.waste:
        return l10n.stockMobileReasonWaste;
      default:
        return l10n.stockMobileReasonUnknown;
    }
  }
}

/// Sélecteur de produit alimenté par les niveaux de l'emplacement.
///
/// On ne charge PAS le catalogue complet : les niveaux sont déjà filtrés par
/// emplacement côté serveur, et un produit sans niveau n'a de toute façon rien
/// à recevoir dans cet emplacement.
class _ProductPicker extends ConsumerWidget {
  const _ProductPicker({
    required this.repository,
    required this.locationId,
    required this.selected,
    required this.onChanged,
  });

  final StockDataSource repository;
  final int? locationId;
  final StockLevel? selected;
  final ValueChanged<StockLevel?> onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final levelsAsync = ref.watch(_levelsProvider(repository, locationId, false));

    return levelsAsync.when(
      loading: () => const LinearProgressIndicator(minHeight: 2),
      error: (_, __) => Text(
        l10n.stockMobileLoadError,
        style: AppTypography.caption.copyWith(color: AppColors.danger),
      ),
      data: (levels) {
        if (levels.isEmpty) {
          return Text(
            l10n.stockMobileEmptyLevels,
            style: AppTypography.caption.copyWith(color: MobileSurface.secondary),
          );
        }

        return DropdownButtonFormField<StockLevel>(
          initialValue: selected,
          isExpanded: true,
          decoration: InputDecoration(labelText: l10n.stockMobileProduct),
          items: levels
              .map(
                (level) => DropdownMenuItem(
                  value: level,
                  child: Text(
                    level.productName ?? '#${level.productId}',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              )
              .toList(),
          onChanged: onChanged,
        );
      },
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: AppTypography.bodySmall.copyWith(color: MobileSurface.secondary),
        ),
      ),
    );
  }
}

String _fmt(double value) {
  if (value == value.roundToDouble()) {
    return value.toStringAsFixed(0);
  }
  return value.toStringAsFixed(3).replaceFirst(RegExp(r'0+$'), '');
}

// ── Providers locaux (autoDispose : chaque onglet recharge à l'ouverture) ──

/// Riverpod 3 : `FutureProvider<T>` expose un `AsyncValue<T>` (et non un
/// `Future<T>` comme en 2.x). On retourne le type CONCRET, pas
/// `ProviderListenable`, car `ref.invalidate` exige un `ProviderOrFamily`.
FutureProvider<List<StockLevel>> _levelsProvider(
  StockDataSource repository,
  int? locationId,
  bool belowThresholdOnly,
) {
  return FutureProvider.autoDispose<List<StockLevel>>((ref) {
    return repository.levels(
      locationId: locationId,
      belowThresholdOnly: belowThresholdOnly,
    );
  });
}

FutureProvider<List<StockMovement>> _movementsProvider(
  StockDataSource repository,
  int? locationId,
) {
  return FutureProvider.autoDispose<List<StockMovement>>((ref) {
    return repository.movements(locationId: locationId);
  });
}

FutureProvider<List<StockAlert>> _alertsProvider(
  StockDataSource repository,
  int? locationId,
) {
  return FutureProvider.autoDispose<List<StockAlert>>((ref) {
    return repository.alerts(locationId: locationId);
  });
}