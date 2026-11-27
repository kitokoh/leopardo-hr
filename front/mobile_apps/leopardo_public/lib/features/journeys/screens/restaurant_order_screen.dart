import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import '../domain/restaurant_order_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Commande en ligne d'un restaurant public — #9959, parité web #9991..#9994.
///
/// ## Pourquoi ce parcours existe
///
/// Le registre mobile déclarait la verticale `restaurant` bloquée en `order`,
/// au motif que la boutique publique exige un jeton par tenant. Ce motif
/// décrivait RESTO-805 (#6226) et il est **périmé** : depuis RESTO-902
/// (#7747), `POST /public/restaurants/{slug}/orders` sert la commande sans
/// aucun jeton, et c'est ce que consomme la vitrine web `/restaurants/{slug}`.
///
/// ## Parité avec la vitrine web
///
/// La vitrine web (`RestaurantOrderPanel.tsx`) offre quatre choses que cet
/// écran n'avait pas, et qui sont ici rattrapées :
///
/// 1. **Recherche dans la carte + puces de catégories** — un restaurant publie
///    couramment 60 à 120 produits ; un `ListView` plat oblige à tout faire
///    défiler pour trouver « tiramisu ».
/// 2. **Avis clients** — lecture des avis publiés et dépôt d'un avis après une
///    commande servie (`order_ref` = preuve d'achat).
/// 3. **Paiement** — `RestaurantOrderGateway.pay()` existait mais n'était
///    appelé par aucun écran : la commande restait `pending` sans moyen de la
///    régler.
/// 4. **Suivi automatique** — le web interroge le statut toutes les 15 s ; ici
///    le suivi est relancé tant que le statut n'est pas terminal.
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
/// - Aucun état « payé » optimiste : le badge de paiement ne change qu'après
///   la réponse serveur.
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

  // ── Menu : recherche + catégorie active ──
  final TextEditingController _menuQuery = TextEditingController();
  String? _activeCategory;

  // ── Suivi ──
  final TextEditingController _trackReference = TextEditingController();
  final TextEditingController _trackSecret = TextEditingController();
  bool _tracking = false;
  Object? _trackError;
  RestaurantOrderTracking? _trackingResult;
  Timer? _trackTimer;

  // ── Paiement ──
  RestaurantPaymentMethod _paymentMethod =
      RestaurantPaymentMethod.cashOnDelivery;
  bool _paying = false;
  Object? _payError;
  bool _paid = false;
  String? _payIdempotencyKey;

  // ── Avis ──
  RestaurantReviewPage? _reviews;
  bool _reviewsLoading = false;
  Object? _reviewsError;
  final TextEditingController _reviewAuthor = TextEditingController();
  final TextEditingController _reviewComment = TextEditingController();
  final TextEditingController _reviewOrderRef = TextEditingController();
  int _reviewRating = 5;
  bool _reviewSubmitting = false;
  Object? _reviewError;
  bool _reviewSent = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _trackTimer?.cancel();
    _customerName.dispose();
    _customerPhone.dispose();
    _note.dispose();
    _trackReference.dispose();
    _trackSecret.dispose();
    _menuQuery.dispose();
    _reviewAuthor.dispose();
    _reviewComment.dispose();
    _reviewOrderRef.dispose();
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

      if (profile != null) {
        // Les avis ne bloquent pas l'affichage de la carte : une panne sur les
        // avis ne doit pas empêcher de commander.
        unawaited(_loadReviews());
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  // ── Avis ──

  Future<void> _loadReviews({bool append = false}) async {
    if (_reviewsLoading) return;

    setState(() {
      _reviewsLoading = true;
      _reviewsError = null;
    });

    try {
      final page = await ref.read(restaurantOrderGatewayProvider).reviews(
            widget.slug,
            page: append ? (_reviews?.currentPage ?? 1) + 1 : 1,
          );
      if (!mounted) return;
      setState(() {
        _reviews = append && _reviews != null
            ? RestaurantReviewPage(
                items: [..._reviews!.items, ...page.items],
                total: page.total,
                currentPage: page.currentPage,
                perPage: page.perPage,
              )
            : page;
        _reviewsLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _reviewsError = error;
        _reviewsLoading = false;
      });
    }
  }

  Future<void> _submitReview() async {
    final reference = _reviewOrderRef.text.trim();
    final author = _reviewAuthor.text.trim();
    final strings = ref.read(appStringsProvider);

    if (reference.isEmpty || author.isEmpty) {
      setState(() {
        _reviewError = JourneyLocalFailure(
          strings.t('restaurantReviewOrderRef'),
        );
      });
      return;
    }

    setState(() {
      _reviewSubmitting = true;
      _reviewError = null;
    });

    try {
      await ref.read(restaurantOrderGatewayProvider).submitReview(
            slug: widget.slug,
            orderRef: reference,
            rating: _reviewRating,
            authorName: author,
            comment: _reviewComment.text,
          );
      if (!mounted) return;
      setState(() {
        _reviewSubmitting = false;
        _reviewSent = true;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _reviewError = error;
        _reviewSubmitting = false;
      });
    }
  }

  /// Message d'un refus d'avis : 409 et 422 sont deux règles métier distinctes.
  String _reviewErrorText(AppStrings strings, Object error) {
    if (error is PublicApiFailure) {
      if (error.statusCode == 409) return strings.t('restaurantReviewAlready');
      if (error.statusCode == 422) {
        return strings.t('restaurantReviewNotEligible');
      }
    }
    if (error is JourneyLocalFailure) {
      return error.message;
    }
    return strings.t('stateErrorBody');
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
      if (_reviewOrderRef.text.isEmpty) {
        _reviewOrderRef.text = order.reference;
      }

      // Le suivi démarre tout seul : le client vient de commander, il attend
      // un statut, pas un bouton à trouver.
      unawaited(_track());
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

      _scheduleNextTrack(result.status);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _trackError = error;
        _tracking = false;
      });
    }
  }

  /// Relance le suivi tant que la commande n'est pas terminale.
  ///
  /// 15 s est la cadence de la vitrine web : assez réactif pour un service en
  /// salle, assez lent pour ne pas marteler une route publique limitée en
  /// débit. Le timer est annulé au `dispose()` — un `Timer` qui survit à
  /// l'écran appelle `setState` sur un widget démonté.
  void _scheduleNextTrack(String? status) {
    _trackTimer?.cancel();
    if (isRestaurantTerminalStatus(status)) return;

    _trackTimer = Timer(const Duration(seconds: 15), () {
      if (!mounted) return;
      unawaited(_track());
    });
  }

  // ── Paiement ──

  Future<void> _pay() async {
    final order = _order;
    if (order == null || _paying || _paid) return;

    // Clé stable par tentative : un double tap ou un retry réseau ne débite
    // pas deux fois.
    _payIdempotencyKey ??= newJourneyIdempotencyKey();

    setState(() {
      _paying = true;
      _payError = null;
    });

    try {
      await ref.read(restaurantOrderGatewayProvider).pay(
            slug: widget.slug,
            reference: order.reference,
            method: _paymentMethod,
            idempotencyKey: _payIdempotencyKey!,
          );
      if (!mounted) return;
      setState(() {
        _paying = false;
        _paid = true;
      });
      unawaited(_track());
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _payError = error;
        _paying = false;
      });
    }
  }

  // ── Menu filtré ──

  /// Catégories visibles selon la recherche et la puce active.
  ///
  /// Le filtrage est fait ici, pas dans le widget : il doit rester testable
  /// sans monter d'arbre, et il ne doit jamais toucher au panier (les
  /// quantités survivent à un changement de filtre).
  List<RestaurantMenuCategory> _visibleCategories(
      RestaurantPublicProfile profile) {
    final needle = _normalize(_menuQuery.text);
    final active = _activeCategory;

    final result = <RestaurantMenuCategory>[];

    for (final category in profile.menu) {
      if (active != null && category.name != active) continue;

      if (needle.isEmpty) {
        result.add(category);
        continue;
      }

      final categoryMatches = _normalize(category.name).contains(needle);
      final products = category.products
          .where((product) =>
              categoryMatches ||
              _normalize(product.name).contains(needle) ||
              _normalize(product.description ?? '').contains(needle))
          .toList();

      if (products.isNotEmpty) {
        result.add(
            RestaurantMenuCategory(name: category.name, products: products));
      }
    }

    return result;
  }

  /// Minuscules sans accents — « Crème brûlée » doit répondre à « creme ».
  static String _normalize(String value) {
    const from = 'àáâãäåçèéêëìíîïñòóôõöùúûüýÿœæ';
    const to = 'aaaaaaceeeeiiiinooooouuuuyyoa';

    final buffer = StringBuffer();
    for (final rune in value.toLowerCase().runes) {
      final char = String.fromCharCode(rune);
      final index = from.indexOf(char);
      buffer.write(index >= 0 ? to[index] : char);
    }
    return buffer.toString().trim();
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    if (_loading) {
      return JourneyScaffold(
        strings: strings,
        title: strings.t('restaurantOrderTitle'),
        child: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_loadError != null) {
      return JourneyScaffold(
        strings: strings,
        title: strings.t('restaurantOrderTitle'),
        child: Padding(
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
      return JourneyScaffold(
        strings: strings,
        title: strings.t('restaurantOrderTitle'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: JourneyMessageView(
            icon: Icons.storefront_outlined,
            title: strings.t('stateNotFoundTitle'),
            body: strings.t('restaurantNotPublished'),
          ),
        ),
      );
    }

    return JourneyScaffold(
      strings: strings,
      title: profile.name,
      child: switch (_step) {
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

    final visible = _visibleCategories(profile);
    final isFiltering =
        _menuQuery.text.trim().isNotEmpty || _activeCategory != null;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _ProfileHeader(strings: strings, profile: profile),
        const Divider(height: 28),

        // Recherche dans la carte : sur 100 produits, c'est le seul moyen
        // d'atteindre un plat sans tout faire défiler.
        TextField(
          controller: _menuQuery,
          onChanged: (_) => setState(() {}),
          decoration: InputDecoration(
            labelText: strings.t('restaurantMenuSearch'),
            hintText: strings.t('restaurantMenuSearchHint'),
            prefixIcon: const Icon(Icons.search, size: 20),
            suffixIcon: _menuQuery.text.isEmpty
                ? null
                : IconButton(
                    icon: const Icon(Icons.close, size: 18),
                    onPressed: () {
                      _menuQuery.clear();
                      setState(() {});
                    },
                  ),
            isDense: true,
          ),
        ),
        const SizedBox(height: 10),

        // Puces de catégories : sélection unique + « Tout ».
        SizedBox(
          height: 40,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(strings.t('restaurantMenuAll')),
                  selected: _activeCategory == null,
                  onSelected: (_) => setState(() => _activeCategory = null),
                ),
              ),
              for (final category in profile.menu)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(category.name),
                    selected: _activeCategory == category.name,
                    onSelected: (_) => setState(
                      () => _activeCategory = _activeCategory == category.name
                          ? null
                          : category.name,
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        if (visible.isEmpty && isFiltering)
          // Un filtre sans résultat n'est PAS un menu vide : deux messages
          // différents, sinon le client croit que la carte a disparu.
          JourneyNotice(
            text: strings.t('restaurantMenuNoMatch'),
            color: AppColors.info,
          )
        else
          for (final category in visible) ...[
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
            label: Text('${strings.t('restaurantGoToCart')} · $_cartCount'),
          ),

        const Divider(height: 36),
        _ReviewsSection(
          strings: strings,
          page: _reviews,
          loading: _reviewsLoading,
          error: _reviewsError,
          onRetry: () => _loadReviews(),
          onLoadMore: () => _loadReviews(append: true),
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

    final status = _trackingResult?.status ?? order.status;
    final terminal = isRestaurantTerminalStatus(status);

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
          value: status,
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

        // ── Paiement ──
        JourneySectionTitle(text: strings.t('restaurantPayTitle')),
        if (_paid)
          JourneyNotice(
            text: strings.t('restaurantPayDone'),
            color: AppColors.success,
            icon: Icons.paid_outlined,
          )
        else ...[
          Wrap(
            spacing: 8,
            children: [
              for (final method in RestaurantPaymentMethod.values)
                ChoiceChip(
                  label: Text(
                    method == RestaurantPaymentMethod.cashOnDelivery
                        ? strings.t('restaurantPayCash')
                        : strings.t('restaurantPayMobileMoney'),
                  ),
                  selected: _paymentMethod == method,
                  onSelected: (_) => setState(() => _paymentMethod = method),
                ),
            ],
          ),
          const SizedBox(height: 8),
          // La carte n'est pas proposée : le contrôleur ne l'ouvre pas, et un
          // bouton qui enverrait une valeur refusée serait un mensonge.
          Text(
            strings.t('restaurantPayCardUnavailable'),
            style: AppTypography.caption.copyWith(
              color: AppColors.textSecondaryFor(context),
            ),
          ),
          const SizedBox(height: 8),
          if (_payError != null)
            JourneyErrorView(strings: strings, error: _payError!),
          FilledButton.icon(
            onPressed: _paying ? null : _pay,
            icon: const Icon(Icons.payments_outlined, size: 18),
            label: Text(
              _paying
                  ? strings.t('stateLoading')
                  : strings.t('restaurantPayAction'),
            ),
          ),
        ],

        const Divider(height: 32),

        // ── Suivi ──
        JourneySectionTitle(text: strings.t('journeyTrackTitle')),
        JourneyNotice(
          text: terminal
              ? strings.t('restaurantTrackStopped')
              : strings.t('restaurantTrackAuto'),
          color: terminal ? AppColors.success : AppColors.info,
        ),
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

        const Divider(height: 32),

        // ── Avis post-commande ──
        JourneySectionTitle(text: strings.t('restaurantReviewsTitle')),
        if (_reviewSent)
          JourneyNotice(
            text: strings.t('restaurantReviewSent'),
            color: AppColors.success,
            icon: Icons.rate_review_outlined,
          )
        else ...[
          Text(
            strings.t('restaurantReviewYourRating'),
            style: AppTypography.bodySmall,
          ),
          Row(
            children: [
              for (var value = 1; value <= 5; value++)
                IconButton(
                  onPressed: () => setState(() => _reviewRating = value),
                  icon: Icon(
                    value <= _reviewRating ? Icons.star : Icons.star_border,
                    color: AppColors.warning,
                    size: 22,
                  ),
                ),
            ],
          ),
          JourneyField(
            controller: _reviewOrderRef,
            label: strings.t('restaurantReviewOrderRef'),
          ),
          JourneyField(
            controller: _reviewAuthor,
            label: strings.t('journeyCustomerName'),
          ),
          JourneyField(
            controller: _reviewComment,
            label: strings.t('restaurantReviewComment'),
            maxLines: 3,
          ),
          if (_reviewError != null)
            JourneyNotice(
              text: _reviewErrorText(strings, _reviewError!),
              color: AppColors.warning,
            ),
          OutlinedButton.icon(
            onPressed: _reviewSubmitting ? null : _submitReview,
            icon: const Icon(Icons.rate_review_outlined, size: 18),
            label: Text(
              _reviewSubmitting
                  ? strings.t('stateLoading')
                  : strings.t('restaurantReviewSubmit'),
            ),
          ),
        ],
      ],
    );
  }
}

/// En-tête de fiche : type, cuisines, note, adresse, état d'ouverture.
///
/// Ces informations venaient du contrat public mais n'étaient pas parsées :
/// la fiche mobile perdait le type d'établissement, les cuisines et les
/// horaires que la vitrine web affiche.
class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.strings, required this.profile});

  final AppStrings strings;
  final RestaurantPublicProfile profile;

  @override
  Widget build(BuildContext context) {
    final open = profile.isOpenAt(DateTime.now());

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (profile.description != null) ...[
          Text(profile.description!, style: AppTypography.bodySmall),
          const SizedBox(height: 10),
        ],

        if (profile.establishmentType != null ||
            profile.cuisineTypes.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              [
                if (profile.establishmentType != null)
                  profile.establishmentType!,
                if (profile.cuisineTypes.isNotEmpty)
                  '${strings.t('restaurantCuisinesLabel')} : '
                      '${profile.cuisineTypes.join(', ')}',
              ].join(' · '),
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
          ),

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

        // `null` = aucun horaire publié : on n'annonce pas un état d'ouverture
        // qu'on ne connaît pas.
        if (open != null)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(
              children: [
                Icon(
                  open ? Icons.schedule : Icons.lock_clock,
                  size: 16,
                  color: open ? AppColors.success : AppColors.warning,
                ),
                const SizedBox(width: 6),
                Text(
                  open
                      ? strings.t('restaurantOpenNow')
                      : strings.t('restaurantClosedNow'),
                  style: AppTypography.caption.copyWith(
                    color: open ? AppColors.success : AppColors.warning,
                  ),
                ),
              ],
            ),
          ),

        if (profile.hours.isNotEmpty) ...[
          const SizedBox(height: 12),
          JourneySectionTitle(text: strings.t('restaurantHoursTitle')),
          _HoursTable(strings: strings, profile: profile),
        ],
      ],
    );
  }
}

/// Horaires jour par jour, jour courant mis en avant.
class _HoursTable extends StatelessWidget {
  const _HoursTable({required this.strings, required this.profile});

  final AppStrings strings;
  final RestaurantPublicProfile profile;

  static const List<String> _dayKeys = [
    'journeyDayMonday',
    'journeyDayTuesday',
    'journeyDayWednesday',
    'journeyDayThursday',
    'journeyDayFriday',
    'journeyDaySaturday',
    'journeyDaySunday',
  ];

  @override
  Widget build(BuildContext context) {
    final today = (DateTime.now().weekday - 1) % 7;

    return Column(
      children: [
        for (final hour in profile.sortedHours)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    strings.t(_dayKeys[hour.dayOfWeek]),
                    style: AppTypography.bodySmall.copyWith(
                      fontWeight:
                          hour.dayOfWeek == today ? FontWeight.w700 : null,
                      color: hour.dayOfWeek == today
                          ? AppColors.textPrimaryFor(context)
                          : AppColors.textSecondaryFor(context),
                    ),
                  ),
                ),
                Text(
                  hour.isServed
                      ? '${hour.opensAt} – ${hour.closesAt}'
                      : strings.t('restaurantClosedDay'),
                  style: AppTypography.bodySmall.copyWith(
                    color: hour.isServed
                        ? AppColors.textPrimaryFor(context)
                        : AppColors.textSecondaryFor(context),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Liste des avis publiés, avec pagination explicite.
class _ReviewsSection extends StatelessWidget {
  const _ReviewsSection({
    required this.strings,
    required this.page,
    required this.loading,
    required this.error,
    required this.onRetry,
    required this.onLoadMore,
  });

  final AppStrings strings;
  final RestaurantReviewPage? page;
  final bool loading;
  final Object? error;
  final VoidCallback onRetry;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    final reviews = page?.items ?? const <RestaurantReview>[];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        JourneySectionTitle(text: strings.t('restaurantReviewsTitle')),
        if (loading && reviews.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (error != null && reviews.isEmpty)
          JourneyErrorView(strings: strings, error: error!, onRetry: onRetry)
        else if (reviews.isEmpty)
          JourneyNotice(
            text: strings.t('restaurantReviewsEmpty'),
            color: AppColors.info,
          )
        else
          for (final review in reviews)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          review.authorName,
                          style: AppTypography.bodySmall.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      Text(
                        '★ ${review.rating}/5',
                        style: AppTypography.caption.copyWith(
                          color: AppColors.warning,
                        ),
                      ),
                    ],
                  ),
                  if (review.comment != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        review.comment!,
                        style: AppTypography.bodySmall,
                      ),
                    ),
                  if (review.hasReply)
                    Container(
                      margin: const EdgeInsets.only(top: 6),
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppColors.tint(
                          context,
                          AppColors.textSecondaryFor(context),
                        ),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            strings.t('restaurantReviewReplyFrom'),
                            style: AppTypography.caption.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(review.reply!, style: AppTypography.caption),
                        ],
                      ),
                    ),
                ],
              ),
            ),
        if (page?.hasMore == true)
          OutlinedButton(
            onPressed: loading ? null : onLoadMore,
            child: Text(strings.t('restaurantReviewsLoadMore')),
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
