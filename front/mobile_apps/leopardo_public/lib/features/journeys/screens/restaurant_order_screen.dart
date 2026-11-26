import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/journey_models.dart';
import '../domain/restaurant_order_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Commande en ligne d'un restaurant public — #9959.
///
/// ## Pourquoi ce parcours existe
///
/// Le registre mobile déclarait la verticale `restaurant` bloquée en `order`,
/// au motif que la boutique publique exige un jeton par tenant. Ce motif
/// décrivait RESTO-805 (#6226) et il est **périmé** : depuis RESTO-902
/// (#7747), `POST /public/restaurants/{slug}/orders` sert la commande sans
/// aucun jeton, et c'est ce que consomme la vitrine web `/restaurants/{slug}`.
///
/// ## Trois étapes, un seul écran
///
/// Menu → panier → confirmation. Le même raisonnement que `TravelJourneyScreen`
/// et `RetailOrderScreen` : des écrans séparés perdraient le panier au retour
/// arrière, et chaque transition coûterait un rechargement du menu.
///
/// ## Ce que l'écran n'invente pas
///
/// - Aucun montant n'est envoyé : prix et TVA sont calculés serveur. Le total
///   affiché avant envoi est la somme des prix reçus, et le montant qui fait
///   foi est celui rendu à la création.
/// - Un produit sans prix publié n'affiche pas « 0 » : il affiche que le prix
///   sera confirmé, et il ne compte pas dans l'estimation.
/// - Le `tracking_secret` n'est rendu qu'à la création réelle : au rejeu
///   idempotent l'écran dit que le suivi se fait avec le secret déjà obtenu.
class RestaurantOrderScreen extends ConsumerStatefulWidget {
  const RestaurantOrderScreen({super.key, required this.slug});

  /// Slug public de l'établissement.
  final String slug;

  @override
  ConsumerState<RestaurantOrderScreen> createState() =>
      _RestaurantOrderScreenState();
}

enum _RestaurantStep { menu, cart, confirmation }

class _RestaurantOrderScreenState extends ConsumerState<RestaurantOrderScreen> {
  _RestaurantStep _step = _RestaurantStep.menu;

  bool _loading = true;
  Object? _loadError;
  RestaurantPublicProfile? _profile;

  /// Panier : code produit → ligne. Une `Map` indexée par code évite les
  /// doublons qu'une `List` laisserait passer (deux fois le même produit).
  final Map<String, RestaurantCartLine> _cart = {};

  final TextEditingController _customerName = TextEditingController();
  final TextEditingController _customerPhone = TextEditingController();
  final TextEditingController _note = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  RestaurantOrderType _orderType = RestaurantOrderType.pickup;

  bool _submitting = false;
  Object? _submitError;
  RestaurantOrderResult? _order;
  String? _idempotencyKey;

  // Suivi
  final TextEditingController _trackReference = TextEditingController();
  final TextEditingController _trackSecret = TextEditingController();
  bool _tracking = false;
  Object? _trackError;
  RestaurantOrderTracking? _trackingResult;

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _customerName.dispose();
    _customerPhone.dispose();
    _note.dispose();
    _trackReference.dispose();
    _trackSecret.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final profile =
          await ref.read(restaurantOrderGatewayProvider).profile(widget.slug);
      if (!mounted) return;
      setState(() {
        _profile = profile;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  // ── Panier ──

  void _add(RestaurantMenuProduct product) {
    final existing = _cart[product.code];
    final next = (existing?.quantity ?? 0) + 1;
    if (next > restaurantMaxLineQuantity) return;

    setState(() {
      _cart[product.code] = RestaurantCartLine(
        product: product,
        quantity: next,
        note: existing?.note,
      );
    });
  }

  void _remove(RestaurantMenuProduct product) {
    final existing = _cart[product.code];
    if (existing == null) return;

    setState(() {
      if (existing.quantity <= 1) {
        _cart.remove(product.code);
      } else {
        _cart[product.code] =
            existing.copyWith(quantity: existing.quantity - 1);
      }
    });
  }

  int get _cartCount =>
      _cart.values.fold(0, (total, line) => total + line.quantity);

  /// Estimation locale du total.
  ///
  /// `null` dès qu'une ligne n'a pas de prix publié : additionner en ignorant
  /// le prix manquant donnerait un total inférieur au vrai, ce qui est pire
  /// que de ne rien annoncer.
  int? get _estimatedTotalMinor {
    var total = 0;
    for (final line in _cart.values) {
      final lineTotal = line.lineTotalMinor;
      if (lineTotal == null) return null;
      total += lineTotal;
    }
    return total;
  }

  String? get _currency {
    for (final line in _cart.values) {
      final currency = line.product.currency;
      if (currency != null) return currency;
    }
    return _profile?.currency;
  }

  // ── Commande ──

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_cart.isEmpty) return;

    // La clé est créée ICI et conservée jusqu'au succès : un double tap ou un
    // retry réseau renvoie la même commande, jamais un doublon.
    _idempotencyKey ??= newJourneyIdempotencyKey();

    setState(() {
      _submitting = true;
      _submitError = null;
    });

    try {
      final order = await ref.read(restaurantOrderGatewayProvider).createOrder(
            slug: widget.slug,
            customerName: _customerName.text,
            customerPhone: _customerPhone.text,
            lines: _cart.values.toList(),
            idempotencyKey: _idempotencyKey!,
            orderType: _orderType,
            note: _note.text,
          );

      if (!mounted) return;
      setState(() {
        _order = order;
        _submitting = false;
        _step = _RestaurantStep.confirmation;
      });

      // Le secret n'est rendu qu'à la création réelle : on pré-remplit le
      // panneau de suivi quand on l'a, sans écraser une saisie en cours.
      if (order.trackingSecret != null && _trackSecret.text.isEmpty) {
        _trackSecret.text = order.trackingSecret!;
      }
      if (_trackReference.text.isEmpty) {
        _trackReference.text = order.reference;
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitError = error;
        _submitting = false;
      });
    }
  }

  Future<void> _track() async {
    final reference = _trackReference.text.trim();
    final secret = _trackSecret.text.trim();
    if (reference.isEmpty || secret.isEmpty) return;

    setState(() {
      _tracking = true;
      _trackError = null;
    });

    try {
      final result = await ref.read(restaurantOrderGatewayProvider).track(
            slug: widget.slug,
            reference: reference,
            secret: secret,
          );
      if (!mounted) return;
      setState(() {
        _trackingResult = result;
        _tracking = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _trackError = error;
        _tracking = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: Text(strings.t('restaurantOrderTitle'))),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_loadError != null) {
      return Scaffold(
        appBar: AppBar(title: Text(strings.t('restaurantOrderTitle'))),
        body: Padding(
          padding: const EdgeInsets.all(16),
          child: JourneyErrorView(
            strings: strings,
            error: _loadError!,
            onRetry: _load,
          ),
        ),
      );
    }

    final profile = _profile;
    if (profile == null) {
      // 404 : l'établissement n'est pas publié. C'est une information, pas une
      // panne — et on ne propose pas « réessayer », qui ne changerait rien.
      return Scaffold(
        appBar: AppBar(title: Text(strings.t('restaurantOrderTitle'))),
        body: Padding(
          padding: const EdgeInsets.all(16),
          child: JourneyMessageView(
            icon: Icons.storefront_outlined,
            title: strings.t('stateNotFoundTitle'),
            body: strings.t('restaurantNotPublished'),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(profile.name),
        actions: [
          if (_step == _RestaurantStep.menu && _cartCount > 0)
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Center(
                child: Text(
                  '${strings.t('restaurantCart')} · $_cartCount',
                  style: AppTypography.bodySmall,
                ),
              ),
            ),
        ],
      ),
      body: switch (_step) {
        _RestaurantStep.menu => _menuView(strings, profile),
        _RestaurantStep.cart => _cartView(strings, profile),
        _RestaurantStep.confirmation => _confirmationView(strings),
      },
    );
  }

  Widget _menuView(AppStrings strings, RestaurantPublicProfile profile) {
    if (!profile.hasMenu) {
      // Branche publiée mais aucun produit en ligne : on le dit, plutôt que
      // d'afficher un menu vide qui ressemblerait à un bug.
      return Padding(
        padding: const EdgeInsets.all(16),
        child: JourneyNotice(
          text: strings.t('restaurantNoMenu'),
          color: AppColors.warning,
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (profile.description != null) ...[
          Text(profile.description!, style: AppTypography.bodySmall),
          const SizedBox(height: 12),
        ],
        if (profile.address != null || profile.city != null)
          _KeyValue(
            label: strings.t('journeyDirectoryCity'),
            value:
                [profile.address, profile.city].whereType<String>().join(', '),
          ),
        if (profile.ratingAvg != null && (profile.reviewsCount ?? 0) > 0)
          _KeyValue(
            label: strings.t('journeyDirectoryRating'),
            value:
                '★ ${profile.ratingAvg!.toStringAsFixed(1)} (${profile.reviewsCount})',
          ),
        const Divider(height: 28),
        for (final category in profile.menu) ...[
          JourneySectionTitle(text: category.name),
          for (final product in category.products)
            _ProductTile(
              product: product,
              quantity: _cart[product.code]?.quantity ?? 0,
              onAdd: () => _add(product),
              onRemove: () => _remove(product),
            ),
        ],
        const SizedBox(height: 16),
        if (_cartCount > 0)
          FilledButton.icon(
            onPressed: () => setState(() => _step = _RestaurantStep.cart),
            icon: const Icon(Icons.shopping_cart_outlined, size: 18),
            label: Text(
              '${strings.t('restaurantGoToCart')} · $_cartCount',
            ),
          ),
      ],
    );
  }

  Widget _cartView(AppStrings strings, RestaurantPublicProfile profile) {
    final total = _estimatedTotalMinor;

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          JourneySectionTitle(text: strings.t('restaurantCart')),
          for (final line in _cart.values)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(line.product.name),
              subtitle: Text(
                line.product.priceMinor == null
                    ? strings.t('restaurantPriceOnRequest')
                    : formatMinorPrice(
                        line.product.priceMinor!,
                        line.product.currency,
                      ),
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    onPressed: () => _remove(line.product),
                    icon: const Icon(Icons.remove_circle_outline, size: 20),
                  ),
                  Text('${line.quantity}'),
                  IconButton(
                    onPressed: () => _add(line.product),
                    icon: const Icon(Icons.add_circle_outline, size: 20),
                  ),
                ],
              ),
            ),
          const Divider(height: 28),

          // Le total n'est annoncé que s'il est calculable : un total partiel
          // serait plus trompeur qu'une absence de total.
          if (total != null)
            _KeyValue(
              label: strings.t('journeyTotal'),
              value: formatMinorPrice(total, _currency),
            )
          else
            JourneyNotice(
              text: strings.t('restaurantTotalOnRequest'),
              color: AppColors.warning,
            ),

          const Divider(height: 28),
          JourneySectionTitle(text: strings.t('restaurantOrderType')),
          Wrap(
            spacing: 8,
            children: [
              for (final type in RestaurantOrderType.values)
                ChoiceChip(
                  label: Text(strings.t('restaurantOrderType_${type.wire}')),
                  selected: _orderType == type,
                  onSelected: (_) => setState(() => _orderType = type),
                ),
            ],
          ),
          const SizedBox(height: 12),

          JourneySectionTitle(text: strings.t('journeyCustomer')),
          // `customer_name` et `customer_phone` sont REQUIS par le contrat :
          // sans eux le serveur répond 422, autant le dire tout de suite.
          JourneyField(
            controller: _customerName,
            label: strings.t('journeyCustomerName'),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? strings.t('journeyFieldRequired')
                : null,
          ),
          JourneyField(
            controller: _customerPhone,
            label: strings.t('journeyCustomerPhone'),
            keyboardType: TextInputType.phone,
            validator: (value) => (value == null || value.trim().isEmpty)
                ? strings.t('journeyFieldRequired')
                : null,
          ),
          JourneyField(
            controller: _note,
            label: strings.t('restaurantOrderNote'),
            maxLines: 2,
          ),

          if (_submitError != null)
            JourneyErrorView(strings: strings, error: _submitError!),

          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: Text(
              _submitting
                  ? strings.t('stateLoading')
                  : strings.t('restaurantSubmitOrder'),
            ),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: _submitting
                ? null
                : () => setState(() => _step = _RestaurantStep.menu),
            child: Text(strings.t('restaurantBackToMenu')),
          ),
        ],
      ),
    );
  }

  Widget _confirmationView(AppStrings strings) {
    final order = _order;
    if (order == null) {
      return JourneyErrorView(strings: strings, error: _loadError ?? '');
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(
          text: strings.t('journeyConfirmationSent'),
          color: AppColors.success,
          icon: Icons.check_circle_outline,
        ),
        JourneySectionTitle(text: strings.t('journeyConfirmationTitle')),
        _KeyValue(
          label: strings.t('journeyConfirmationReference'),
          value: order.reference,
        ),
        _KeyValue(
          label: strings.t('journeyTotal'),
          value: formatMinorPrice(order.totalMinor, order.currency),
        ),
        _KeyValue(
          label: strings.t('journeyTrackStatus'),
          value: order.status,
        ),

        // Le secret n'est rendu qu'à la création réelle. Au rejeu idempotent il
        // est absent : on le dit, plutôt que d'afficher un champ vide.
        if (order.trackingSecret != null)
          JourneyNotice(
            text: strings.t('restaurantKeepSecretNotice'),
            color: AppColors.warning,
          )
        else
          JourneyNotice(
            text: strings.t('restaurantReplayNotice'),
            color: AppColors.info,
          ),

        const Divider(height: 32),
        JourneySectionTitle(text: strings.t('journeyTrackTitle')),
        JourneyField(
          controller: _trackReference,
          label: strings.t('journeyConfirmationReference'),
        ),
        JourneyField(
          controller: _trackSecret,
          label: strings.t('restaurantTrackingSecret'),
        ),
        OutlinedButton.icon(
          onPressed: _tracking ? null : _track,
          icon: const Icon(Icons.travel_explore_outlined, size: 18),
          label: Text(strings.t('journeyTrack')),
        ),
        if (_tracking)
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (_trackError != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: JourneyErrorView(
              strings: strings,
              error: _trackError!,
              onRetry: _track,
            ),
          ),
        if (_trackingResult != null)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: JourneyStatusView(
              strings: strings,
              report: JourneyStatusReport(
                reference: _trackingResult!.reference,
                status: _trackingResult!.status,
                fields: {
                  'total': formatMinorPrice(
                    _trackingResult!.totalMinor,
                    _trackingResult!.currency,
                  ),
                },
                events: [
                  for (final line in _trackingResult!.items)
                    '${line.quantity} × ${line.name}',
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// Une ligne de produit du menu, avec son compteur.
class _ProductTile extends StatelessWidget {
  const _ProductTile({
    required this.product,
    required this.quantity,
    required this.onAdd,
    required this.onRemove,
  });

  final RestaurantMenuProduct product;
  final int quantity;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(product.name),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (product.description != null)
            Text(product.description!, style: AppTypography.caption),
          Text(
            product.priceMinor == null
                ? '—'
                : formatMinorPrice(product.priceMinor!, product.currency),
            style: AppTypography.caption.copyWith(
              color: AppColors.textSecondaryFor(context),
            ),
          ),
        ],
      ),
      trailing: quantity == 0
          ? IconButton(
              onPressed: onAdd,
              icon: const Icon(Icons.add_circle_outline),
            )
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  onPressed: onRemove,
                  icon: const Icon(Icons.remove_circle_outline, size: 20),
                ),
                Text('$quantity'),
                IconButton(
                  onPressed: onAdd,
                  icon: const Icon(Icons.add_circle_outline, size: 20),
                ),
              ],
            ),
    );
  }
}

class _KeyValue extends StatelessWidget {
  const _KeyValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label : ',
            style: AppTypography.bodySmall.copyWith(
              color: AppColors.textSecondaryFor(context),
            ),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: AppTypography.bodySmall.copyWith(
                color: AppColors.textPrimaryFor(context),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
