import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:local_auth/local_auth.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_employee/features/auth/providers/auth_provider.dart';
import 'package:leopardo_core/models/attendance_log.dart';

DateTime attendanceHistoryMonthKey(DateTime value) {
  return DateTime(value.year, value.month);
}

class AttendanceScreen extends ConsumerStatefulWidget {
  const AttendanceScreen({super.key});

  @override
  ConsumerState<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends ConsumerState<AttendanceScreen> {
  static const Color _bg = Color(0xFF0B1120);
  static const Color _card = Color(0xFF111B2E);
  static const Color _text = Color(0xFFE2EAF6);
  static const Color _muted = Color(0xFF7A9CC0);
  static const Color _secondary = Color(0xFFB8C7DA);
  static const Color _border = Color(0xFF1A2B44);
  static const Color _soft = Color(0xFF6F86A5);

  late Timer _clockTimer;
  DateTime _now = DateTime.now();
  bool _fingerprintAvailable = false;
  bool _fingerprintEnabled = true;

  @override
  void initState() {
    super.initState();
    _clockTimer = Timer.periodic(
      const Duration(seconds: 1),
      (_) => setState(() => _now = DateTime.now()),
    );
    _loadFingerprintAvailability();
  }

  @override
  void dispose() {
    _clockTimer.cancel();
    super.dispose();
  }

  Future<void> _loadFingerprintAvailability() async {
    try {
      final auth = LocalAuthentication();
      final canCheck = await auth.canCheckBiometrics;
      final supported = await auth.isDeviceSupported();
      final methods = await auth.getAvailableBiometrics();
      if (!mounted) return;
      setState(() {
        _fingerprintAvailable =
            supported &&
            canCheck &&
            methods.any((type) => type == BiometricType.fingerprint);
      });
    } catch (_) {
      if (mounted) setState(() => _fingerprintAvailable = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final attState = ref.watch(attendanceProvider);
    final historyMonth = attendanceHistoryMonthKey(_now);
    final weekAsync = ref.watch(historyProvider(historyMonth));
    final tasksAsync = ref.watch(todayTasksProvider);
    final weekLogs = weekAsync.maybeWhen(
      data: (value) => value,
      orElse: () => const <AttendanceLog>[],
    );
    final week = _buildWeekSummaries(weekLogs);
    final employee = authState.employee;
    final isCheckedIn =
        attState.todayLog?.checkIn != null &&
        attState.todayLog?.checkOut == null;
    const canDirectEdit = false;

    return Scaffold(
      backgroundColor: _bg,
      body: SafeArea(
        child: RefreshIndicator(
          color: AppColors.rh,
          backgroundColor: _card,
          onRefresh: () async {
            ref.invalidate(historyProvider(historyMonth));
            await ref.read(attendanceProvider.notifier).loadTodayData();
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 28),
            children: [
              _buildHeader(
                firstName: employee?.firstName ?? 'Leo',
                roleLabel: 'Employe',
                tasksAsync: tasksAsync,
              ),
              const SizedBox(height: 22),
              _buildLiveClock(),
              const SizedBox(height: 28),
              _buildPunchButton(
                isCheckedIn: isCheckedIn,
                isLoading: attState.isPunching,
                onTap: () => _handlePunch(isCheckedIn),
              ),
              const SizedBox(height: 22),
              _buildTodayCard(attState),
              const SizedBox(height: 14),
              _buildTodayTasks(tasksAsync),
              if (attState.error != null) ...[
                const SizedBox(height: 12),
                _buildNoticeCard(attState.error!, AppColors.danger),
              ] else if (attState.notice != null) ...[
                const SizedBox(height: 12),
                _buildNoticeCard(attState.notice!, AppColors.warning),
              ],
              const SizedBox(height: 24),
              _buildSectionTitle('CETTE SEMAINE'),
              const SizedBox(height: 10),
              if (weekAsync.hasError)
                _buildNoticeCard(
                  'Semaine indisponible pour l instant. Le pointage reste utilisable.',
                  AppColors.warning,
                ),
              ...week.map(
                (day) => _buildDayRow(
                  day,
                  canDirectEdit: canDirectEdit,
                  currency: attState.summary?.currency ?? 'DZD',
                ),
              ),
              const SizedBox(height: 10),
              _buildWeekSummary(
                week,
                currency: attState.summary?.currency ?? 'DZD',
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _handlePunch(bool isCheckedIn) async {
    if (ref.read(attendanceProvider).isPunching) return;
    HapticFeedback.mediumImpact();
    final state = ref.read(attendanceProvider);
    final choice = await _resolvePunchChoice(isCheckedIn, state.todaySessions);
    if (choice == null) return;
    if (!mounted) return;

    ScaffoldMessenger.of(context).clearSnackBars();
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(
      SnackBar(
        content: Text('${choice.loadingLabel} vers le serveur...'),
        duration: const Duration(seconds: 2),
        backgroundColor: AppColors.rhDark,
      ),
    );

    final success =
        isCheckedIn
            ? await ref
                .read(attendanceProvider.notifier)
                .checkOut(workType: choice.workType)
            : await ref
                .read(attendanceProvider.notifier)
                .checkIn(workType: choice.workType);
    if (!mounted) return;
    messenger.clearSnackBars();
    messenger.showSnackBar(
      SnackBar(
        content: Text(
          success
              ? choice.successLabel
              : ref.read(attendanceProvider).error ??
                  '${choice.failureLabel}. Reessayez.',
        ),
        backgroundColor: success ? AppColors.rh : AppColors.danger,
      ),
    );
    if (success) {
      ref.invalidate(historyProvider(attendanceHistoryMonthKey(_now)));
      ref.invalidate(todayTasksProvider);
    }
  }

  Future<_PunchChoice?> _resolvePunchChoice(
    bool isCheckedIn,
    List<AttendanceLog> sessions,
  ) {
    final sorted = [...sessions]
      ..sort((a, b) => a.sessionNumber.compareTo(b.sessionNumber));
    final openSessions = sorted.where((session) => session.checkOut == null);

    if (!isCheckedIn && sorted.isEmpty) {
      return Future.value(_PunchChoice.firstArrival);
    }

    if (isCheckedIn && sorted.length == 1 && openSessions.length == 1) {
      return Future.value(_PunchChoice.firstDeparture);
    }

    return _chooseAdvancedPunchType(isCheckedIn, sorted);
  }

  Future<_PunchChoice?> _chooseAdvancedPunchType(
    bool isCheckedIn,
    List<AttendanceLog> sessions,
  ) {
    final choices =
        isCheckedIn
            ? const [
              _PunchChoice.firstDeparture,
              _PunchChoice(
                workType: 'break',
                title: 'Partir en pause',
                subtitle: 'Ferme la session et marque une pause',
                loadingLabel: 'Envoi de la pause',
                successLabel: 'Pause confirmee.',
                failureLabel: 'Pause non confirmee',
              ),
            ]
            : [
              _PunchChoice(
                workType: 'resume',
                title: 'Reprise',
                subtitle: 'Reprendre apres une pause ou une sortie',
                loadingLabel: 'Envoi reprise',
                successLabel: 'Reprise confirmee.',
                failureLabel: 'Reprise non confirmee',
              ),
              const _PunchChoice(
                workType: 'overtime',
                title: 'Heures supplementaires',
                subtitle: 'Demarrer une session d heures supp',
                loadingLabel: 'Envoi heures supplementaires',
                successLabel: 'Heures supplementaires demarrees.',
                failureLabel: 'Heures supplementaires non confirmees',
              ),
              const _PunchChoice(
                workType: 'mission',
                title: 'Mission',
                subtitle: 'Temps de travail hors site habituel',
                loadingLabel: 'Envoi mission',
                successLabel: 'Mission demarree.',
                failureLabel: 'Mission non confirmee',
              ),
              const _PunchChoice(
                workType: 'travel',
                title: 'Deplacement',
                subtitle: 'Temps de deplacement professionnel',
                loadingLabel: 'Envoi deplacement',
                successLabel: 'Deplacement demarre.',
                failureLabel: 'Deplacement non confirme',
              ),
            ];

    return showModalBottomSheet<_PunchChoice>(
      context: context,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (ctx) => Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFF2A3C5A),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 16),
                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Type de pointage',
                    style: TextStyle(
                      color: _text,
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                ...choices.map(
                  (choice) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      choice.title,
                      style: const TextStyle(color: _text),
                    ),
                    subtitle: Text(
                      choice.subtitle,
                      style: const TextStyle(color: _muted, fontSize: 12),
                    ),
                    trailing: const Icon(Icons.chevron_right, color: _muted),
                    onTap: () => Navigator.pop(ctx, choice),
                  ),
                ),
              ],
            ),
          ),
    );
  }

  Widget _buildHeader({
    required String firstName,
    required String roleLabel,
    required AsyncValue<List<Map<String, dynamic>>> tasksAsync,
  }) {
    final initial =
        firstName.trim().isEmpty
            ? 'L'
            : firstName.trim().characters.first.toUpperCase();

    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: AppColors.rh.withValues(alpha: 0.16),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.rh.withValues(alpha: 0.35)),
          ),
          child: Center(
            child: Text(
              initial,
              style: const TextStyle(
                color: AppColors.rh,
                fontSize: 18,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                firstName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _text,
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                roleLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: _secondary, fontSize: 12),
              ),
            ],
          ),
        ),
        PopupMenuButton<String>(
          icon: Column(
            mainAxisSize: MainAxisSize.min,
            children: List.generate(
              3,
              (_) => Container(
                width: 3,
                height: 3,
                margin: const EdgeInsets.symmetric(vertical: 1.5),
                decoration: const BoxDecoration(
                  color: _muted,
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ),
          color: _card,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          onSelected: (value) {
            switch (value) {
              case 'tasks':
                _showTasksSheet(context, tasksAsync);
                break;
              case 'history':
                context.push('/history');
                break;
              case 'preferences':
                context.push('/settings');
                break;
              case 'settings':
                context.push('/settings');
                break;
            }
          },
          itemBuilder:
              (_) => const [
                PopupMenuItem(
                  value: 'tasks',
                  child: _MenuItem(
                    icon: Icons.task_alt_outlined,
                    label: 'Taches du jour',
                  ),
                ),
                PopupMenuItem(
                  value: 'history',
                  child: _MenuItem(
                    icon: Icons.history_outlined,
                    label: 'Historique',
                  ),
                ),
                PopupMenuItem(
                  value: 'preferences',
                  child: _MenuItem(
                    icon: Icons.tune_outlined,
                    label: 'Preferences',
                  ),
                ),
                PopupMenuItem(
                  value: 'settings',
                  child: _MenuItem(
                    icon: Icons.settings_outlined,
                    label: 'Parametres',
                  ),
                ),
              ],
        ),
      ],
    );
  }

  void _showTasksSheet(
    BuildContext context,
    AsyncValue<List<Map<String, dynamic>>> tasksAsync,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (_) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
              child: tasksAsync.when(
                loading:
                    () => const _TasksSheetFrame(
                      child: _SheetMessage(
                        icon: Icons.sync,
                        title: 'Synchronisation',
                        body: 'Chargement des taches du jour...',
                      ),
                    ),
                error:
                    (_, __) => const _TasksSheetFrame(
                      child: _SheetMessage(
                        icon: Icons.wifi_off_outlined,
                        title: 'Taches indisponibles',
                        body:
                            'Le pointage reste utilisable. Reessayez apres synchronisation.',
                      ),
                    ),
                data:
                    (tasks) => _TasksSheetFrame(
                      child:
                          tasks.isEmpty
                              ? const _SheetMessage(
                                icon: Icons.task_alt_outlined,
                                title: 'Aucune tache aujourd hui',
                                body:
                                    'Vous pourrez pointer normalement. Les taches assignees apparaitront ici.',
                              )
                              : Column(
                                mainAxisSize: MainAxisSize.min,
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const _SheetHeader(
                                    title: 'Taches du jour',
                                    subtitle:
                                        'Cloturez ce qui est realise avant votre depart.',
                                  ),
                                  const SizedBox(height: 14),
                                  ...tasks.map((task) => _TaskLine(task: task)),
                                ],
                              ),
                    ),
              ),
            ),
          ),
    );
  }

  Widget _buildLiveClock() {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: [
            Text(
              DateFormat('HH:mm').format(_now),
              style: const TextStyle(
                fontSize: 50,
                fontWeight: FontWeight.w200,
                color: _text,
                letterSpacing: -2,
                fontFeatures: [FontFeature.tabularFigures()],
              ),
            ),
            Text(
              ':${_now.second.toString().padLeft(2, '0')}',
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w200,
                color: _muted,
                fontFeatures: [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
        Text(
          DateFormat('EEEE d MMMM yyyy', 'fr_FR').format(_now),
          style: const TextStyle(fontSize: 12, color: _muted),
        ),
      ],
    );
  }

  Widget _buildPunchButton({
    required bool isCheckedIn,
    required bool isLoading,
    required VoidCallback onTap,
  }) {
    return Column(
      children: [
        GestureDetector(
          onTap: isLoading ? null : onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            width: 188,
            height: 188,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.rh.withValues(alpha: 0.08),
              border: Border.all(
                color: AppColors.rh.withValues(alpha: 0.28),
                width: 16,
              ),
            ),
            child: Container(
              margin: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.rh.withValues(alpha: 0.13),
                border: Border.all(
                  color: AppColors.rh.withValues(alpha: 0.42),
                  width: 10,
                ),
              ),
              child: Container(
                margin: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color:
                      isCheckedIn ? AppColors.rhDark : const Color(0xFF0D5C3A),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.rh.withValues(alpha: 0.28),
                      blurRadius: 28,
                      spreadRadius: 4,
                    ),
                  ],
                ),
                child: Center(
                  child:
                      isLoading
                          ? const SizedBox(
                            width: 30,
                            height: 30,
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 2.4,
                            ),
                          )
                          : _buildFingerprintIcon(),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          isLoading
              ? 'Enregistrement en cours...'
              : isCheckedIn
              ? 'Appuyez pour enregistrer votre depart'
              : 'Appuyez pour enregistrer votre arrivee',
          style: const TextStyle(color: _secondary, fontSize: 12),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 8),
        if (_fingerprintAvailable)
          GestureDetector(
            onTap:
                () =>
                    setState(() => _fingerprintEnabled = !_fingerprintEnabled),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.fingerprint,
                  size: 13,
                  color: _fingerprintEnabled ? AppColors.rhDark : _soft,
                ),
                const SizedBox(width: 5),
                Text(
                  _fingerprintEnabled
                      ? 'Empreinte activee (optionnel)'
                      : 'Activer l\'empreinte (optionnel)',
                  style: TextStyle(
                    fontSize: 11,
                    color:
                        _fingerprintEnabled ? _soft : const Color(0xFF1E3050),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _buildFingerprintIcon() {
    return const SizedBox(
      width: 44,
      height: 44,
      child: CustomPaint(painter: _FingerprintPainter(color: AppColors.rh)),
    );
  }

  Widget _buildTodayCard(AttendanceState state) {
    final log = state.todayLog;
    final checkIn = _formatTime(log?.checkIn);
    final checkOut = _formatTime(log?.checkOut);
    final statusLabel = _statusLabel(log);
    final statusColor = _statusColor(log);
    final gain =
        state.summary?.totalEstimated ??
        _estimatedEarnings(log?.workedHours ?? 0);
    final currency = state.summary?.currency ?? 'DZD';
    final sessionsCount =
        int.tryParse(state.daySummary?['sessions_count']?.toString() ?? '') ??
        state.todaySessions.length;
    final breakMinutes =
        int.tryParse(state.daySummary?['break_minutes']?.toString() ?? '') ?? 0;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text(
                'AUJOURD\'HUI',
                style: TextStyle(
                  color: _secondary,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                ),
              ),
              const Spacer(),
              _StatusBadge(label: statusLabel, color: statusColor),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(child: _TimeChip(label: 'Arrivee', value: checkIn)),
              const SizedBox(width: 10),
              Expanded(child: _TimeChip(label: 'Depart', value: checkOut)),
            ],
          ),
          if (sessionsCount > 1 || breakMinutes > 0) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _TimeChip(
                    label: 'Sessions',
                    value: sessionsCount.toString(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _TimeChip(label: 'Pauses', value: '$breakMinutes min'),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          const Divider(color: _border, height: 1),
          const SizedBox(height: 14),
          Row(
            children: [
              const Text(
                'Gain estime du jour',
                style: TextStyle(color: _muted, fontSize: 12),
              ),
              const Spacer(),
              Text(
                '${gain.toStringAsFixed(0)} $currency',
                style: const TextStyle(
                  color: AppColors.rh,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  fontFeatures: [FontFeature.tabularFigures()],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String label) {
    return Text(
      label,
      style: const TextStyle(
        color: _secondary,
        fontSize: 12,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.8,
      ),
    );
  }

  Widget _buildTodayTasks(AsyncValue<List<Map<String, dynamic>>> tasksAsync) {
    return tasksAsync.when(
      loading:
          () => _buildNoticeCard(
            'Synchronisation des taches du jour...',
            AppColors.warning,
          ),
      error:
          (_, __) => _buildNoticeCard(
            'Taches du jour indisponibles pour l instant.',
            AppColors.warning,
          ),
      data: (tasks) {
        if (tasks.isEmpty) {
          return _buildNoticeCard(
            'Aucune tache assignee pour aujourd hui.',
            AppColors.rh,
          );
        }

        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _card,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: _border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'TACHES DU JOUR',
                style: TextStyle(
                  color: _secondary,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              ...tasks.take(3).map((task) => _TaskLine(task: task)),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDayRow(
    AttendanceDaySummary day, {
    required bool canDirectEdit,
    required String currency,
  }) {
    final barColor =
        day.isAbsent
            ? _soft
            : day.lateMinutes > 0
            ? AppColors.warning
            : AppColors.rh;

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _border),
      ),
      child: Row(
        children: [
          Container(
            width: 3,
            height: 54,
            decoration: BoxDecoration(
              color: barColor,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(10),
                bottomLeft: Radius.circular(10),
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          day.dayLabel,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: _secondary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          day.isAbsent
                              ? 'Absent'
                              : '${day.checkInFormatted} -> ${day.checkOutFormatted}'
                                  '${day.lateMinutes > 0 ? ' · +${day.lateMinutes} min' : ''}',
                          style: const TextStyle(fontSize: 10, color: _soft),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        day.isAbsent ? '--' : day.hoursFormatted,
                        style: TextStyle(
                          fontSize: 12,
                          fontFeatures: const [FontFeature.tabularFigures()],
                          color: day.isAbsent ? _soft : const Color(0xFFC8D8F0),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        day.isAbsent
                            ? '0 $currency'
                            : '${day.estimatedEarnings.toStringAsFixed(0)} $currency',
                        style: TextStyle(
                          fontSize: 10,
                          color: day.isAbsent ? _soft : AppColors.rh,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          GestureDetector(
            onTap:
                () => _showDayActionsSheet(
                  context,
                  day: day,
                  canDirectEdit: canDirectEdit,
                  currency: currency,
                ),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 18),
              decoration: const BoxDecoration(
                border: Border(left: BorderSide(color: _border, width: 0.5)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: List.generate(
                  3,
                  (_) => Container(
                    width: 2.5,
                    height: 2.5,
                    margin: const EdgeInsets.symmetric(vertical: 1.5),
                    decoration: const BoxDecoration(
                      color: _soft,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWeekSummary(
    List<AttendanceDaySummary> week, {
    required String currency,
  }) {
    final totalMinutes = week
        .where((day) => !day.isAbsent)
        .fold<int>(0, (sum, day) => sum + day.workedMinutes);
    final totalEarnings = week
        .where((day) => !day.isAbsent)
        .fold<double>(0, (sum, day) => sum + day.estimatedEarnings);
    final totalLate = week.fold<int>(0, (sum, day) => sum + day.lateMinutes);
    final hours = totalMinutes ~/ 60;
    final mins = totalMinutes % 60;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _border),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _WeekStat(
            value: '${hours}h${mins.toString().padLeft(2, '0')}',
            label: 'Heures semaine',
            color: const Color(0xFFC8D8F0),
          ),
          _WeekStat(
            value: '${totalEarnings.toStringAsFixed(0)} $currency',
            label: 'Gain estime',
            color: AppColors.rh,
          ),
          _WeekStat(
            value: totalLate > 0 ? '$totalLate min' : 'Aucun',
            label: 'Retard cumule',
            color: totalLate > 0 ? AppColors.warning : AppColors.rh,
          ),
        ],
      ),
    );
  }

  Widget _buildNoticeCard(String message, Color color) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.34)),
      ),
      child: Text(message, style: const TextStyle(color: _secondary)),
    );
  }

  void _showCorrectionSheet(
    BuildContext context, {
    DateTime? forDate,
    required bool canDirectEdit,
    int? logId,
  }) {
    final targetDate = forDate ?? DateTime.now();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (_) => _CorrectionSheet(
            targetDate: targetDate,
            canDirectEdit: canDirectEdit,
            logId: logId,
          ),
    );
  }

  void _showDayActionsSheet(
    BuildContext context, {
    required AttendanceDaySummary day,
    required bool canDirectEdit,
    required String currency,
  }) {
    showModalBottomSheet(
      context: context,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (ctx) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _SheetHandle(),
                  const SizedBox(height: 16),
                  _SheetHeader(
                    title: day.dayLabel,
                    subtitle:
                        day.isAbsent
                            ? 'Aucun pointage enregistre pour cette journee.'
                            : '${day.sessionsCount} session(s) - ${day.hoursFormatted} travaillees.',
                  ),
                  const SizedBox(height: 14),
                  _ActionTile(
                    icon: Icons.receipt_long_outlined,
                    title: 'Details de la journee',
                    subtitle:
                        'Voir les pointages, pauses, heures supp et temps reel.',
                    onTap: () {
                      Navigator.pop(ctx);
                      _showDayDetailsSheet(context, day, currency: currency);
                    },
                  ),
                  const SizedBox(height: 8),
                  _ActionTile(
                    icon: Icons.edit_calendar_outlined,
                    title:
                        canDirectEdit
                            ? 'Modifier'
                            : 'Demander une modification',
                    subtitle:
                        canDirectEdit
                            ? 'Corriger directement cette ligne de pointage.'
                            : 'Soumettre une correction au RH pour validation.',
                    onTap: () {
                      Navigator.pop(ctx);
                      _showCorrectionSheet(
                        context,
                        forDate: day.date,
                        canDirectEdit: canDirectEdit,
                        logId: day.logId,
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
    );
  }

  void _showDayDetailsSheet(
    BuildContext context,
    AttendanceDaySummary day, {
    required String currency,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: _card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (_) => DraggableScrollableSheet(
            expand: false,
            initialChildSize: 0.72,
            minChildSize: 0.42,
            maxChildSize: 0.92,
            builder:
                (context, controller) => SafeArea(
                  child: ListView(
                    controller: controller,
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
                    children: [
                      const _SheetHandle(),
                      const SizedBox(height: 16),
                      _SheetHeader(
                        title: 'Details de la journee',
                        subtitle: DateFormat(
                          'EEEE d MMMM yyyy',
                          'fr_FR',
                        ).format(day.date),
                      ),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: _DetailMetric(
                              label: 'Temps travaille',
                              value: day.hoursFormatted,
                              color: const Color(0xFFC8D8F0),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _DetailMetric(
                              label: 'Heures supp',
                              value: day.overtimeFormatted,
                              color: AppColors.rh,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: _DetailMetric(
                              label: 'Pauses',
                              value: '${day.breakMinutes} min',
                              color: AppColors.warning,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _DetailMetric(
                              label: 'Gain estime',
                              value:
                                  '${day.estimatedEarnings.toStringAsFixed(0)} $currency',
                              color: AppColors.rh,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Pointages',
                        style: TextStyle(
                          color: _secondary,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 10),
                      if (day.sessions.isEmpty)
                        const _SheetMessage(
                          icon: Icons.event_busy_outlined,
                          title: 'Aucune session',
                          body:
                              'Cette journee ne contient pas encore de pointage.',
                        )
                      else
                        ...day.sessions.map(_SessionDetailTile.new),
                    ],
                  ),
                ),
          ),
    );
  }

  List<AttendanceDaySummary> _buildWeekSummaries(List<AttendanceLog> logs) {
    final today = DateTime(_now.year, _now.month, _now.day);
    final byDay = <String, List<AttendanceLog>>{};
    for (final log in logs) {
      final key = _dateKey(log.date);
      byDay.putIfAbsent(key, () => <AttendanceLog>[]).add(log);
    }

    return List.generate(5, (index) {
      final date = today.subtract(Duration(days: index));
      final sessions = byDay[_dateKey(date)] ?? const <AttendanceLog>[];
      final labelPrefix =
          index == 0
              ? 'Aujourd hui'
              : index == 1
              ? 'Hier'
              : _capitalize(DateFormat('EEE', 'fr_FR').format(date));
      final label =
          '$labelPrefix - ${DateFormat('d MMM', 'fr_FR').format(date)}';
      return AttendanceDaySummary.fromLogs(
        date: date,
        dayLabel: label,
        logs: sessions,
      );
    });
  }

  static String _dateKey(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  static String _formatTime(DateTime? date) =>
      date == null ? '--:--' : DateFormat('HH:mm').format(date);

  static double _estimatedEarnings(double hours) => hours * 550;

  static String _statusLabel(AttendanceLog? log) {
    if (log == null || log.checkIn == null) return 'A pointer';
    if (log.checkOut == null) return 'En cours';
    if ((log.lateMinutes ?? 0) > 0) return 'Retard';
    return 'Complet';
  }

  static Color _statusColor(AttendanceLog? log) {
    if (log == null || log.checkIn == null) return _soft;
    if ((log.lateMinutes ?? 0) > 0) return AppColors.warning;
    return AppColors.rh;
  }

  static String _capitalize(String value) {
    if (value.isEmpty) return value;
    return value[0].toUpperCase() + value.substring(1);
  }
}

class _FingerprintPainter extends CustomPainter {
  final Color color;
  const _FingerprintPainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height / 2;
    final paint =
        Paint()
          ..color = color
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.8
          ..strokeCap = StrokeCap.round;

    canvas.drawCircle(Offset(cx, cy), 1.5, paint..style = PaintingStyle.fill);
    paint.style = PaintingStyle.stroke;

    final radii = [5.0, 9.0, 13.0, 17.0, 21.0];
    final alphas = [1.0, 0.85, 0.70, 0.55, 0.40];
    for (var i = 0; i < radii.length; i++) {
      final r = radii[i];
      paint.color = color.withValues(alpha: alphas[i]);
      final sweep = i == radii.length - 1 ? 0.55 : 0.70;
      canvas.drawArc(
        Rect.fromCircle(center: Offset(cx, cy), radius: r),
        -3.14 * sweep,
        3.14 * sweep * 2,
        false,
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(_FingerprintPainter oldDelegate) =>
      oldDelegate.color != color;
}

class _PunchChoice {
  final String workType;
  final String title;
  final String subtitle;
  final String loadingLabel;
  final String successLabel;
  final String failureLabel;

  static const firstArrival = _PunchChoice(
    workType: 'normal',
    title: 'Arrivee normale',
    subtitle: 'Demarrer la journee',
    loadingLabel: 'Envoi de l arrivee',
    successLabel: 'Arrivee confirmee.',
    failureLabel: 'Arrivee non confirmee',
  );

  static const firstDeparture = _PunchChoice(
    workType: 'normal',
    title: 'Terminer le travail',
    subtitle: 'Enregistrer le depart de cette session',
    loadingLabel: 'Envoi du depart',
    successLabel: 'Depart confirme.',
    failureLabel: 'Depart non confirme',
  );

  const _PunchChoice({
    required this.workType,
    required this.title,
    required this.subtitle,
    required this.loadingLabel,
    required this.successLabel,
    required this.failureLabel,
  });
}

class _TasksSheetFrame extends StatelessWidget {
  final Widget child;

  const _TasksSheetFrame({required this.child});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Center(
          child: Container(
            width: 36,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFF2A3C5A),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
        const SizedBox(height: 16),
        child,
      ],
    );
  }
}

class _SheetHeader extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SheetHeader({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: _AttendanceScreenState._text,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          subtitle,
          style: const TextStyle(
            color: _AttendanceScreenState._muted,
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

class _SheetMessage extends StatelessWidget {
  final IconData icon;
  final String title;
  final String body;

  const _SheetMessage({
    required this.icon,
    required this.title,
    required this.body,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: AppColors.rh, size: 30),
        const SizedBox(height: 10),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: _AttendanceScreenState._text,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          body,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: _AttendanceScreenState._muted,
            fontSize: 12,
          ),
        ),
      ],
    );
  }
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 36,
        height: 4,
        decoration: BoxDecoration(
          color: const Color(0xFF2A3C5A),
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFF0C1525),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _AttendanceScreenState._border),
        ),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: AppColors.rh.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: AppColors.rh, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: _AttendanceScreenState._text,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: _AttendanceScreenState._muted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right,
              color: _AttendanceScreenState._muted,
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailMetric extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _DetailMetric({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFF0C1525),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _AttendanceScreenState._border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: _AttendanceScreenState._muted,
              fontSize: 10,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 16,
              fontWeight: FontWeight.w800,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

class _SessionDetailTile extends StatelessWidget {
  final AttendanceLog session;

  const _SessionDetailTile(this.session);

  @override
  Widget build(BuildContext context) {
    final start = _AttendanceScreenState._formatTime(session.checkIn);
    final end = _AttendanceScreenState._formatTime(session.checkOut);
    final minutes = ((session.workedHours ?? 0) * 60).round();
    final hours = minutes ~/ 60;
    final mins = minutes % 60;
    final duration = '${hours}h${mins.toString().padLeft(2, '0')}';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF0C1525),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _AttendanceScreenState._border),
      ),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 46,
            decoration: BoxDecoration(
              color: _workTypeColor(session.workType),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Session ${session.sessionNumber} - ${_workTypeLabel(session.workType)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _AttendanceScreenState._text,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '$start -> $end',
                  style: const TextStyle(
                    color: _AttendanceScreenState._muted,
                    fontSize: 11,
                    fontFeatures: [FontFeature.tabularFigures()],
                  ),
                ),
                if ((session.punchNote ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    session.punchNote!.trim(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _AttendanceScreenState._muted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            duration,
            style: const TextStyle(
              color: AppColors.rh,
              fontSize: 13,
              fontWeight: FontWeight.w800,
              fontFeatures: [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }

  static String _workTypeLabel(String raw) {
    switch (raw) {
      case 'overtime':
        return 'Heure supp';
      case 'break':
        return 'Pause';
      case 'resume':
        return 'Reprise';
      case 'mission':
        return 'Mission';
      case 'travel':
        return 'Deplacement';
      case 'training':
        return 'Formation';
      case 'other':
        return 'Autre';
      default:
        return 'Normal';
    }
  }

  static Color _workTypeColor(String raw) {
    switch (raw) {
      case 'overtime':
        return AppColors.warning;
      case 'break':
        return const Color(0xFF6F86A5);
      case 'mission':
      case 'travel':
        return const Color(0xFF38BDF8);
      default:
        return AppColors.rh;
    }
  }
}

class _TaskLine extends ConsumerWidget {
  final Map<String, dynamic> task;

  const _TaskLine({required this.task});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final id = int.tryParse(task['id']?.toString() ?? '');
    final title = task['title']?.toString() ?? 'Tache';
    final priority = task['priority']?.toString() ?? 'normal';
    final status = task['status']?.toString() ?? 'todo';
    final minutes = int.tryParse(task['estimated_minutes']?.toString() ?? '');
    final done = status == 'done';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFF0C1525),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _AttendanceScreenState._border),
      ),
      child: Row(
        children: [
          Icon(
            done
                ? Icons.task_alt
                : priority == 'urgent' || priority == 'high'
                ? Icons.priority_high
                : Icons.task_alt,
            color:
                done
                    ? AppColors.rh
                    : priority == 'urgent' || priority == 'high'
                    ? AppColors.warning
                    : AppColors.rh,
            size: 18,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: _AttendanceScreenState._text),
            ),
          ),
          if (minutes != null)
            Text(
              '$minutes min',
              style: const TextStyle(
                color: _AttendanceScreenState._muted,
                fontSize: 11,
              ),
            ),
          if (!done && id != null) ...[
            const SizedBox(width: 8),
            TextButton(
              onPressed: () => _openCompleteSheet(context, ref, id, minutes),
              child: const Text('Terminer'),
            ),
          ],
        ],
      ),
    );
  }

  void _openCompleteSheet(
    BuildContext context,
    WidgetRef ref,
    int taskId,
    int? estimatedMinutes,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: _AttendanceScreenState._card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (_) => _TaskCompletionSheet(
            taskId: taskId,
            estimatedMinutes: estimatedMinutes,
          ),
    );
  }
}

class _TaskCompletionSheet extends ConsumerStatefulWidget {
  const _TaskCompletionSheet({required this.taskId, this.estimatedMinutes});

  final int taskId;
  final int? estimatedMinutes;

  @override
  ConsumerState<_TaskCompletionSheet> createState() =>
      _TaskCompletionSheetState();
}

class _TaskCompletionSheetState extends ConsumerState<_TaskCompletionSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _minutesCtrl;
  final _noteCtrl = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _minutesCtrl = TextEditingController(
      text: (widget.estimatedMinutes ?? 30).toString(),
    );
  }

  @override
  void dispose() {
    _minutesCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, bottom + 24),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Cloturer la tache',
              style: TextStyle(
                color: _AttendanceScreenState._text,
                fontSize: 16,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Indiquez le temps reel et une note courte avant le depart.',
              style: TextStyle(
                color: _AttendanceScreenState._muted,
                fontSize: 12,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _minutesCtrl,
              keyboardType: TextInputType.number,
              style: const TextStyle(color: _AttendanceScreenState._text),
              decoration: const InputDecoration(
                labelText: 'Temps reel',
                suffixText: 'min',
              ),
              validator: (value) {
                final parsed = int.tryParse(value?.trim() ?? '');
                if (parsed == null || parsed <= 0) return 'Duree invalide';
                return null;
              },
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _noteCtrl,
              minLines: 2,
              maxLines: 3,
              maxLength: 300,
              style: const TextStyle(color: _AttendanceScreenState._text),
              decoration: const InputDecoration(
                labelText: 'Note de realisation',
              ),
            ),
            const SizedBox(height: 14),
            ElevatedButton.icon(
              onPressed: _submitting ? null : _submit,
              icon:
                  _submitting
                      ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                      : const Icon(Icons.task_alt),
              label: const Text('Marquer terminee'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      await ref
          .read(attendanceRepositoryProvider)
          .completeTask(
            taskId: widget.taskId,
            completedMinutes: int.parse(_minutesCtrl.text.trim()),
            note: _noteCtrl.text,
          );
      ref.invalidate(todayTasksProvider);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Tache terminee.')));
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Echec : $e')));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }
}

class _CorrectionSheet extends ConsumerStatefulWidget {
  final DateTime targetDate;
  final bool canDirectEdit;
  final int? logId;

  const _CorrectionSheet({
    required this.targetDate,
    required this.canDirectEdit,
    this.logId,
  });

  @override
  ConsumerState<_CorrectionSheet> createState() => _CorrectionSheetState();
}

class _CorrectionSheetState extends ConsumerState<_CorrectionSheet> {
  final _formKey = GlobalKey<FormState>();
  final _reasonCtrl = TextEditingController();
  TimeOfDay? _checkIn;
  TimeOfDay? _checkOut;
  bool _submitting = false;

  @override
  void dispose() {
    _reasonCtrl.dispose();
    super.dispose();
  }

  bool _isTimeFuture(TimeOfDay time) {
    final now = DateTime.now();
    final isToday =
        widget.targetDate.day == now.day &&
        widget.targetDate.month == now.month &&
        widget.targetDate.year == now.year;
    if (!isToday) return false;
    final candidate = DateTime(
      now.year,
      now.month,
      now.day,
      time.hour,
      time.minute,
    );
    return candidate.isAfter(now);
  }

  Future<void> _pickTime({required bool isCheckIn}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
      helpText:
          isCheckIn ? 'Heure d\'arrivee reelle' : 'Heure de depart reelle',
      builder:
          (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(alwaysUse24HourFormat: true),
            child: child!,
          ),
    );
    if (picked == null) return;

    if (_isTimeFuture(picked)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Impossible de saisir une heure future'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() => isCheckIn ? _checkIn = picked : _checkOut = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_checkIn == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Saisir l\'heure d\'arrivee reelle')),
      );
      return;
    }

    if (widget.canDirectEdit && widget.logId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Aucune ligne de pointage existante a modifier pour ce jour.',
          ),
          backgroundColor: AppColors.warning,
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    var success = true;
    if (widget.canDirectEdit) {
      success = await ref
          .read(attendanceProvider.notifier)
          .updateCorrection(
            logId: widget.logId!,
            checkIn: _asDateTime(widget.targetDate, _checkIn!),
            checkOut:
                _checkOut == null
                    ? null
                    : _asDateTime(widget.targetDate, _checkOut!),
            notes: _reasonCtrl.text.trim(),
          );
    } else {
      success = await ref
          .read(attendanceProvider.notifier)
          .requestCorrection(
            logId: widget.logId,
            date: widget.targetDate,
            checkIn: _asDateTime(widget.targetDate, _checkIn!),
            checkOut:
                _checkOut == null
                    ? null
                    : _asDateTime(widget.targetDate, _checkOut!),
            reason: _reasonCtrl.text.trim(),
          );
    }

    if (!mounted) return;
    setState(() => _submitting = false);
    if (!success) {
      final message =
          ref.read(attendanceProvider).error ??
          'Impossible d envoyer la modification pour le moment.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), backgroundColor: AppColors.danger),
      );
      return;
    }
    Navigator.pop(context);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          widget.canDirectEdit
              ? 'Pointage du ${DateFormat('d MMM', 'fr_FR').format(widget.targetDate)} modifie.'
              : 'Demande du ${DateFormat('d MMM', 'fr_FR').format(widget.targetDate)} soumise au RH - vous serez notifie de la decision.',
        ),
        backgroundColor: AppColors.rh,
        duration: const Duration(seconds: 4),
      ),
    );
  }

  DateTime _asDateTime(DateTime date, TimeOfDay time) {
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        20,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFF2A3C5A),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Modifier le ${DateFormat('EEEE d MMMM', 'fr_FR').format(widget.targetDate)}',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: Color(0xFFE2EAF6),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              widget.canDirectEdit
                  ? 'La correction sera appliquee au dossier de pointage.'
                  : 'La demande sera transmise au RH pour validation.',
              style: const TextStyle(fontSize: 12, color: Color(0xFF7A9CC0)),
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: _TimeTile(
                    label: 'Arrivee reelle *',
                    value: _checkIn,
                    onTap: () => _pickTime(isCheckIn: true),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _TimeTile(
                    label: 'Depart reel',
                    value: _checkOut,
                    onTap: () => _pickTime(isCheckIn: false),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reasonCtrl,
              maxLines: 2,
              maxLength: 200,
              style: const TextStyle(fontSize: 13, color: Color(0xFFE2EAF6)),
              decoration: InputDecoration(
                hintText: 'Motif (ex: oubli de pointage a 8h)',
                hintStyle: const TextStyle(
                  fontSize: 13,
                  color: Color(0xFF7A9CC0),
                ),
                filled: true,
                fillColor: const Color(0xFF0C1525),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: Color(0xFF1A2B44)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: Color(0xFF1A2B44)),
                ),
              ),
              validator:
                  (value) =>
                      value == null || value.trim().isEmpty
                          ? 'Motif obligatoire'
                          : null,
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _submitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.rh,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child:
                    _submitting
                        ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                        : Text(
                          widget.canDirectEdit
                              ? 'Modifier'
                              : 'Demander une modification',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TimeTile extends StatelessWidget {
  final String label;
  final TimeOfDay? value;
  final VoidCallback onTap;

  const _TimeTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFF0C1525),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFF1A2B44)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(fontSize: 10, color: Color(0xFF7A9CC0)),
            ),
            const SizedBox(height: 4),
            Text(
              value != null
                  ? '${value!.hour.toString().padLeft(2, '0')}:${value!.minute.toString().padLeft(2, '0')}'
                  : '--:--',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: value != null ? AppColors.rh : const Color(0xFF6F86A5),
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TimeChip extends StatelessWidget {
  final String label;
  final String value;

  const _TimeChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: const Color(0xFF0C1525),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFF1A2B44)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 10, color: Color(0xFF7A9CC0)),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 18,
              color: Color(0xFFE2EAF6),
              fontWeight: FontWeight.w600,
              fontFeatures: [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusBadge({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.34)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final String label;

  const _MenuItem({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: const Color(0xFF7A9CC0)),
        const SizedBox(width: 10),
        Text(
          label,
          style: const TextStyle(fontSize: 13, color: Color(0xFFE2EAF6)),
        ),
      ],
    );
  }
}

class _WeekStat extends StatelessWidget {
  final String value;
  final String label;
  final Color color;

  const _WeekStat({
    required this.value,
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w500,
            color: color,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(fontSize: 10, color: Color(0xFF6F86A5)),
        ),
      ],
    );
  }
}

class AttendanceDaySummary {
  final DateTime date;
  final String dayLabel;
  final int? logId;
  final bool isAbsent;
  final int workedMinutes;
  final int lateMinutes;
  final int sessionsCount;
  final int breakMinutes;
  final int overtimeMinutes;
  final double estimatedEarnings;
  final String checkInFormatted;
  final String checkOutFormatted;
  final List<AttendanceLog> sessions;

  const AttendanceDaySummary({
    required this.date,
    required this.dayLabel,
    this.logId,
    required this.isAbsent,
    required this.workedMinutes,
    required this.lateMinutes,
    required this.sessionsCount,
    required this.breakMinutes,
    required this.overtimeMinutes,
    required this.estimatedEarnings,
    required this.checkInFormatted,
    required this.checkOutFormatted,
    required this.sessions,
  });

  factory AttendanceDaySummary.fromLogs({
    required DateTime date,
    required String dayLabel,
    required List<AttendanceLog> logs,
  }) {
    final sorted = [...logs]
      ..sort((a, b) => a.sessionNumber.compareTo(b.sessionNumber));
    final first = sorted.isEmpty ? null : sorted.first;
    final last = sorted.isEmpty ? null : sorted.last;
    final hours = sorted.fold<double>(
      0,
      (sum, log) => sum + (log.workedHours ?? 0),
    );
    final workedMinutes = (hours * 60).round();
    final overtimeMinutes = sorted.fold<int>(
      0,
      (sum, log) => sum + (((log.overtimeHours ?? 0) * 60).round()),
    );
    final breakMinutes = _estimateBreakMinutes(sorted);
    return AttendanceDaySummary(
      date: date,
      dayLabel: dayLabel,
      logId: last?.id == 0 ? null : last?.id,
      isAbsent: sorted.isEmpty || first?.checkIn == null,
      workedMinutes: workedMinutes,
      lateMinutes: sorted.fold<int>(
        0,
        (sum, log) => sum + (log.lateMinutes ?? 0),
      ),
      sessionsCount: sorted.length,
      breakMinutes: breakMinutes,
      overtimeMinutes: overtimeMinutes,
      estimatedEarnings: hours * 550,
      checkInFormatted: _AttendanceScreenState._formatTime(first?.checkIn),
      checkOutFormatted: _AttendanceScreenState._formatTime(last?.checkOut),
      sessions: sorted,
    );
  }

  String get hoursFormatted {
    final hours = workedMinutes ~/ 60;
    final mins = workedMinutes % 60;
    return '${hours}h${mins.toString().padLeft(2, '0')}';
  }

  String get overtimeFormatted {
    final hours = overtimeMinutes ~/ 60;
    final mins = overtimeMinutes % 60;
    return overtimeMinutes <= 0
        ? '0h00'
        : '${hours}h${mins.toString().padLeft(2, '0')}';
  }

  static int _estimateBreakMinutes(List<AttendanceLog> sessions) {
    if (sessions.length < 2) return 0;
    var total = 0;
    for (var i = 0; i < sessions.length - 1; i++) {
      final currentOut = sessions[i].checkOut;
      final nextIn = sessions[i + 1].checkIn;
      if (currentOut == null || nextIn == null) continue;
      final gap = nextIn.difference(currentOut).inMinutes;
      if (gap > 0 && gap < 12 * 60) total += gap;
    }
    return total;
  }
}
