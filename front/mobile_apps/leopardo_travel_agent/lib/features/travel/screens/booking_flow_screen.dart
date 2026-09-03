import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/data/travel_repository.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_booking.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_trip.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';
import 'package:qr_flutter/qr_flutter.dart';

/// Flux de vente guichet (TRAVEL-701/#6088) :
/// passagers → réservation (idempotente) → confirmation + encaissement cash
/// → émission des billets → check-in QR.
class BookingFlowScreen extends ConsumerStatefulWidget {
  const BookingFlowScreen({super.key, required this.tripId});

  final int tripId;

  @override
  ConsumerState<BookingFlowScreen> createState() => _BookingFlowScreenState();
}

class _PassengerDraft {
  _PassengerDraft();

  String fullName = '';
  String ageCategory = 'adult';
  int? classId;
  int? seatNumber;
}

class _BookingFlowScreenState extends ConsumerState<BookingFlowScreen> {
  final List<_PassengerDraft> _passengers = [_PassengerDraft()];
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  TravelBooking? _booking;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  String _idempotencyKey() {
    final random = Random.secure();
    final suffix = List.generate(
      8,
      (_) => random.nextInt(36).toRadixString(36),
    ).join();
    return 'app-${DateTime.now().millisecondsSinceEpoch}-$suffix';
  }

  Future<void> _createBooking(TravelTrip trip) async {
    final repository = ref.read(travelRepositoryProvider);
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    if (_passengers.isEmpty ||
        _passengers.any((p) => p.fullName.trim().isEmpty)) {
      setState(() {
        _error = l10n.t('fillRequired');
      });
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final booking = await repository.createBooking(
        tripId: widget.tripId,
        idempotencyKey: _idempotencyKey(),
        passengers: [
          for (final p in _passengers)
            BookingPassengerDraft(
              fullName: p.fullName.trim(),
              ageCategory: p.ageCategory,
              classId: p.classId ?? _firstClassId(trip),
              seatNumber: p.seatNumber,
            ),
        ],
        contactEmail: _emailController.text.trim(),
        contactPhone: _phoneController.text.trim(),
        notifyConsent: false,
      );
      setState(() {
        _booking = booking;
        _busy = false;
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('bookingError');
      });
    }
  }

  static int? _firstClassId(TravelTrip trip) {
    return trip.prices.isNotEmpty ? trip.prices.first.classId : null;
  }

  Future<void> _confirm(TravelTrip trip) async {
    final repository = ref.read(travelRepositoryProvider);
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    final booking = _booking;
    if (booking == null) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final confirmed = await repository.confirmBooking(booking.id);
      setState(() {
        _booking = confirmed;
        _busy = false;
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('confirmError');
      });
    }
  }

  Future<void> _issueTickets() async {
    final repository = ref.read(travelRepositoryProvider);
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    final booking = _booking;
    if (booking == null) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final updated = await repository.issueTickets(booking.id);
      setState(() {
        _booking = updated;
        _busy = false;
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('issueError');
      });
    }
  }

  Future<void> _checkIn(TravelTicket ticket) async {
    final repository = ref.read(travelRepositoryProvider);
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    final booking = _booking;
    if (booking == null) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await repository.checkInTicket(ticket.id!);
      final updated = await repository.listBookings().then((list) {
        for (final b in list) {
          if (b.id == booking.id) {
            return b;
          }
        }
        return booking;
      });
      setState(() {
        _booking = updated;
        _busy = false;
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('checkInError');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final tripState = ref.watch(tripProvider(widget.tripId));
    final text = AppColors.textPrimaryFor(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('newBookingTitle'))),
      body: SafeArea(
        child: tripState.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Text(l10n.t('loadError')),
          ),
          data: (trip) {
            final booking = _booking;
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (booking != null) ...[
                  _BookingSummary(booking: booking),
                  const SizedBox(height: 16),
                  if (booking.status == 'pending') ...[
                    FilledButton.icon(
                      onPressed: _busy ? null : () => _confirm(trip),
                      icon: const Icon(Icons.payments_outlined),
                      label: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Text(l10n.t('confirmBooking')),
                      ),
                    ),
                  ] else if (booking.paymentStatus == 'confirmed' &&
                      booking.tickets.isEmpty) ...[
                    FilledButton.icon(
                      onPressed: _busy ? null : _issueTickets,
                      icon: const Icon(Icons.confirmation_number_outlined),
                      label: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Text(l10n.t('issueTickets')),
                      ),
                    ),
                  ] else ...[
                    _TicketsSection(
                      booking: booking,
                      busy: _busy,
                      onCheckIn: _checkIn,
                    ),
                  ],
                ] else ...[
                  Text(
                    l10n.t('passenger'),
                    style: AppTypography.subtitle.copyWith(color: text),
                  ),
                  const SizedBox(height: 8),
                  for (var i = 0; i < _passengers.length; i++)
                    _PassengerCard(
                      index: i,
                      passenger: _passengers[i],
                      classes: trip.prices,
                      canRemove: _passengers.length > 1,
                      onRemove: () {
                        setState(() {
                          _passengers.removeAt(i);
                        });
                      },
                      onChange: () => setState(() {}),
                    ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: OutlinedButton.icon(
                      onPressed: () {
                        setState(() {
                          if (_passengers.length < 20) {
                            _passengers.add(_PassengerDraft());
                          }
                        });
                      },
                      icon: const Icon(Icons.person_add_alt),
                      label: Text(l10n.t('addPassenger')),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: l10n.t('contactEmail'),
                      border: const OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(
                      labelText: l10n.t('contactPhone'),
                      border: const OutlineInputBorder(),
                      isDense: true,
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: AppTypography.caption.copyWith(
                      color: AppColors.danger,
                    ),
                  ),
                ],
                if (booking == null) ...[
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: _busy ? null : () => _createBooking(trip),
                    icon: const Icon(Icons.check),
                    label: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(l10n.t('createBooking')),
                    ),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _PassengerCard extends ConsumerWidget {
  const _PassengerCard({
    required this.index,
    required this.passenger,
    required this.classes,
    required this.canRemove,
    required this.onRemove,
    required this.onChange,
  });

  final int index;
  final _PassengerDraft passenger;
  final List<TravelTripPrice> classes;
  final bool canRemove;
  final VoidCallback onRemove;
  final VoidCallback onChange;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final muted = AppColors.textSecondaryFor(context);

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${l10n.t('passenger')} ${index + 1}',
                    style: AppTypography.subtitle.copyWith(color: muted),
                  ),
                ),
                if (canRemove)
                  IconButton(
                    icon: const Icon(Icons.remove_circle_outline),
                    tooltip: l10n.t('removePassenger'),
                    onPressed: onRemove,
                  ),
              ],
            ),
            TextField(
              decoration: InputDecoration(
                labelText: l10n.t('fullName'),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
              onChanged: (value) {
                passenger.fullName = value;
                onChange();
              },
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: passenger.ageCategory,
                    decoration: InputDecoration(
                      labelText: l10n.t('ageCategory'),
                      border: const OutlineInputBorder(),
                      isDense: true,
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 'adult',
                        child: Text(l10n.t('adult')),
                      ),
                      DropdownMenuItem(
                        value: 'child',
                        child: Text(l10n.t('child')),
                      ),
                      DropdownMenuItem(
                        value: 'infant',
                        child: Text(l10n.t('infant')),
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        passenger.ageCategory = value;
                        onChange();
                      }
                    },
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButtonFormField<int?>(
                    initialValue: passenger.classId,
                    decoration: InputDecoration(
                      labelText: l10n.t('priceAdult'),
                      border: const OutlineInputBorder(),
                      isDense: true,
                    ),
                    items: [
                      for (final price in classes)
                        DropdownMenuItem<int?>(
                          value: price.classId,
                          child: Text(
                            '${l10n.t('priceAdult')} '
                            '${(price.adultPriceMinor ?? 0) / 100}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: (value) {
                      passenger.classId = value;
                      onChange();
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: l10n.t('seatNumber'),
                border: const OutlineInputBorder(),
                isDense: true,
              ),
              onChanged: (value) {
                passenger.seatNumber = int.tryParse(value);
                onChange();
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _BookingSummary extends ConsumerWidget {
  const _BookingSummary({required this.booking});

  final TravelBooking booking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final total = booking.totalAmountMinor ?? 0;

    return Card(
      elevation: 0,
      color: AppColors.surfaceFor(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${l10n.t('bookingReference')} : ${booking.reference ?? ''}',
              style: AppTypography.subtitle.copyWith(color: text),
            ),
            const SizedBox(height: 6),
            Text(
              '${l10n.t('status')} : '
              '${_statusLabel(l10n, booking.status)}',
              style: AppTypography.caption.copyWith(color: muted),
            ),
            const SizedBox(height: 4),
            Text(
              '${l10n.t('paymentStatus')} : '
              '${_paymentLabel(l10n, booking.paymentStatus)}',
              style: AppTypography.caption.copyWith(color: muted),
            ),
            const SizedBox(height: 6),
            Text(
              '${l10n.t('totalAmount')} : '
              '${(total / 100).toStringAsFixed(2)} '
              '${booking.currency ?? ''}',
              style: AppTypography.subtitle.copyWith(
                color: AppColors.success,
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _statusLabel(AppStrings l10n, String? status) {
    switch (status) {
      case 'pending':
        return l10n.t('bookingStatus_pending');
      case 'confirmed':
        return l10n.t('bookingStatus_confirmed');
      case 'cancelled':
        return l10n.t('bookingStatus_cancelled');
      case 'refunded':
        return l10n.t('bookingStatus_refunded');
      case 'completed':
        return l10n.t('bookingStatus_completed');
      default:
        return status ?? '';
    }
  }

  static String _paymentLabel(AppStrings l10n, String? status) {
    switch (status) {
      case 'pending':
        return l10n.t('paymentStatus_pending');
      case 'confirmed':
        return l10n.t('paymentStatus_confirmed');
      case 'failed':
        return l10n.t('paymentStatus_failed');
      case 'refunded':
        return l10n.t('paymentStatus_refunded');
      default:
        return status ?? '';
    }
  }
}

class _TicketsSection extends ConsumerWidget {
  const _TicketsSection({
    required this.booking,
    required this.busy,
    required this.onCheckIn,
  });

  final TravelBooking booking;
  final bool busy;
  final Future<void> Function(TravelTicket ticket) onCheckIn;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final muted = AppColors.textSecondaryFor(context);
    final text = AppColors.textPrimaryFor(context);

    if (booking.tickets.isEmpty) {
      return Text(
        l10n.t('noTickets'),
        style: AppTypography.caption.copyWith(color: muted),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l10n.t('ticketsTitle'),
          style: AppTypography.subtitle.copyWith(color: text),
        ),
        const SizedBox(height: 8),
        for (final ticket in booking.tickets)
          Card(
            elevation: 0,
            color: AppColors.surfaceFor(context),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${l10n.t('ticketNumber')} : '
                              '${ticket.ticketNumber ?? ''}',
                              style: AppTypography.body.copyWith(color: text),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${l10n.t('status')} : '
                              '${_ticketStatusLabel(l10n, ticket.status)}',
                              style: AppTypography.caption.copyWith(
                                color: ticket.status == 'checked_in'
                                    ? AppColors.success
                                    : muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (ticket.id != null && ticket.status == 'issued')
                        TextButton(
                          onPressed: busy ? null : () => onCheckIn(ticket),
                          child: Text(l10n.t('checkIn')),
                        ),
                    ],
                  ),
                  if (ticket.ticketNumber != null) ...[
                    const SizedBox(height: 8),
                    Center(
                      child: QrTicketView(ticketNumber: ticket.ticketNumber!),
                    ),
                  ],
                ],
              ),
            ),
          ),
      ],
    );
  }

  static String _ticketStatusLabel(AppStrings l10n, String? status) {
    switch (status) {
      case 'issued':
        return l10n.t('ticketStatus_issued');
      case 'checked_in':
        return l10n.t('ticketStatus_checked_in');
      case 'void':
        return l10n.t('ticketStatus_void');
      default:
        return status ?? '';
    }
  }
}

class QrTicketView extends ConsumerWidget {
  const QrTicketView({super.key, required this.ticketNumber});

  final String ticketNumber;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final muted = AppColors.textSecondaryFor(context);
    return Column(
      children: [
        QrImageView(
          data: ticketNumber,
          version: QrVersions.auto,
          size: 160,
          backgroundColor: Colors.white,
        ),
        const SizedBox(height: 4),
        Text(
          l10n.t('qrTicket'),
          style: AppTypography.caption.copyWith(color: muted),
        ),
      ],
    );
  }
}
