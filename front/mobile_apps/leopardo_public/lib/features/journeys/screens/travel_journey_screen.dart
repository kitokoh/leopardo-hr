import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/journey_models.dart';
import '../domain/travel_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de la verticale **Billets** (travel) — #9943.
///
/// ## Quatre étapes, un seul écran
///
/// Recherche → fiche trajet → passagers → confirmation. Le même raisonnement
/// que `RetailOrderScreen` : des écrans séparés perdraient la sélection au
/// retour arrière, et chaque transition coûterait un rechargement.
///
/// ## Ce que l'écran n'invente pas
///
/// - Le total affiché avant envoi est la SOMME des tarifs reçus ; le montant
///   qui fait foi est celui rendu par le serveur à la création.
/// - Un trajet sans places connues n'est pas déclaré complet, mais un trajet
///   à `available_seats: 0` l'est — et son bouton de réservation est désactivé.
/// - Le code de validation n'est présenté qu'une fois : l'écran demande de le
///   conserver et pré-remplit le panneau de suivi avec.
class TravelJourneyScreen extends ConsumerStatefulWidget {
  const TravelJourneyScreen({super.key, this.initialTripId});

  /// Ouvre directement la fiche d'un trajet (lien profond).
  final int? initialTripId;

  @override
  ConsumerState<TravelJourneyScreen> createState() =>
      _TravelJourneyScreenState();
}

enum _TravelStep { search, detail, passengers, confirmation, payment }

class _TravelJourneyScreenState extends ConsumerState<TravelJourneyScreen> {
  _TravelStep _step = _TravelStep.search;

  bool _loading = false;
  Object? _loadError;
  List<TravelTrip> _trips = const [];
  List<TravelCity> _cities = const [];

  TravelTrip? _selected;

  final TextEditingController _origin = TextEditingController();
  final TextEditingController _destination = TextEditingController();
  final TextEditingController _date = TextEditingController();

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final List<TextEditingController> _passengerNames = [TextEditingController()];
  TravelAgeCategory _ageCategory = TravelAgeCategory.adult;
  bool _notifyConsent = false;

  bool _submitting = false;
  Object? _submitError;
  TravelBookingConfirmation? _confirmation;
  String? _idempotencyKey;

  // ── Paiement en ligne (#9947) ──
  TravelPaymentProvider _paymentProvider = TravelPaymentProvider.momo;
  bool _paying = false;
  Object? _paymentError;
  TravelPaymentInitiation? _payment;
  String? _paymentIdempotencyKey;

  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      await _loadCities();
      if (widget.initialTripId != null) {
        await _openTrip(widget.initialTripId!);
      }
    });
  }

  @override
  void dispose() {
    _origin.dispose();
    _destination.dispose();
    _date.dispose();
    _email.dispose();
    _phone.dispose();
    for (final controller in _passengerNames) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _loadCities() async {
    try {
      final cities = await ref.read(travelGatewayProvider).cities();
      if (!mounted) return;
      setState(() => _cities = cities);
    } catch (_) {
      // L'autocomplétion est un confort : son échec ne doit pas empêcher la
      // recherche par identifiant de ville déjà connue, ni afficher une erreur
      // sur un écran qui n'a encore rien demandé.
    }
  }

  /// Résout un libellé saisi en identifiant de ville.
  ///
  /// Le contrat n'accepte que `origin_city_id` / `destination_city_id`
  /// (entiers) : envoyer un nom ferait échouer la validation. Un libellé
  /// inconnu rend `null` — la recherche est alors non filtrée sur ce critère,
  /// ce qui est préférable à une requête invalide.
  int? _cityId(String label) {
    final needle = label.trim().toLowerCase();
    if (needle.isEmpty) return null;
    for (final city in _cities) {
      if (city.name.toLowerCase() == needle && city.id > 0) return city.id;
    }
    return null;
  }

  Future<void> _search() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final date = _date.text.trim();
      final trips = await ref.read(travelGatewayProvider).searchTrips(
            originCityId: _cityId(_origin.text),
            destinationCityId: _cityId(_destination.text),
            date: date.isEmpty ? null : date,
          );
      if (!mounted) return;
      setState(() {
        _trips = trips;
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

  Future<void> _openTrip(int tripId) async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final trip = await ref.read(travelGatewayProvider).trip(tripId);
      if (!mounted) return;
      setState(() {
        _selected = trip;
        _loading = false;
        _step = _TravelStep.detail;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  List<TravelPassengerDraft> get _passengers {
    final classId = _selected?.defaultPrice?.classId ?? 0;
    return [
      for (final controller in _passengerNames)
        TravelPassengerDraft(
          fullName: controller.text,
          ageCategory: _ageCategory,
          classId: classId,
        ),
    ];
  }

  int get _estimatedTotalMinor {
    final price = _selected?.defaultPrice;
    if (price == null) return 0;
    var total = 0;
    for (final passenger in _passengers) {
      total += price.priceFor(passenger.ageCategory);
    }
    return total;
  }

  void _startPassengers() {
    // Garde de dernier recours : l'écran n'affiche pas le bouton sans tarif,
    // mais un appel direct (lien profond, futur bouton) ne doit pas produire
    // une réservation à `class_id: 0`.
    if (_selected?.defaultPrice == null) return;

    _idempotencyKey ??= newJourneyIdempotencyKey();
    setState(() {
      _step = _TravelStep.passengers;
      _submitError = null;
    });
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _submitting = true;
      _submitError = null;
    });

    try {
      final confirmation = await ref.read(travelGatewayProvider).book(
            tripId: _selected!.id,
            passengers: _passengers,
            idempotencyKey: _idempotencyKey ?? newJourneyIdempotencyKey(),
            contactEmail: _email.text,
            contactPhone: _phone.text,
            notifyConsent: _notifyConsent,
          );

      if (!mounted) return;
      setState(() {
        _confirmation = confirmation;
        _submitting = false;
        _step = _TravelStep.confirmation;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _submitError = error;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyVerticalTravel'),
      child: switch (_step) {
        _TravelStep.search => _searchView(strings),
        _TravelStep.detail => _detailView(strings),
        _TravelStep.passengers => _passengersView(strings),
        _TravelStep.confirmation => _confirmationView(strings),
        _TravelStep.payment => _paymentView(strings),
      },
    );
  }

  Widget _searchView(AppStrings strings) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('travelSearchNotice')),
        JourneyField(
          controller: _origin,
          label: strings.t('travelOrigin'),
          hint: strings.t('travelCityHint'),
        ),
        JourneyField(
          controller: _destination,
          label: strings.t('travelDestination'),
          hint: strings.t('travelCityHint'),
        ),
        JourneyField(
          controller: _date,
          label: strings.t('travelDate'),
          hint: strings.t('journeyDateHint'),
          keyboardType: TextInputType.datetime,
        ),
        FilledButton.icon(
          onPressed: _loading ? null : _search,
          icon: const Icon(Icons.search, size: 18),
          label: Text(
            _loading ? strings.t('stateLoading') : strings.t('travelSearch'),
          ),
        ),
        const SizedBox(height: 16),
        if (_loadError != null)
          JourneyErrorView(
            strings: strings,
            error: _loadError!,
            onRetry: _search,
          )
        else if (!_loading && _trips.isEmpty)
          JourneyMessageView(
            icon: Icons.directions_bus_outlined,
            title: strings.t('travelEmptyTitle'),
            body: strings.t('travelEmptyBody'),
          )
        else
          for (final trip in _trips) _tripCard(strings, trip),
      ],
    );
  }

  Widget _tripCard(AppStrings strings, TravelTrip trip) {
    final route = trip.route;
    final price = trip.priceFromMinor;

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        title: Text(
          route ?? strings.t('travelRouteUnknown'),
          style: AppTypography.title.copyWith(fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (trip.departureDate != null)
              Text(
                '${trip.departureDate} ${trip.departureTime ?? ''}'.trim(),
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            if (trip.agencyName != null)
              Text(
                trip.agencyName!,
                style: AppTypography.caption.copyWith(
                  color: AppColors.textSecondaryFor(context),
                ),
              ),
            if (!trip.isBookable)
              Text(
                strings.t('travelSoldOut'),
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
          ],
        ),
        trailing: price == null
            ? null
            : Text(
                formatMinorPrice(price, trip.currency),
                style: AppTypography.title.copyWith(fontSize: 13),
              ),
        onTap: () => _openTrip(trip.id),
      ),
    );
  }

  Widget _detailView(AppStrings strings) {
    final trip = _selected;
    if (trip == null) {
      return JourneyErrorView(strings: strings, error: _loadError ?? '');
    }

    final freeSeats = trip.seats.where((seat) => seat.isFree).length;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(
          text: trip.route ?? strings.t('travelRouteUnknown'),
        ),
        if (trip.departureDate != null)
          _KeyValue(
            label: strings.t('travelDeparture'),
            value: '${trip.departureDate} ${trip.departureTime ?? ''}'.trim(),
          ),
        if (trip.arrivalDate != null)
          _KeyValue(
            label: strings.t('travelArrival'),
            value: '${trip.arrivalDate} ${trip.arrivalTime ?? ''}'.trim(),
          ),
        if (trip.agencyName != null)
          _KeyValue(label: strings.t('travelAgency'), value: trip.agencyName!),
        if (trip.availableSeats != null)
          _KeyValue(
            label: strings.t('travelSeatsAvailable'),
            value: '${trip.availableSeats}',
          ),
        if (trip.seats.isNotEmpty)
          _KeyValue(
            label: strings.t('travelSeatsFree'),
            value: '$freeSeats / ${trip.seats.length}',
          ),
        const Divider(height: 28),
        JourneySectionTitle(text: strings.t('travelPrices')),
        for (final price in trip.prices)
          _KeyValue(
            label: '${strings.t('travelClass')} ${price.classId}',
            value:
                '${strings.t('travelAdult')} ${formatMinorPrice(price.adultPriceMinor, price.currency)} · '
                '${strings.t('travelChild')} ${formatMinorPrice(price.childPriceMinor, price.currency)}',
          ),
        const SizedBox(height: 16),
        if (!trip.isBookable)
          JourneyNotice(
            text: strings.t('travelSoldOut'),
            color: AppColors.danger,
            icon: Icons.event_busy_outlined,
          )
        else if (!trip.canBook)
          // Des places, mais aucun tarif publié : la réservation n'a pas de
          // classe à désigner. On le dit au lieu d'afficher un bouton qui
          // échouerait à coup sûr.
          JourneyNotice(
            text: strings.t('travelNoFare'),
            color: AppColors.warning,
            icon: Icons.price_change_outlined,
          )
        else
          FilledButton(
            onPressed: _startPassengers,
            child: Text(strings.t('travelBook')),
          ),
        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          codeLabel: strings.t('travelValidationCode'),
          onTrack: (reference, code) => ref
              .read(travelGatewayProvider)
              .track(reference: reference, code: code)
              .then(_toJourneyReport),
        ),
      ],
    );
  }

  /// Adapte un statut de réservation travel au panneau de suivi commun.
  JourneyStatusReport _toJourneyReport(TravelBookingStatus status) {
    final fields = <String, String>{
      'payment': status.paymentStatus,
      'passengers': '${status.passengerCount}',
      'total': formatMinorPrice(status.totalMinor, status.currency),
      if (status.tripCode != null) 'trip': status.tripCode!,
      if (status.departureDate != null)
        'departure':
            '${status.departureDate} ${status.departureTime ?? ''}'.trim(),
    };

    return JourneyStatusReport(
      reference: status.reference,
      status: status.status,
      fields: fields,
      events: [
        for (final ticket in status.tickets)
          '${ticket.number} · ${ticket.status}',
      ],
    );
  }

  Widget _passengersView(AppStrings strings) {
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
          JourneyNotice(text: strings.t('travelPassengerNotice')),
          JourneySectionTitle(text: strings.t('travelPassengers')),
          for (var index = 0; index < _passengerNames.length; index++)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: JourneyField(
                    controller: _passengerNames[index],
                    label: '${strings.t('travelPassenger')} ${index + 1}',
                    validator: required,
                  ),
                ),
                if (_passengerNames.length > 1)
                  IconButton(
                    onPressed: () => setState(() {
                      _passengerNames.removeAt(index).dispose();
                    }),
                    icon: const Icon(Icons.remove_circle_outline, size: 20),
                  ),
              ],
            ),
          if (_passengerNames.length < travelMaxPassengers)
            OutlinedButton.icon(
              onPressed: () => setState(() {
                _passengerNames.add(TextEditingController());
              }),
              icon: const Icon(Icons.add, size: 18),
              label: Text(strings.t('travelAddPassenger')),
            ),
          JourneySectionTitle(text: strings.t('travelAgeCategory')),
          Wrap(
            spacing: 8,
            children: [
              for (final age in TravelAgeCategory.values)
                ChoiceChip(
                  label: Text(strings.t('travelAge_${age.wire}')),
                  selected: _ageCategory == age,
                  onSelected: (_) => setState(() => _ageCategory = age),
                ),
            ],
          ),
          const SizedBox(height: 8),
          JourneySectionTitle(text: strings.t('journeyCustomer')),
          JourneyField(
            controller: _email,
            label: strings.t('journeyCustomerEmail'),
            keyboardType: TextInputType.emailAddress,
            validator: optionalEmail,
          ),
          JourneyField(
            controller: _phone,
            label: strings.t('journeyCustomerPhone'),
            keyboardType: TextInputType.phone,
          ),
          CheckboxListTile(
            value: _notifyConsent,
            onChanged: (value) =>
                setState(() => _notifyConsent = value ?? false),
            title: Text(
              strings.t('travelNotifyConsent'),
              style: AppTypography.bodySmall,
            ),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
          ),
          const SizedBox(height: 8),
          JourneyNotice(
            text:
                '${strings.t('journeyTotal')} · ${formatMinorPrice(_estimatedTotalMinor, _selected?.currency)}',
          ),
          if (_submitError != null)
            JourneyErrorView(strings: strings, error: _submitError!),
          FilledButton(
            onPressed: _submitting ? null : _submit,
            child: Text(
              _submitting
                  ? strings.t('stateLoading')
                  : strings.t('travelSubmitBooking'),
            ),
          ),
        ],
      ),
    );
  }

  /// Ouvre l'étape de paiement pour la réservation confirmée — #9947.
  ///
  /// La clé d'idempotence est créée ICI et conservée jusqu'au succès : un
  /// double tap ou un retry réseau renvoie le même paiement, jamais un doublon.
  void _startPayment() {
    final confirmation = _confirmation;
    if (confirmation == null) return;

    _paymentIdempotencyKey ??= newJourneyIdempotencyKey();
    setState(() {
      _step = _TravelStep.payment;
      _paymentError = null;
    });
  }

  Future<void> _pay() async {
    final confirmation = _confirmation;
    if (confirmation == null) return;

    setState(() {
      _paying = true;
      _paymentError = null;
    });

    try {
      final payment = await ref.read(travelGatewayProvider).initiatePayment(
            bookingReference: confirmation.reference,
            provider: _paymentProvider,
            idempotencyKey: _paymentIdempotencyKey ?? newJourneyIdempotencyKey(),
          );

      if (!mounted) return;
      setState(() {
        _payment = payment;
        _paying = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _paymentError = error;
        _paying = false;
      });
    }
  }

  Widget _paymentView(AppStrings strings) {
    final confirmation = _confirmation;
    if (confirmation == null) {
      return JourneyErrorView(strings: strings, error: _loadError ?? '');
    }

    final payment = _payment;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(text: strings.t('travelPaymentTitle')),
        _KeyValue(
          label: strings.t('journeyConfirmationReference'),
          value: confirmation.reference,
        ),
        _KeyValue(
          label: strings.t('journeyTotal'),
          value: formatMinorPrice(
            confirmation.totalMinor,
            confirmation.currency,
          ),
        ),
        const Divider(height: 28),

        // Paiement déjà initié : on montre la référence et le statut, et on
        // n'affiche plus le sélecteur — relancer un second paiement sur la
        // même réservation créerait une transaction concurrente.
        if (payment != null) ...[
          JourneyNotice(
            text: strings.t('travelPaymentInitiated'),
            color: AppColors.success,
            icon: Icons.receipt_long_outlined,
          ),
          _KeyValue(
            label: strings.t('travelPaymentReference'),
            value: payment.reference,
          ),
          _KeyValue(
            label: strings.t('journeyPaymentStatus'),
            value: payment.status,
          ),
          if (payment.providerReference != null)
            _KeyValue(
              label: strings.t('travelPaymentProviderReference'),
              value: payment.providerReference!,
            ),
          JourneyNotice(
            text: strings.t('travelPaymentPendingNotice'),
            color: AppColors.warning,
          ),
        ] else ...[
          JourneySectionTitle(text: strings.t('travelPaymentMethod')),
          Wrap(
            spacing: 8,
            children: [
              for (final provider in TravelPaymentProvider.values)
                ChoiceChip(
                  label: Text(strings.t('travelPayment_${provider.wire}')),
                  selected: _paymentProvider == provider,
                  onSelected: (_) =>
                      setState(() => _paymentProvider = provider),
                ),
            ],
          ),
          const SizedBox(height: 12),
          JourneyNotice(text: strings.t('travelPaymentNotice')),
          if (_paymentError != null)
            JourneyErrorView(strings: strings, error: _paymentError!),
          FilledButton.icon(
            onPressed: _paying ? null : _pay,
            icon: const Icon(Icons.lock_outline, size: 18),
            label: Text(
              _paying
                  ? strings.t('stateLoading')
                  : strings.t('travelPayNow'),
            ),
          ),
        ],

        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          initialReference: confirmation.reference,
          codeLabel: strings.t('travelValidationCode'),
          onTrack: (reference, code) => ref
              .read(travelGatewayProvider)
              .track(reference: reference, code: code)
              .then(_toJourneyReport),
        ),
      ],
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
        _KeyValue(
          label: strings.t('journeyTotal'),
          value: formatMinorPrice(
            confirmation.totalMinor,
            confirmation.currency,
          ),
        ),
        _KeyValue(
          label: strings.t('journeyPaymentStatus'),
          value: confirmation.paymentStatus,
        ),
        if (confirmation.agencyName != null)
          _KeyValue(
            label: strings.t('travelAgency'),
            value: confirmation.agencyName!,
          ),
        if (confirmation.expiresAt != null)
          _KeyValue(
            label: strings.t('travelExpiresAt'),
            value: confirmation.expiresAt!,
          ),
        JourneyNotice(
          text: strings.t('travelKeepCodeNotice'),
          color: AppColors.warning,
        ),
        const SizedBox(height: 8),
        // #9947 — paiement en ligne. Le bouton n'apparaît que si la
        // réservation attend encore un règlement : payer une réservation
        // déjà réglée créerait une seconde transaction.
        if (confirmation.paymentStatus == 'pending' ||
            confirmation.paymentStatus == 'unpaid')
          FilledButton.icon(
            onPressed: _startPayment,
            icon: const Icon(Icons.credit_card, size: 18),
            label: Text(strings.t('travelPayNow')),
          ),
        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          initialReference: confirmation.reference,
          codeLabel: strings.t('travelValidationCode'),
          onTrack: (reference, code) => ref
              .read(travelGatewayProvider)
              .track(reference: reference, code: code)
              .then(_toJourneyReport),
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
