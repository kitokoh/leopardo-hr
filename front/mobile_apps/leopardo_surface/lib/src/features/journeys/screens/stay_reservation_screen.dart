import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

import '../../../core/i18n/app_strings.dart';
import '../../../core/providers/app_providers.dart';
import '../domain/journey_models.dart';
import '../providers/journey_providers.dart';
import '../widgets/journey_widgets.dart';

/// Parcours de réservation d'un séjour — `hospitality_property`.
///
/// ## L'ordre est imposé par le serveur, pas par l'ergonomie
///
/// 1. les dates, parce que la disponibilité en dépend (`?from&to`) ;
/// 2. les types de chambre RÉELLEMENT libres sur la période ;
/// 3. le voyageur, une fois le prix connu ;
/// 4. la confirmation, avec une référence et un code de suivi.
///
/// Demander les coordonnées avant de savoir s'il reste une chambre ferait
/// saisir des données personnelles pour un produit indisponible — et le code
/// de suivi, présenté une seule fois, serait perdu au premier retour arrière.
class StayReservationScreen extends ConsumerStatefulWidget {
  const StayReservationScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<StayReservationScreen> createState() =>
      _StayReservationScreenState();
}

enum _StayStep { period, rooms, guest, confirmation }

class _StayReservationScreenState extends ConsumerState<StayReservationScreen> {
  _StayStep _step = _StayStep.period;

  final TextEditingController _from = TextEditingController(
    text: isoDate(DateTime.now().add(const Duration(days: 1))),
  );
  final TextEditingController _to = TextEditingController(
    text: isoDate(DateTime.now().add(const Duration(days: 2))),
  );
  final TextEditingController _adults = TextEditingController(text: '1');
  final TextEditingController _guestName = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _children = TextEditingController(text: '0');

  bool _loading = false;
  bool _submitting = false;
  Object? _error;
  List<StayRoomOffer> _rooms = const [];
  StayRoomOffer? _selectedRoom;
  JourneyConfirmation? _confirmation;
  String? _idempotencyKey;

  @override
  void dispose() {
    _from.dispose();
    _to.dispose();
    _adults.dispose();
    _guestName.dispose();
    _email.dispose();
    _phone.dispose();
    _children.dispose();
    super.dispose();
  }

  Future<void> _checkAvailability() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final rooms = await ref.read(stayReservationGatewayProvider).availability(
            slug: widget.slug,
            from: _from.text.trim(),
            to: _to.text.trim(),
            adults: int.tryParse(_adults.text.trim()) ?? 1,
          );

      if (!mounted) return;
      setState(() {
        _rooms = rooms;
        _selectedRoom = null;
        _loading = false;
        _step = _StayStep.rooms;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  void _selectRoom(StayRoomOffer room) {
    if (!room.isBookable) return;
    _idempotencyKey ??= newJourneyIdempotencyKey();
    setState(() {
      _selectedRoom = room;
      _step = _StayStep.guest;
      _error = null;
    });
  }

  Future<void> _reserve() async {
    final room = _selectedRoom;
    if (room == null) return;

    final strings = ref.read(appStringsProvider);
    final hasContact =
        _email.text.trim().isNotEmpty || _phone.text.trim().isNotEmpty;

    if (_guestName.text.trim().isEmpty || !hasContact) {
      setState(() {
        _error = ArgumentError(
          _guestName.text.trim().isEmpty
              ? strings.t('journeyFieldRequired')
              : strings.t('journeyContactRequired'),
        );
      });
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final confirmation =
          await ref.read(stayReservationGatewayProvider).reserve(
                slug: widget.slug,
                roomTypeId: room.roomTypeId,
                guestName: _guestName.text,
                checkIn: _from.text.trim(),
                checkOut: _to.text.trim(),
                email: _email.text,
                phone: _phone.text,
                adults: int.tryParse(_adults.text.trim()) ?? 1,
                children: int.tryParse(_children.text.trim()) ?? 0,
                idempotencyKey: _idempotencyKey,
              );

      if (!mounted) return;
      setState(() {
        _confirmation = confirmation;
        _submitting = false;
        _step = _StayStep.confirmation;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = ref.watch(appStringsProvider);

    return JourneyScaffold(
      strings: strings,
      title: strings.t('journeyTitleStay'),
      child: switch (_step) {
        _StayStep.period => _periodView(strings),
        _StayStep.rooms => _roomsView(strings),
        _StayStep.guest => _guestView(strings),
        _StayStep.confirmation => _confirmationView(strings),
      },
    );
  }

  Widget _periodView(AppStrings strings) {
    String? dateValidator(String? value) {
      final text = value?.trim() ?? '';
      if (!isIsoDate(text)) return strings.t('journeyDateHint');
      return null;
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneyNotice(text: strings.t('journeyStayNotice')),
        JourneyField(
          controller: _from,
          label: strings.t('journeyFrom'),
          hint: 'AAAA-MM-JJ',
          validator: dateValidator,
        ),
        JourneyField(
          controller: _to,
          label: strings.t('journeyTo'),
          hint: 'AAAA-MM-JJ',
          validator: dateValidator,
        ),
        JourneyField(
          controller: _adults,
          label: strings.t('journeyAdults'),
          keyboardType: TextInputType.number,
        ),
        const SizedBox(height: 4),
        if (_error != null)
          JourneyErrorView(
            strings: strings,
            error: _friendly(_error!, strings),
          ),
        FilledButton(
          onPressed: _loading ? null : _checkAvailability,
          child: Text(
            _loading
                ? strings.t('stateLoading')
                : strings.t('journeyCheckAvailability'),
          ),
        ),
      ],
    );
  }

  Widget _roomsView(AppStrings strings) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_rooms.isEmpty) {
      return JourneyMessageView(
        icon: Icons.event_busy_outlined,
        title: strings.t('journeyRoomsEmptyTitle'),
        body: strings.t('journeyRoomsEmptyBody'),
        action: OutlinedButton.icon(
          onPressed: () => setState(() => _step = _StayStep.period),
          icon: const Icon(Icons.edit_calendar_outlined, size: 18),
          label: Text(strings.t('journeyChangeDates')),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        JourneySectionTitle(text: strings.t('journeyRoomsAvailable')),
        for (final room in _rooms)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ListTile(
              enabled: room.isBookable,
              title: Text(room.name),
              subtitle: Text(
                room.isBookable
                    ? '${strings.t('journeyRoomAvailable')} · ${room.available}'
                    : strings.t('journeyRoomSoldOut'),
                style: AppTypography.caption.copyWith(
                  color: room.isBookable
                      ? AppColors.textSecondaryFor(context)
                      : AppColors.danger,
                ),
              ),
              trailing: Text(
                formatMinorPrice(room.priceMinor, room.currency),
                style: AppTypography.title.copyWith(fontSize: 13),
              ),
              onTap: room.isBookable ? () => _selectRoom(room) : null,
            ),
          ),
        TextButton.icon(
          onPressed: () => setState(() => _step = _StayStep.period),
          icon: const Icon(Icons.edit_calendar_outlined, size: 18),
          label: Text(strings.t('journeyChangeDates')),
        ),
      ],
    );
  }

  Widget _guestView(AppStrings strings) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_selectedRoom != null)
          JourneyNotice(
            text:
                '${_selectedRoom!.name} · ${formatMinorPrice(_selectedRoom!.priceMinor, _selectedRoom!.currency)}',
          ),
        JourneySectionTitle(text: strings.t('journeyGuest')),
        JourneyField(
          controller: _guestName,
          label: strings.t('journeyGuestName'),
        ),
        JourneyField(
          controller: _email,
          label: strings.t('journeyCustomerEmail'),
          keyboardType: TextInputType.emailAddress,
        ),
        JourneyField(
          controller: _phone,
          label: strings.t('journeyCustomerPhone'),
          keyboardType: TextInputType.phone,
        ),
        JourneyField(
          controller: _children,
          label: strings.t('journeyChildren'),
          keyboardType: TextInputType.number,
        ),
        JourneyNotice(text: strings.t('journeyContactRequired')),
        if (_error != null)
          JourneyErrorView(
            strings: strings,
            error: _friendly(_error!, strings),
          ),
        FilledButton(
          onPressed: _submitting ? null : _reserve,
          child: Text(
            _submitting
                ? strings.t('stateLoading')
                : strings.t('journeySubmitBooking'),
          ),
        ),
      ],
    );
  }

  Widget _confirmationView(AppStrings strings) {
    final confirmation = _confirmation;
    if (confirmation == null) {
      // Ne devrait pas arriver (la confirmation précède cette étape) : mieux
      // vaut un écran honnête qu'un écran blanc.
      return JourneyMessageView(
        icon: Icons.event_busy_outlined,
        title: strings.t('journeyRoomsEmptyTitle'),
        body: strings.t('journeyRoomsEmptyBody'),
      );
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
        _ConfirmationRow(
          label: strings.t('journeyConfirmationReference'),
          value: confirmation.reference,
        ),
        if (confirmation.status != null)
          _ConfirmationRow(
            label: strings.t('journeyTrackStatus'),
            value: confirmation.status!,
          ),
        if (confirmation.totalMinor != null)
          _ConfirmationRow(
            label: strings.t('journeyTotal'),
            value: formatMinorPrice(
              confirmation.totalMinor!,
              confirmation.currency,
            ),
          ),
        if (confirmation.trackingCode != null) ...[
          _ConfirmationRow(
            label: strings.t('journeyConfirmationTrackingCode'),
            value: confirmation.trackingCode!,
          ),
          JourneyNotice(
            text: strings.t('journeyKeepCodeNotice'),
            color: AppColors.warning,
          ),
        ],
        const Divider(height: 32),
        JourneyTrackingPanel(
          strings: strings,
          initialReference: confirmation.reference,
          initialCode: confirmation.trackingCode,
          codeLabel: strings.t('journeyConfirmationTrackingCode'),
          onTrack: (reference, code) => ref
              .read(stayReservationGatewayProvider)
              .track(reference: reference, code: code),
        ),
      ],
    );
  }

  /// Un `ArgumentError` local porte un message destiné à la personne ; on
  /// l'affiche tel quel plutôt que de le classer en « erreur serveur ».
  Object _friendly(Object error, AppStrings strings) {
    if (error is ArgumentError) {
      final message = error.message;
      if (message is String && message.isNotEmpty) {
        return JourneyLocalFailure(message);
      }
      return JourneyLocalFailure(strings.t('journeyFieldRequired'));
    }
    return error;
  }
}

class _ConfirmationRow extends StatelessWidget {
  const _ConfirmationRow({required this.label, required this.value});

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
