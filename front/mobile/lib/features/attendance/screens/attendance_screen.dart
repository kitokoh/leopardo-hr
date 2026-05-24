import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/models/attendance_log.dart';

const _bgPrimary = Color(0xFF0B1120);
const _bgCard = Color(0xFF111B2E);
const _bgChip = Color(0xFF0C1525);
const _borderCard = Color(0xFF1A2B44);
const _textPrimary = Color(0xFFE2EAF6);
const _textSecondary = Color(0xFF3D5470);
const _textMid = Color(0xFF7A9CC0);
const _textValues = Color(0xFFC8D8F0);
const _accentGreen = Color(0xFF10B981);
const _accentGreenDark = Color(0xFF0D5C3A);
const _accentGreenBg = Color(0xFF0D4F3C);
const _accentAmber = Color(0xFFF59E0B);
const _accentRed = Color(0xFFEF4444);
const _bottomBarBg = Color(0xFF0C1525);

class AttendanceScreen extends ConsumerStatefulWidget {
  const AttendanceScreen({super.key});

  @override
  ConsumerState<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends ConsumerState<AttendanceScreen> {
  late Timer _clockTimer;
  DateTime _now = DateTime.now();

  @override
  void initState() {
    super.initState();
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
  }

  @override
  void dispose() {
    _clockTimer.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final attState = ref.watch(attendanceProvider);
    final isManager =
        authState.employee?.isManager == true ||
        attState.context?['mode'] == 'collection';

    if (attState.error != null && attState.error!.contains('NOT_IMPLEMENTED')) {
      return _StubScreen(ref: ref);
    }

    return Scaffold(
      backgroundColor: _bgPrimary,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: RefreshIndicator(
                color: _accentGreen,
                backgroundColor: _bgCard,
                onRefresh: () =>
                    ref.read(attendanceProvider.notifier).loadTodayData(),
                child: ListView(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  children: [
                    const SizedBox(height: 10),
                    _buildHeader(authState, isManager),
                    const SizedBox(height: 22),
                    _buildClock(),
                    const SizedBox(height: 22),
                    isManager
                        ? _buildManagerOverview(attState)
                        : _buildPulseArea(attState),
                    const SizedBox(height: 22),
                    if (!isManager) _buildStatusCard(attState),
                    if (!isManager) ...[
                      _buildWeekSection(),
                      const SizedBox(height: 20),
                    ],
                    _buildQuickActions(isManager),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
            _buildBottomBar(context),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(AuthState authState, bool isManager) {
    final employee = authState.employee;
    final firstName = employee?.firstName ?? '';
    final lastName = employee?.lastName ?? '';
    final initials =
        '${firstName.isNotEmpty ? firstName[0] : ''}${lastName.isNotEmpty ? lastName[0] : ''}'
            .toUpperCase();
    final role = isManager ? 'Manager' : 'Employe';

    return Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            color: _accentGreenBg,
          ),
          child: Center(
            child: Text(
              initials,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: _accentGreen,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Bonjour, $firstName',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                  color: _textPrimary,
                ),
              ),
              Text(
                role,
                style: const TextStyle(fontSize: 11, color: _textSecondary),
              ),
            ],
          ),
        ),
        GestureDetector(
          onTap: () => context.push('/settings'),
          child: const Padding(
            padding: EdgeInsets.all(4),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _Dot(),
                SizedBox(height: 3),
                _Dot(),
                SizedBox(height: 3),
                _Dot(),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildClock() {
    final hours = _now.hour.toString().padLeft(2, '0');
    final minutes = _now.minute.toString().padLeft(2, '0');
    final seconds = _now.second.toString().padLeft(2, '0');
    final dateLabel = DateFormat('EEEE d MMMM yyyy', 'fr_FR').format(_now);

    return Column(
      children: [
        RichText(
          textAlign: TextAlign.center,
          text: TextSpan(
            children: [
              TextSpan(
                text: '$hours:$minutes',
                style: const TextStyle(
                  fontSize: 50,
                  fontWeight: FontWeight.w200,
                  color: _textPrimary,
                  letterSpacing: -2,
                  fontFeatures: [FontFeature.tabularFigures()],
                ),
              ),
              TextSpan(
                text: ':$seconds',
                style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w200,
                  color: _textSecondary,
                  fontFeatures: [FontFeature.tabularFigures()],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 5),
        Text(
          dateLabel,
          style: const TextStyle(
            fontSize: 12,
            color: _textSecondary,
            letterSpacing: 0.2,
          ),
        ),
      ],
    );
  }

  Widget _buildPulseArea(AttendanceState state) {
    final isCheckedIn =
        state.todayLog?.checkIn != null && state.todayLog?.checkOut == null;
    final isLoading = state.isLoading && state.todayLog == null;

    return Column(
      children: [
        _PulseRingButton(
          isCheckedIn: isCheckedIn,
          isLoading: isLoading,
          onTap: () {
            HapticFeedback.mediumImpact();
            if (isCheckedIn) {
              ref.read(attendanceProvider.notifier).checkOut();
            } else {
              ref.read(attendanceProvider.notifier).checkIn();
            }
          },
        ),
        const SizedBox(height: 12),
        const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.fingerprint, size: 13, color: _accentGreenDark),
            SizedBox(width: 5),
            Text(
              'Empreinte activee (optionnel)',
              style: TextStyle(fontSize: 11, color: _textSecondary),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildManagerOverview(AttendanceState state) {
    final items = state.context?['items'];
    final employees = items is List ? items : const [];
    final checkedInCount = employees.whereType<Map>().where((item) {
      final status = item['status']?.toString();
      return status != null && status != 'absent';
    }).length;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _bgCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _borderCard, width: 0.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'SUIVI EQUIPE',
            style: TextStyle(
              fontSize: 11,
              color: _textSecondary,
              fontWeight: FontWeight.w500,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 12),
          if (state.isLoading && employees.isEmpty)
            const Row(
              children: [
                SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: _accentGreen,
                  ),
                ),
                SizedBox(width: 12),
                Text(
                  'Chargement...',
                  style: TextStyle(color: _textSecondary),
                ),
              ],
            )
          else ...[
            Text(
              employees.isEmpty
                  ? 'Le suivi sera disponible apres actualisation.'
                  : '${employees.length} collaborateurs, $checkedInCount pointes',
              style: const TextStyle(color: _textMid, fontSize: 13),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () =>
                    ref.read(attendanceProvider.notifier).loadTodayData(),
                style: OutlinedButton.styleFrom(
                  foregroundColor: _accentGreen,
                  side: const BorderSide(color: _borderCard),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: const Text('Actualiser le suivi'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildStatusCard(AttendanceState state) {
    final log = state.todayLog;
    final summary = state.summary;
    final isPresent = log?.checkIn != null;
    final checkInTime = log?.checkIn != null
        ? '${log!.checkIn!.hour.toString().padLeft(2, '0')}:${log.checkIn!.minute.toString().padLeft(2, '0')}'
        : '--:--';
    final checkOutTime = log?.checkOut != null
        ? '${log!.checkOut!.hour.toString().padLeft(2, '0')}:${log.checkOut!.minute.toString().padLeft(2, '0')}'
        : '--:--';

    final currencyFormat = NumberFormat.currency(
      locale: 'fr_DZ',
      symbol: summary?.currency ?? 'DZD',
      decimalDigits: 0,
    );

    return Container(
      padding: const EdgeInsets.all(14),
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: _bgCard,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _borderCard, width: 0.5),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                "AUJOURD'HUI",
                style: TextStyle(
                  fontSize: 11,
                  color: _textSecondary,
                  fontWeight: FontWeight.w500,
                  letterSpacing: 0.5,
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
                decoration: BoxDecoration(
                  color: isPresent
                      ? _accentGreen.withValues(alpha: 0.1)
                      : _textSecondary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: isPresent
                        ? _accentGreen.withValues(alpha: 0.25)
                        : _textSecondary.withValues(alpha: 0.25),
                    width: 0.5,
                  ),
                ),
                child: Text(
                  isPresent ? 'Present' : 'En attente',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: isPresent ? _accentGreen : _textSecondary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: _bgChip,
                    borderRadius: BorderRadius.circular(9),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.login, size: 14, color: _accentGreen),
                      const SizedBox(width: 7),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Arrivee',
                            style:
                                TextStyle(fontSize: 9, color: _textSecondary),
                          ),
                          Text(
                            checkInTime,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: log?.checkIn != null
                                  ? _textValues
                                  : _textSecondary,
                              fontFeatures: const [
                                FontFeature.tabularFigures()
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: _bgChip,
                    borderRadius: BorderRadius.circular(9),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                          Icons.logout, size: 14, color: _textSecondary),
                      const SizedBox(width: 7),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Depart',
                            style:
                                TextStyle(fontSize: 9, color: _textSecondary),
                          ),
                          Text(
                            checkOutTime,
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                              color: log?.checkOut != null
                                  ? _textValues
                                  : _textSecondary,
                              fontFeatures: const [
                                FontFeature.tabularFigures()
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const Divider(color: _borderCard, height: 24, thickness: 0.5),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Gain estime du jour',
                style: TextStyle(fontSize: 11, color: _textSecondary),
              ),
              Text(
                summary != null
                    ? currencyFormat.format(summary.totalEstimated)
                    : '-- DZD',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: _accentGreen,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildWeekSection() {
    final now = DateTime.now();
    final historyAsync =
        ref.watch(historyProvider(DateTime(now.year, now.month)));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 14),
          child: Text(
            'CETTE SEMAINE',
            style: TextStyle(
              fontSize: 10,
              color: _textSecondary,
              fontWeight: FontWeight.w500,
              letterSpacing: 0.6,
            ),
          ),
        ),
        historyAsync.when(
          loading: () => const Center(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: _accentGreen,
                ),
              ),
            ),
          ),
          error: (_, __) => const Padding(
            padding: EdgeInsets.all(8),
            child: Text(
              'Historique indisponible',
              style: TextStyle(color: _textSecondary, fontSize: 12),
            ),
          ),
          data: (logs) {
            final weekStart = now.subtract(Duration(days: now.weekday - 1));
            final weekLogs = logs.where((log) {
              return log.date.isAfter(
                        weekStart.subtract(const Duration(days: 1))) &&
                  log.date.isBefore(now);
            }).toList()
              ..sort((a, b) => b.date.compareTo(a.date));

            final displayLogs = weekLogs.take(5).toList();

            double totalHours = 0;
            int totalLateMinutes = 0;
            for (final log in weekLogs) {
              totalHours += log.workedHours ?? 0;
              totalLateMinutes += log.lateMinutes ?? 0;
            }

            return Column(
              children: [
                ...displayLogs.map((log) => _DayRow(log: log, now: now)),
                const SizedBox(height: 4),
                _WeekSummary(
                  totalHours: totalHours,
                  totalLateMinutes: totalLateMinutes,
                ),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget _buildQuickActions(bool isManager) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _QuickActionTile(
          icon: Icons.calendar_month,
          label: 'Mon mois (heures, heures sup)',
          onTap: () => context.push('/me/monthly'),
        ),
        const SizedBox(height: 8),
        _QuickActionTile(
          icon: Icons.history,
          label: 'Voir historique complet',
          onTap: () => context.push('/history'),
        ),
        if (isManager) ...[
          const SizedBox(height: 8),
          _QuickActionTile(
            icon: Icons.groups,
            label: 'Equipe (ajouter, inviter)',
            onTap: () => context.push('/team'),
          ),
        ],
      ],
    );
  }

  Widget _buildBottomBar(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: _bottomBarBg,
        border: Border(top: BorderSide(color: _borderCard, width: 0.5)),
      ),
      padding: const EdgeInsets.only(top: 10, bottom: 14),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _BottomBarItem(
            icon: Icons.home_outlined,
            label: 'Accueil',
            onTap: () => context.go('/'),
          ),
          _BottomBarItem(
            icon: Icons.fingerprint,
            label: 'Pointage',
            isActive: true,
            onTap: () {},
          ),
          _BottomBarItem(
            icon: Icons.notifications_outlined,
            label: 'Alertes',
            showBadge: true,
            onTap: () => context.push('/notifications'),
          ),
          _BottomBarItem(
            icon: Icons.person_outline,
            label: 'Compte',
            onTap: () => context.push('/settings'),
          ),
        ],
      ),
    );
  }
}

class _StubScreen extends StatelessWidget {
  final WidgetRef ref;
  const _StubScreen({required this.ref});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bgPrimary,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
                Icons.build_circle_outlined, size: 64, color: _textSecondary),
            const SizedBox(height: 16),
            const Text(
              'Fonction bientot disponible',
              style: TextStyle(fontSize: 20, color: _textPrimary),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () =>
                  ref.read(attendanceProvider.notifier).loadTodayData(),
              style: ElevatedButton.styleFrom(
                backgroundColor: _accentGreen,
                foregroundColor: Colors.white,
              ),
              child: const Text('Reessayer'),
            ),
            const SizedBox(height: 16),
            TextButton(
              onPressed: () => ref.read(authProvider.notifier).logout(),
              child: const Text(
                'Deconnexion',
                style: TextStyle(color: _accentRed),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PulseRingButton extends StatefulWidget {
  final bool isCheckedIn;
  final bool isLoading;
  final VoidCallback onTap;

  const _PulseRingButton({
    required this.isCheckedIn,
    required this.isLoading,
    required this.onTap,
  });

  @override
  State<_PulseRingButton> createState() => _PulseRingButtonState();
}

class _PulseRingButtonState extends State<_PulseRingButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(seconds: 2),
      vsync: this,
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final scale =
            widget.isLoading ? 1.0 : 1.0 + 0.03 * _controller.value;
        return Transform.scale(
          scale: scale,
          child: Semantics(
            label: widget.isCheckedIn
                ? 'Se deconnecter du pointage'
                : 'Pointer mon arrivee',
            button: true,
            enabled: !widget.isLoading,
            child: GestureDetector(
              onTap: widget.isLoading ? null : widget.onTap,
              child: Container(
                width: 148,
                height: 148,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: _accentGreen.withValues(alpha: 0.12),
                    width: 1,
                  ),
                ),
                child: Center(
                  child: Container(
                    width: 128,
                    height: 128,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: _accentGreen.withValues(alpha: 0.2),
                        width: 1,
                      ),
                    ),
                    child: Center(
                      child: Container(
                        width: 108,
                        height: 108,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: widget.isCheckedIn
                              ? _accentRed.withValues(alpha: 0.8)
                              : _accentGreenDark,
                          border: Border.all(
                            color: widget.isCheckedIn
                                ? _accentRed.withValues(alpha: 0.4)
                                : _accentGreen.withValues(alpha: 0.4),
                            width: 1,
                          ),
                        ),
                        child: widget.isLoading
                            ? const Center(
                                child: SizedBox(
                                  width: 28,
                                  height: 28,
                                  child: CircularProgressIndicator(
                                    color: _accentGreen,
                                    strokeWidth: 2,
                                  ),
                                ),
                              )
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    widget.isCheckedIn
                                        ? Icons.logout
                                        : Icons.login,
                                    size: 26,
                                    color: widget.isCheckedIn
                                        ? Colors.white
                                        : _accentGreen,
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    widget.isCheckedIn
                                        ? 'TERMINER'
                                        : 'POINTER',
                                    style: TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w600,
                                      color: widget.isCheckedIn
                                          ? Colors.white
                                          : _accentGreen,
                                      letterSpacing: 1.2,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _DayRow extends StatelessWidget {
  final AttendanceLog log;
  final DateTime now;

  const _DayRow({required this.log, required this.now});

  @override
  Widget build(BuildContext context) {
    final isAbsent = log.status == 'absent' || log.checkIn == null;
    final isLate = log.lateMinutes != null && log.lateMinutes! > 0;

    Color indicatorColor;
    if (isAbsent) {
      indicatorColor = _textSecondary;
    } else if (isLate) {
      indicatorColor = _accentAmber;
    } else {
      indicatorColor = _accentGreen;
    }

    final dayDiff = DateTime(now.year, now.month, now.day)
        .difference(DateTime(log.date.year, log.date.month, log.date.day))
        .inDays;
    final dayLabel = dayDiff == 0
        ? "Aujourd'hui"
        : dayDiff == 1
            ? 'Hier'
            : DateFormat('E. d', 'fr_FR').format(log.date);

    String subtitle;
    if (isAbsent) {
      subtitle = 'Absent';
    } else {
      final ci =
          '${log.checkIn!.hour.toString().padLeft(2, '0')}:${log.checkIn!.minute.toString().padLeft(2, '0')}';
      final co = log.checkOut != null
          ? '${log.checkOut!.hour.toString().padLeft(2, '0')}:${log.checkOut!.minute.toString().padLeft(2, '0')}'
          : 'en cours';
      subtitle = '$ci \u2192 $co';
      if (isLate) subtitle += ' \u00b7 +${log.lateMinutes} min';
    }

    final hours = log.workedHours ?? 0;
    final hoursLabel = isAbsent
        ? '--'
        : '${hours.floor()}h${((hours % 1) * 60).round().toString().padLeft(2, '0')}';

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      decoration: BoxDecoration(
        color: _bgCard,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _borderCard, width: 0.5),
      ),
      child: Row(
        children: [
          Container(
            width: 3,
            height: 54,
            decoration: BoxDecoration(
              color: indicatorColor,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(10),
                bottomLeft: Radius.circular(10),
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          dayLabel,
                          style: const TextStyle(
                            fontSize: 12,
                            color: _textMid,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 1),
                        Text(
                          subtitle,
                          style: const TextStyle(
                            fontSize: 10,
                            color: _textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    hoursLabel,
                    style: TextStyle(
                      fontSize: 12,
                      color: isAbsent ? _textSecondary : _textValues,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WeekSummary extends StatelessWidget {
  final double totalHours;
  final int totalLateMinutes;

  const _WeekSummary({
    required this.totalHours,
    required this.totalLateMinutes,
  });

  @override
  Widget build(BuildContext context) {
    final hoursLabel =
        '${totalHours.floor()}h${((totalHours % 1) * 60).round().toString().padLeft(2, '0')}';
    final lateLabel =
        totalLateMinutes > 0 ? '$totalLateMinutes min' : '0 min';

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _bgCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _borderCard, width: 0.5),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          Column(
            children: [
              Text(
                hoursLabel,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                  color: _textValues,
                ),
              ),
              const SizedBox(height: 2),
              const Text(
                'Heures semaine',
                style: TextStyle(fontSize: 10, color: _textSecondary),
              ),
            ],
          ),
          Column(
            children: [
              Text(
                lateLabel,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                  color: _accentAmber,
                ),
              ),
              const SizedBox(height: 2),
              const Text(
                'Retard cumule',
                style: TextStyle(fontSize: 10, color: _textSecondary),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _QuickActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: _bgCard,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _borderCard, width: 0.5),
          ),
          child: Row(
            children: [
              Icon(icon, size: 20, color: _accentGreen),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    fontSize: 13,
                    color: _textMid,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
              const Icon(
                  Icons.chevron_right, size: 18, color: _textSecondary),
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomBarItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isActive;
  final bool showBadge;
  final VoidCallback onTap;

  const _BottomBarItem({
    required this.icon,
    required this.label,
    this.isActive = false,
    this.showBadge = false,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        width: 60,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(
                  icon,
                  size: 20,
                  color: isActive ? _accentGreen : _textSecondary,
                ),
                if (showBadge)
                  Positioned(
                    right: -4,
                    top: -2,
                    child: Container(
                      width: 7,
                      height: 7,
                      decoration: BoxDecoration(
                        color: _accentRed,
                        shape: BoxShape.circle,
                        border:
                            Border.all(color: _bottomBarBg, width: 1.5),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 9,
                color: isActive ? _accentGreen : _textSecondary,
              ),
            ),
            if (isActive)
              Container(
                margin: const EdgeInsets.only(top: 1),
                width: 3,
                height: 3,
                decoration: const BoxDecoration(
                  color: _accentGreen,
                  shape: BoxShape.circle,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 3,
      height: 3,
      decoration: const BoxDecoration(
        color: _textSecondary,
        shape: BoxShape.circle,
      ),
    );
  }
}
