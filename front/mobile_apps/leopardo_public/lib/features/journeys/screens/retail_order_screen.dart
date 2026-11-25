import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../../discovery/data/public_discovery_repository.dart';
import '../domain/journey_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de commande d'une boutique — `retail_shop`.
///
/// ## Trois étapes, un seul écran
///
/// Catalogue → coordonnées → confirmation. Trois écrans séparés perdraient le
/// panier au retour arrière (le geste le plus fréquent : « je vérifie un
/// produit, je reviens »), et chaque transition coûterait un rechargement.
///
/// ## Ce que l'écran n'invente pas
///
/// - Le total affiché est la SOMME des prix reçus ; le montant qui fait foi est
///   celui que le serveur renvoie à la création (il applique stock, frais,
///   promotions). Les deux sont affichés séparément à la confirmation.
/// - Le paiement en ligne ouvre l'URL de paiement du serveur dans le
///   navigateur : l'app ne collecte jamais de données de carte.
/// - Le jeton de suivi n'est présenté qu'une fois : l'écran demande de le
///   conserver et pré-remplit le panneau de suivi avec.
class RetailOrderScreen extends ConsumerStatefulWidget {
  const RetailOrderScreen({super.key, required this.sellerSlug});

  final String sellerSlug;

  @override
  ConsumerState<RetailOrderScreen> createState() => _RetailOrderScreenState();
}

enum _RetailStep { catalog, checkout, confirmation }

class _RetailOrderScreenState extends ConsumerState<RetailOrderScreen> {
  _RetailStep _step = _RetailStep.catalog;

  bool _loading = true;
  Object? _loadError;
  List<JourneyOffer> _offers = const [];
  final Map<String, int> _quantities = {};

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _address = TextEditingController();
  final TextEditingController _city = TextEditingController();
  final TextEditingController _notes = TextEditingController();

  String _paymentMethod = 'cash';
  bool _submitting = false;
  Object? _submitError;
  JourneyConfirmation? _confirmation;

  /// Une clé par TENTATIVE de commande : un retry après erreur réseau réutilise
  /// la même, donc le serveur rend la commande existante au lieu d'en créer une
  /// deuxième.
  String? _idempotencyKey;

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _address.dispose();
    _city.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final offers = await ref
          .read(retailOrderGatewayProvider)
          .products(widget.sellerSlug);
      if (!mounted) return;
      setState(() {
        _offers = offers;
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

  List<JourneyCartLine> get _cart {
    final lines = <JourneyCartLine>[];
    for (final offer in _offers) {
      final quantity = _quantities[offer.id] ?? 0;
      if (quantity > 0 && offer.isPurchasable) {
        lines.add(JourneyCartLine(offer: offer, quantity: quantity));
      }
    }
    return lines;
  }

  int get _cartTotalMinor {
    var total = 0;
    for (final line in _cart) {
      total += line.lineTotalMinor;
    }
    return total;
  }

  String get _cartCurrency {
    for (final line in _cart) {
      if (line.offer.currency.isNotEmpty) return line.offer.currency;
    }
    return 'MAD';
  }

  void _changeQuantity(JourneyOffer offer, int delta) {
    if (!offer.isPurchasable) return;

    final current = _quantities[offer.id] ?? 0;
    final next = (current + delta).clamp(0, 999);
    setState(() {
      if (next == 0) {
        _quantities.remove(offer.id);
      } else {
        _quantities[offer.id] = next;
      }
    });
  }

  void _startCheckout() {
    _idempotencyKey ??= newJourneyIdempotencyKey();
    setState(() {
      _step = _RetailStep.checkout;
      _submitError = null;
    });
  }

  Future<void> _submit() async {
    final strings = ref.read(appStringsProvider);
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _submitError = null;
    });

    try {
      final confirmation =
          await ref.read(retailOrderGatewayProvider).placeOrder(
                sellerSlug: widget.sellerSlug,
                lines: _cart,
                customer: JourneyCustomer(
                  name: _name.text,
                  phone: _phone.text,
                  email: _email.text,
                ),
                delivery: JourneyAddress(
                  address: _address.text,
                  city: _city.text,
                  notes: _notes.text,
                ),
                paymentMethod: _paymentMethod,
                idempotencyKey: _idempotencyKey ?? newJourneyIdempotencyKey(),
              );

      if (!mounted) return;
      setState(() {
        _confirmation = confirmation;
        _submitting = false;
        _step = _RetailStep.confirmation;
      });

      if (confirmation.checkoutUrl != null) {
        // Le paiement se termine dans le navigateur ; l'app ne prétend pas
        // l'avoir encaissé. Le suivi reste disponible, y compris avant
        // confirmation du paiement.
        await _openCheckout(confirmation.checkoutUrl!, strings);
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitError = error;
        _submitting = false;
      });
    }
  }

  Future<void> _openCheckout(String url, AppStrings strings) async {
    final uri = Uri.tryParse(url);
    if (uri == null) return;

    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(strings.t('stateNotAvailable'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyTitleOrder'),
      bottomBar: _step == _RetailStep.catalog && _cart.isNotEmpty
          ? _CartBar(
              strings: strings,
              totalMinor: _cartTotalMinor,
              currency: _cartCurrency,
              onCheckout: _startCheckout,
            )
          : null,
      child: switch (_step) {
        _RetailStep.catalog => _catalog(strings),
        _RetailStep.checkout => _checkout(strings),
        _RetailStep.confirmation => _confirmationView(strings),
      },
    );
  }

  Widget _catalog(AppStrings strings) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_loadError != null) {
      return JourneyErrorView(
        strings: strings,
        error: _loadError!,
        onRetry: _load,
      );
    }

    if (_offers.isEmpty) {
      return JourneyErrorView(
        strings: strings,
        error: const PublicApiFailure(PublicFailureKind.notFound),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      itemCount: _offers.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return JourneyNotice(text: strings.t('journeyCatalogNotice'));
        }

        final offer = _offers[index - 1];
        final quantity = _quantities[offer.id] ?? 0;

        return Card(
          margin: const EdgeInsets.only(bottom: 10),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        offer.title,
                        style: AppTypography.title.copyWith(fontSize: 14),
                      ),
                      if (offer.subtitle != null)
                        Text(
                          offer.subtitle!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: AppTypography.caption.copyWith(
                            color: AppColors.textSecondaryFor(context),
                          ),
                        ),
                      if (offer.priceMinor != null)
                        Text(
                          formatMinorPrice(offer.priceMinor!, offer.currency),
                          style: AppTypography.title.copyWith(fontSize: 13),
                        ),
                      if (!offer.isPurchasable)
                        Text(
                          strings.t('journeyProductUnavailable'),
                          style: AppTypography.caption.copyWith(
                            color: AppColors.danger,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                if (offer.isPurchasable)
                  _QuantityStepper(
                    quantity: quantity,
                    onDecrease: () => _changeQuantity(offer, -1),
                    onIncrease: () => _changeQuantity(offer, 1),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _checkout(AppStrings strings) {
    String? required(String? value) => (value == null || value.trim().isEmpty)
        ? strings.t('journeyFieldRequired')
        : null;

    String? optionalEmail(String? value) {
      final text = value?.trim() ?? '';
      if (text.isEmpty) return null;
      return text.contains('@') ? null : strings.t('journeyFieldInvalidEmail');
    }

    return Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          JourneySectionTitle(text: strings.t('journeyCustomer')),
          JourneyField(
            controller: _name,
            label: strings.t('journeyCustomerName'),
            validator: required,
          ),
          JourneyField(
            controller: _phone,
            label: strings.t('journeyCustomerPhone'),
            keyboardType: TextInputType.phone,
            validator: required,
          ),
          JourneyField(
            controller: _email,
            label: strings.t('journeyCustomerEmail'),
            keyboardType: TextInputType.emailAddress,
            validator: optionalEmail,
          ),
          JourneySectionTitle(text: strings.t('journeyDelivery')),
          JourneyField(
            controller: _address,
            label: strings.t('journeyDeliveryAddress'),
            validator: required,
          ),
          JourneyField(
            controller: _city,
            label: strings.t('journeyDeliveryCity'),
            validator: required,
          ),
          JourneyField(
            controller: _notes,
            label: strings.t('journeyDeliveryNotes'),
            maxLines: 3,
          ),
          JourneySectionTitle(text: strings.t('journeyPaymentMethod')),
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: Text(strings.t('journeyPaymentCash')),
                selected: _paymentMethod == 'cash',
                onSelected: (_) => setState(() => _paymentMethod = 'cash'),
              ),
              ChoiceChip(
                label: Text(strings.t('journeyPaymentOnline')),
                selected: _paymentMethod == 'online',
                onSelected: (_) => setState(() => _paymentMethod = 'online'),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_submitError != null)
            JourneyErrorView(strings: strings, error: _submitError!),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: Text(
              _submitting
                  ? strings.t('stateLoading')
                  : strings.t('journeySubmitOrder'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _confirmationView(AppStrings strings) {
    final confirmation = _confirmation;
    if (confirmation == null) {
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
          value: confirmation.reference,
        ),
        if (confirmation.totalMinor != null)
          _KeyValue(
            label: strings.t('journeyTotal'),
            value: formatMinorPrice(
              confirmation.totalMinor!,
              confirmation.currency,
            ),
          ),
        if (confirmation.status != null)
          _KeyValue(
            label: strings.t('journeyPaymentStatus'),
            value: confirmation.status!,
          ),
        if (confirmation.trackingCode != null) ...[
          _KeyValue(
            label: strings.t('journeyConfirmationTrackingCode'),
            value: confirmation.trackingCode!,
          ),
          JourneyNotice(
            text: strings.t('journeyKeepCodeNotice'),
            color: AppColors.warning,
          ),
        ],
        if (confirmation.checkoutUrl != null)
          OutlinedButton.icon(
            onPressed: () => _openCheckout(confirmation.checkoutUrl!, strings),
            icon: const Icon(Icons.credit_card, size: 18),
            label: Text(strings.t('journeyPayNow')),
          ),
        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          initialReference: confirmation.reference,
          initialCode: confirmation.trackingCode,
          onTrack: (reference, code) => ref
              .read(retailOrderGatewayProvider)
              .track(reference: reference, token: code),
        ),
      ],
    );
  }
}

class _CartBar extends StatelessWidget {
  const _CartBar({
    required this.strings,
    required this.totalMinor,
    required this.currency,
    required this.onCheckout,
  });

  final AppStrings strings;
  final int totalMinor;
  final String currency;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                '${strings.t('journeyTotal')} · ${formatMinorPrice(totalMinor, currency)}',
                style: AppTypography.title.copyWith(fontSize: 14),
              ),
            ),
            FilledButton(
              onPressed: onCheckout,
              child: Text(strings.t('journeyCheckout')),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({
    required this.quantity,
    required this.onDecrease,
    required this.onIncrease,
  });

  final int quantity;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          onPressed: quantity == 0 ? null : onDecrease,
          icon: const Icon(Icons.remove_circle_outline, size: 20),
        ),
        Text('$quantity', style: AppTypography.title.copyWith(fontSize: 14)),
        IconButton(
          onPressed: onIncrease,
          icon: const Icon(Icons.add_circle_outline, size: 20),
        ),
      ],
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
