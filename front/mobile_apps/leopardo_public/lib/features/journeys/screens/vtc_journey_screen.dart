import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/vertical_directory_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de la verticale **Mobilité** (vtc) — #9948.
///
/// ## Trois étapes : opérateur → course → suivi
///
/// La vitrine web `/operateurs` fait exactement cela : annuaire des opérateurs
/// opt-in, devis, demande de course invitée, suivi par référence + code.
///
/// ## Ce que l'écran n'invente pas
///
/// - **Aucun géocodage.** Le contrat exige `pickup.lat/lng` ET
///   `dropoff.lat/lng`. Sans coordonnées, un devis serait géographiquement
///   faux : l'écran demande donc des coordonnées explicites (ou la position de
///   l'appareil, avec consentement) plutôt que de fabriquer un point.
/// - **Le code de suivi n'est montré qu'une fois.** L'API ne le renvoie qu'à la
///   création (201) ; au rejeu idempotent (200) il est absent. L'écran le dit
///   au lieu d'afficher un champ vide.
/// - **Un devis sans prix n'est pas un devis.** L'écran affiche « prix à
///   confirmer », jamais « 0 » ni « gratuit ».
class VtcJourneyScreen extends ConsumerStatefulWidget {
  const VtcJourneyScreen({super.key});

  @override
  ConsumerState<VtcJourneyScreen> createState() => _VtcJourneyScreenState();
}

enum _VtcStep { operators, request, tracking }

class _VtcJourneyScreenState extends ConsumerState<VtcJourneyScreen> {
  _VtcStep _step = _VtcStep.operators;

  bool _loading = false;
  VerticalDirectory? _operators;
  Object? _loadError;
  VerticalEntry? _operator;

  final TextEditingController _pickup = TextEditingController();
  final TextEditingController _dropoff = TextEditingController();
  final TextEditingController _pickupAddress = TextEditingController();
  final TextEditingController _dropoffAddress = TextEditingController();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _phone = TextEditingController();

  bool _estimating = false;
  RideEstimate? _estimate;
  Object? _estimateError;

  bool _submitting = false;
  Object? _submitError;
  GuestRide? _ride;
  String? _idempotencyKey;

  @override
  void initState() {
    super.initState();
    Future.microtask(_loadOperators);
  }

  @override
  void dispose() {
    _pickup.dispose();
    _dropoff.dispose();
    _pickupAddress.dispose();
    _dropoffAddress.dispose();
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _loadOperators() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      final directory = await ref.read(verticalDirectoryGatewayProvider).directory(
            type: 'vtc_operator',
          );
      if (!mounted) return;
      setState(() {
        _operators = directory;
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

  /// Valide les deux points saisis, ou rend le message à afficher.
  ///
  /// Le format « lat,lng » est celui de la vitrine web : la parité de saisie
  /// évite qu'un utilisateur habitué au web ne comprenne pas l'app.
  String? _pointsError(AppStrings strings) {
    final from = GeoPoint.tryParseText(_pickup.text);
    final to = GeoPoint.tryParseText(_dropoff.text);
    if (from == null) return strings.t('vtcInvalidPickup');
    if (to == null) return strings.t('vtcInvalidDropoff');
    return null;
  }

  Future<void> _estimateRide() async {
    final operator = _operator;
    if (operator == null) return;

    final strings = ref.read(appStringsProvider);
    final error = _pointsError(strings);
    if (error != null) {
      setState(() => _estimateError = JourneyLocalFailure(error));
      return;
    }

    setState(() {
      _estimating = true;
      _estimateError = null;
    });

    try {
      final estimate = await ref.read(vtcGatewayProvider).estimate(
            operator: operator.slug,
            pickup: GeoPoint.tryParseText(_pickup.text)!,
            dropoff: GeoPoint.tryParseText(_dropoff.text)!,
          );
      if (!mounted) return;
      setState(() {
        _estimate = estimate;
        _estimating = false;
      });
    } catch (err) {
      if (!mounted) return;
      setState(() {
        _estimateError = err;
        _estimating = false;
      });
    }
  }

  Future<void> _requestRide() async {
    final operator = _operator;
    if (operator == null) return;

    final strings = ref.read(appStringsProvider);
    final error = _pointsError(strings);
    if (error != null) {
      setState(() => _submitError = JourneyLocalFailure(error));
      return;
    }

    if (_name.text.trim().isEmpty) {
      setState(
        () => _submitError = JourneyLocalFailure(strings.t('vtcMissingName')),
      );
      return;
    }
    if (_phone.text.trim().isEmpty) {
      setState(
        () => _submitError = JourneyLocalFailure(strings.t('vtcMissingPhone')),
      );
      return;
    }

    // La clé est créée ICI et conservée jusqu'au succès : un double tap ou un
    // retry réseau renvoie la même course, jamais un doublon.
    _idempotencyKey ??= newJourneyIdempotencyKey();

    setState(() {
      _submitting = true;
      _submitError = null;
    });

    try {
      final ride = await ref.read(vtcGatewayProvider).createRide(
            operator: operator.slug,
            idempotencyKey: _idempotencyKey!,
            pickup: GeoPoint.tryParseText(_pickup.text)!,
            dropoff: GeoPoint.tryParseText(_dropoff.text)!,
            passengerName: _name.text,
            passengerPhone: _phone.text,
            pickupAddress: _pickupAddress.text,
            dropoffAddress: _dropoffAddress.text,
          );
      if (!mounted) return;
      setState(() {
        _ride = ride;
        _submitting = false;
        _step = _VtcStep.tracking;
      });
    } catch (err) {
      if (!mounted) return;
      setState(() {
        _submitError = err;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyVerticalVtc'),
      child: switch (_step) {
        _VtcStep.operators => _operatorsView(strings),
        _VtcStep.request => _requestView(strings),
        _VtcStep.tracking => _trackingView(strings),
      },
    );
  }

  Widget _operatorsView(AppStrings strings) {
    final directory = _operators;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('vtcOperatorsNotice')),
        if (_loadError != null)
          JourneyErrorView(
            strings: strings,
            error: _loadError!,
            onRetry: _loadOperators,
          )
        else if (directory == null)
          const Center(child: CircularProgressIndicator())
        else if (!directory.available)
          JourneyMessageView(
            icon: Icons.cloud_off_outlined,
            title: strings.t('journeyDirectoryUnavailableTitle'),
            body: strings.t('journeyDirectoryUnavailableBody'),
            action: OutlinedButton.icon(
              onPressed: _loadOperators,
              icon: const Icon(Icons.refresh, size: 18),
              label: Text(strings.t('stateRetry')),
            ),
          )
        else if (directory.isEmpty)
          JourneyMessageView(
            icon: Icons.local_taxi_outlined,
            title: strings.t('vtcEmptyTitle'),
            body: strings.t('vtcEmptyBody'),
          )
        else
          for (final operator in directory.entries)
            Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                title: Text(
                  operator.name,
                  style: AppTypography.title.copyWith(fontSize: 14),
                ),
                subtitle: operator.location == null
                    ? null
                    : Text(
                        operator.location!,
                        style: AppTypography.caption.copyWith(
                          color: AppColors.textSecondaryFor(context),
                        ),
                      ),
                trailing: const Icon(Icons.chevron_right, size: 20),
                onTap: () => setState(() {
                  _operator = operator;
                  _step = _VtcStep.request;
                }),
              ),
            ),
      ],
    );
  }

  Widget _requestView(AppStrings strings) {
    final operator = _operator;
    if (operator == null) {
      return JourneyErrorView(strings: strings, error: _loadError ?? '');
    }

    final estimate = _estimate;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            IconButton(
              onPressed: () => setState(() => _step = _VtcStep.operators),
              icon: const Icon(Icons.arrow_back, size: 20),
            ),
            Expanded(child: JourneySectionTitle(text: operator.name)),
          ],
        ),
        JourneyNotice(text: strings.t('vtcCoordinatesNotice')),
        JourneyField(
          controller: _pickup,
          label: strings.t('vtcPickup'),
          hint: strings.t('vtcCoordinatesHint'),
        ),
        JourneyField(
          controller: _pickupAddress,
          label: strings.t('vtcPickupAddress'),
          hint: strings.t('vtcAddressHint'),
        ),
        JourneyField(
          controller: _dropoff,
          label: strings.t('vtcDropoff'),
          hint: strings.t('vtcCoordinatesHint'),
        ),
        JourneyField(
          controller: _dropoffAddress,
          label: strings.t('vtcDropoffAddress'),
          hint: strings.t('vtcAddressHint'),
        ),
        OutlinedButton.icon(
          onPressed: _estimating ? null : _estimateRide,
          icon: const Icon(Icons.calculate_outlined, size: 18),
          label: Text(
            _estimating
                ? strings.t('stateLoading')
                : strings.t('vtcEstimate'),
          ),
        ),
        if (_estimateError != null)
          JourneyErrorView(strings: strings, error: _estimateError!),
        if (estimate != null) ...[
          const SizedBox(height: 8),
          JourneyNotice(
            text: estimate.isPriced
                ? '${strings.t('vtcEstimatedPrice')} · '
                    '${formatMinorPrice(estimate.priceMinor!, estimate.currency)}'
                : strings.t('vtcPriceToConfirm'),
            color: estimate.isPriced ? AppColors.success : AppColors.warning,
            icon: Icons.payments_outlined,
          ),
          if (estimate.distanceM != null)
            Text(
              '${strings.t('vtcDistance')} · '
              '${(estimate.distanceM! / 1000).toStringAsFixed(1)} km',
              style: AppTypography.caption.copyWith(
                color: AppColors.textSecondaryFor(context),
              ),
            ),
        ],
        const Divider(height: 28),
        JourneySectionTitle(text: strings.t('journeyCustomer')),
        JourneyField(
          controller: _name,
          label: strings.t('journeyCustomerName'),
        ),
        JourneyField(
          controller: _phone,
          label: strings.t('journeyCustomerPhone'),
          keyboardType: TextInputType.phone,
        ),
        if (_submitError != null)
          JourneyErrorView(strings: strings, error: _submitError!),
        FilledButton.icon(
          onPressed: _submitting ? null : _requestRide,
          icon: const Icon(Icons.local_taxi, size: 18),
          label: Text(
            _submitting
                ? strings.t('stateLoading')
                : strings.t('vtcRequestRide'),
          ),
        ),
      ],
    );
  }

  Widget _trackingView(AppStrings strings) {
    final ride = _ride;
    if (ride == null) {
      return JourneyErrorView(strings: strings, error: _loadError ?? '');
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(
          text: strings.t('vtcRideRequested'),
          color: AppColors.success,
          icon: Icons.check_circle_outline,
        ),
        JourneySectionTitle(text: strings.t('journeyConfirmationTitle')),
        _KeyValue(
          label: strings.t('journeyConfirmationReference'),
          value: ride.reference,
        ),
        _KeyValue(
          label: strings.t('journeyStatusLabel'),
          value: ride.status,
        ),
        if (ride.pickupAddress != null)
          _KeyValue(
            label: strings.t('vtcPickupAddress'),
            value: ride.pickupAddress!,
          ),
        if (ride.dropoffAddress != null)
          _KeyValue(
            label: strings.t('vtcDropoffAddress'),
            value: ride.dropoffAddress!,
          ),
        if (ride.estimatedPriceMinor != null)
          _KeyValue(
            label: strings.t('vtcEstimatedPrice'),
            value: formatMinorPrice(
              ride.estimatedPriceMinor!,
              ride.currency,
            ),
          ),

        // Le code n'est rendu qu'à la création. Au rejeu idempotent il est
        // absent : on le dit, on n'affiche pas un champ vide.
        if (ride.trackingCode != null) ...[
          JourneyNotice(
            text: strings.t('vtcTrackingCodeNotice'),
            color: AppColors.warning,
          ),
          _KeyValue(
            label: strings.t('vtcTrackingCode'),
            value: ride.trackingCode!,
          ),
        ] else
          JourneyNotice(
            text: strings.t('vtcTrackingCodeMissing'),
            color: AppColors.warning,
            icon: Icons.info_outline,
          ),

        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          initialReference: ride.reference,
          codeLabel: strings.t('vtcTrackingCode'),
          onTrack: (reference, code) => ref
              .read(vtcGatewayProvider)
              .track(reference: reference, code: code)
              .then(
                (tracked) => JourneyStatusReport(
                  reference: tracked.reference,
                  status: tracked.status,
                  fields: {
                    if (tracked.pickupAddress != null)
                      'pickup': tracked.pickupAddress!,
                    if (tracked.dropoffAddress != null)
                      'dropoff': tracked.dropoffAddress!,
                    if (tracked.estimatedPriceMinor != null)
                      'price': formatMinorPrice(
                        tracked.estimatedPriceMinor!,
                        tracked.currency,
                      ),
                  },
                  events: const [],
                ),
              ),
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
