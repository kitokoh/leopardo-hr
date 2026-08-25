import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_employee/features/attendance_geo/data/models/geo_attendance_session.dart';
import 'package:leopardo_employee/features/attendance_geo/data/models/attendance_geo_config.dart';
import 'package:leopardo_employee/features/attendance_geo/providers/attendance_geo_provider.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran principal du module Pointage Intelligent.
///
/// Affiche :
/// - Le mode de pointage effectif (GPS auto / QR / Manuel)
/// - Si mode GPS : statut zone (dedans/dehors) avec badge coloré
/// - Bouton d'activation du monitoring GPS si non démarré
/// - Liste des dernières sessions GPS avec badge de statut
/// - Bouton pour changer sa préférence (si non forcé par l'entreprise)
class AttendanceGeoScreen extends ConsumerStatefulWidget {
  const AttendanceGeoScreen({super.key});

  @override
  ConsumerState<AttendanceGeoScreen> createState() =>
      _AttendanceGeoScreenState();
}

class _AttendanceGeoScreenState extends ConsumerState<AttendanceGeoScreen> {
  // Palette de couleurs cohérente avec le reste de l'app
  static const Color _bg = AppColors.mobileDarkBg;
  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _text = AppColors.mobileDarkText;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _accent = AppColors.mobileAccentBlue;

  @override
  Widget build(BuildContext context) {
    final configAsync = ref.watch(smartAttendanceConfigProvider);
    final sessionState = ref.watch(activeGeoSessionProvider);
    final effectiveMode = ref.watch(attendanceModeProvider);
    final canChangeMode = ref.watch(canChangeAttendanceModeProvider);

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _bg,
        elevation: 0,
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _text),
          onPressed: () => context.pop(),
        ),
        title: Text(
          context.l10n.smartAttendanceSmart,
          style: TextStyle(
            color: _text,
            fontWeight: FontWeight.w600,
            fontSize: 18,
          ),
        ),
        actions: [
          // Bouton de rafraîchissement
          IconButton(
            tooltip: context.l10n.attendanceRefresh,
            icon: const Icon(Icons.refresh_rounded, color: _muted),
            onPressed: () {
              ref.invalidate(smartAttendanceConfigProvider);
              ref.read(activeGeoSessionProvider.notifier).refresh();
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        color: _accent,
        backgroundColor: _card,
        onRefresh: () async {
          ref.invalidate(smartAttendanceConfigProvider);
          await ref.read(activeGeoSessionProvider.notifier).refresh();
        },
        child: configAsync.when(
          loading: () => const Center(
            child: CircularProgressIndicator(color: AppColors.mobileAccentBlue),
          ),
          error: (error, _) => _ErrorPanel(
            message: context.l10n.saConfigLoadError(error.toString()),
            onRetry: () => ref.invalidate(smartAttendanceConfigProvider),
          ),
          data: (config) => _buildBody(
            context: context,
            config: config,
            sessionState: sessionState,
            effectiveMode: effectiveMode,
            canChangeMode: canChangeMode,
          ),
        ),
      ),
    );
  }

  Widget _buildBody({
    required BuildContext context,
    required AttendanceGeoConfig config,
    required ActiveGeoSessionState sessionState,
    required String effectiveMode,
    required bool canChangeMode,
  }) {
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      children: [
        // Section mode effectif
        _ModeStatusCard(
          effectiveMode: effectiveMode,
          config: config,
          canChangeMode: canChangeMode,
          onChangeTap: () => _openModePicker(effectiveMode),
        ),

        const SizedBox(height: 20),

        // Section statut zone GPS (visible uniquement en mode GPS auto)
        if (effectiveMode == 'gps_auto') ...[
          _GpsZoneStatusCard(
            config: config,
            sessionState: sessionState,
            onStartMonitoring: () async {
              // #4625 : startMonitoring capture désormais les erreurs dans
              // l'état du provider — on affiche un retour utilisateur au lieu
              // de laisser l'exception remonter au framework.
              final notifier = ref.read(activeGeoSessionProvider.notifier);
              await notifier.startMonitoring(config);
              final current = ref.read(activeGeoSessionProvider);
              if (current.error != null && !current.isMonitoring) {
                if (!context.mounted) return;
                ScaffoldMessenger.of(context)
                  ..hideCurrentSnackBar()
                  ..showSnackBar(
                    SnackBar(
                      content: Text(_localizeSaError(context, current.error!)),
                    ),
                  );
              }
            },
            onStopMonitoring: () {
              ref.read(activeGeoSessionProvider.notifier).stopMonitoring();
            },
          ),
          const SizedBox(height: 20),
        ],

        // Titre section sessions récentes
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                context.l10n.saRecentSessions,
                style: TextStyle(
                  color: _text,
                  fontWeight: FontWeight.w600,
                  fontSize: 16,
                ),
              ),
              if (sessionState.isLoading)
                const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: AppColors.mobileAccentBlue,
                  ),
                ),
            ],
          ),
        ),

        // Erreur de chargement des sessions
        if (sessionState.error != null)
          _ErrorBanner(message: _localizeSaError(context, sessionState.error!)),

        // Liste des sessions ou message vide
        if (sessionState.recentSessions.isEmpty && !sessionState.isLoading)
          _EmptySessionsPanel()
        else
          ...sessionState.recentSessions.take(10).map(
                (session) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _SessionCard(session: session),
                ),
              ),

        // Espace en bas
        const SizedBox(height: 32),
      ],
    );
  }

  /// Ouvre l'écran de sélection du mode de pointage.
  ///
  /// #3958 : navigation par route GoRouter (/attendance/geo/mode) — plus de
  /// push MaterialPageRoute hors table de routes (deep-links, état de
  /// navigation, retour arrière système cohérents).
  Future<void> _openModePicker(String currentMode) async {
    final result = await context.push<bool>(
      '/attendance/geo/mode',
      extra: currentMode,
    );

    // Si l'utilisateur a sauvegardé un nouveau mode, rafraîchir
    if (result == true) {
      ref.invalidate(smartAttendanceConfigProvider);
    }
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Composants internes
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/// Carte affichant le mode de pointage actif.
class _ModeStatusCard extends StatelessWidget {
  final String effectiveMode;
  final AttendanceGeoConfig config;
  final bool canChangeMode;
  final VoidCallback onChangeTap;

  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _border = AppColors.mobileDarkBorder;
  static const Color _accent = AppColors.mobileAccentBlue;

  const _ModeStatusCard({
    required this.effectiveMode,
    required this.config,
    required this.canChangeMode,
    required this.onChangeTap,
  });

  String _modeLabelFr(BuildContext context) {
    switch (effectiveMode) {
      case 'gps_auto':
        return context.l10n.smartAttendanceGpsAuto;
      case 'qr_code':
        return context.l10n.smartAttendanceQr;
      case 'manual':
      default:
        return context.l10n.smartAttendanceManual;
    }
  }

  IconData get _modeIcon {
    switch (effectiveMode) {
      case 'gps_auto':
        return Icons.location_on_rounded;
      case 'qr_code':
        return Icons.qr_code_scanner_rounded;
      case 'manual':
      default:
        return Icons.touch_app_rounded;
    }
  }

  Color get _modeColor {
    switch (effectiveMode) {
      case 'gps_auto':
        return AppColors.mobileAccentGreen;
      case 'qr_code':
        return AppColors.mobileAccentPurple;
      case 'manual':
      default:
        return _muted;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: _modeColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(_modeIcon, color: _modeColor, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.smartAttendanceActiveMode,
                      style: TextStyle(color: _muted, fontSize: 12),
                    ),
                    Text(
                      _modeLabelFr(context),
                      style: TextStyle(
                        color: _modeColor,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),
              // Badge "Imposé" si mode forcé
              if (config.hasForced)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: _modeColor.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    context.l10n.saForced,
                    style: TextStyle(
                      color: _modeColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
            ],
          ),

          // Bouton de changement de mode si autorisé
          if (canChangeMode) ...[
            const SizedBox(height: 14),
            const Divider(color: AppColors.mobileDarkBorder, height: 1),
            const SizedBox(height: 12),
            GestureDetector(
              onTap: onChangeTap,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.settings_rounded, color: _accent, size: 16),
                  const SizedBox(width: 6),
                  Text(
                    context.l10n.smartAttendanceChangeMode,
                    style: TextStyle(
                      color: _accent,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Carte affichant l'état de la zone GPS et les contrôles de monitoring.
class _GpsZoneStatusCard extends StatelessWidget {
  final AttendanceGeoConfig config;
  final ActiveGeoSessionState sessionState;
  final VoidCallback onStartMonitoring;
  final VoidCallback onStopMonitoring;

  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _text = AppColors.mobileDarkText;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _border = AppColors.mobileDarkBorder;
  static const Color _green = AppColors.mobileAccentGreen;
  static const Color _red = AppColors.mobileAccentRedLight;
  static const Color _accent = AppColors.mobileAccentBlue;

  const _GpsZoneStatusCard({
    required this.config,
    required this.sessionState,
    required this.onStartMonitoring,
    required this.onStopMonitoring,
  });

  @override
  Widget build(BuildContext context) {
    final isMonitoring = sessionState.isMonitoring;
    final hasActiveSession = sessionState.activeSession != null;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Titre section
          Row(
            children: [
              const Icon(Icons.radar_rounded, color: _muted, size: 18),
              SizedBox(width: 8),
              Text(
                context.l10n.smartAttendanceZoneSurveillance,
                style: TextStyle(
                  color: _text,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Badge statut zone : actif/inactif
          Row(
            children: [
              // Indicateur d'activité
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isMonitoring ? _green : _muted,
                  boxShadow: isMonitoring
                      ? [
                          BoxShadow(
                            color: _green.withValues(alpha: 0.5),
                            blurRadius: 8,
                            spreadRadius: 2,
                          ),
                        ]
                      : null,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                isMonitoring
                    ? context.l10n.smartAttendanceSurveillanceActive
                    : context.l10n.smartAttendanceSurveillanceInactive,
                style: TextStyle(
                  color: isMonitoring ? _green : _muted,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),

          // Session active en cours
          if (hasActiveSession) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: _green.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: _green.withValues(alpha: 0.3)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.check_circle_outline_rounded,
                    color: _green,
                    size: 16,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    context.l10n.saPresenceInProgress(
                      _formatTime(
                          context, sessionState.activeSession!.startedAt),
                    ),
                    style: const TextStyle(
                      color: _green,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],

          // Configuration de zone manquante
          if (!config.hasValidZone) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.mobileAccentOrange.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: AppColors.mobileAccentOrange.withValues(alpha: 0.3),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.warning_amber_rounded,
                    color: AppColors.mobileAccentOrange,
                    size: 16,
                  ),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      context.l10n.saGpsZoneNotConfigured,
                      style: TextStyle(
                        color: AppColors.mobileAccentOrange,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],

          const SizedBox(height: 16),

          // Bouton démarrer / arrêter
          if (config.hasValidZone)
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: isMonitoring ? onStopMonitoring : onStartMonitoring,
                icon: Icon(
                  isMonitoring
                      ? Icons.pause_circle_rounded
                      : Icons.play_circle_rounded,
                  size: 20,
                ),
                label: Text(
                  isMonitoring
                      ? context.l10n.saDisableAutoGps
                      : context.l10n.saEnableAutoGps,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      isMonitoring ? _red.withValues(alpha: 0.8) : _accent,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  String _formatTime(BuildContext context, DateTime dateTime) {
    final now = DateTime.now();
    final diff = now.difference(dateTime);
    if (diff.inMinutes < 60) {
      return context.l10n.attendanceBreakMinutes(diff.inMinutes);
    }
    return DateFormat('HH:mm').format(dateTime);
  }
}

/// Carte d'une session GPS avec badge de statut.
class _SessionCard extends StatelessWidget {
  final GeoAttendanceSession session;

  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _text = AppColors.mobileDarkText;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _border = AppColors.mobileDarkBorder;

  const _SessionCard({required this.session});

  Color get _statusColor {
    switch (session.status) {
      case 'approved':
        return AppColors.mobileAccentGreen;
      case 'detected':
      case 'pending_validation':
        return AppColors.mobileAccentOrange;
      case 'rejected':
        return AppColors.mobileAccentRedLight;
      case 'cancelled':
        return AppColors.mobileAccentGrey;
      default:
        return AppColors.mobileDarkMuted;
    }
  }

  String _statusLabel(BuildContext context) {
    switch (session.status) {
      case 'approved':
        return context.l10n.saStatusApproved;
      case 'detected':
        return context.l10n.saStatusDetected;
      case 'pending_validation':
        return context.l10n.saStatusPending;
      case 'rejected':
        return context.l10n.saStatusRejected;
      case 'cancelled':
        return context.l10n.saStatusCancelled;
      default:
        return session.status;
    }
  }

  @override
  Widget build(BuildContext context) {
    // #4337 : pattern locale-aware (yMd suit la locale active) — le
    // dd/MM/yyyy en dur ignorait la préférence utilisateur (résiduel #4197).
    final dateFmt = DateFormat.yMd(deviceIntlDateLocale);
    final timeFmt = DateFormat('HH:mm');

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Indicateur de statut
          Container(
            width: 4,
            height: 52,
            decoration: BoxDecoration(
              color: _statusColor,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
          const SizedBox(width: 12),

          // Informations de la session
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      dateFmt.format(session.startedAt),
                      style: const TextStyle(
                        color: _text,
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    // Badge statut
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: _statusColor.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        _statusLabel(context),
                        style: TextStyle(
                          color: _statusColor,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Icon(Icons.login_rounded, color: _muted, size: 14),
                    const SizedBox(width: 4),
                    Text(
                      timeFmt.format(session.startedAt),
                      style: const TextStyle(color: _muted, fontSize: 12),
                    ),
                    if (session.endedAt != null) ...[
                      const SizedBox(width: 12),
                      Icon(Icons.logout_rounded, color: _muted, size: 14),
                      const SizedBox(width: 4),
                      Text(
                        timeFmt.format(session.endedAt!),
                        style: const TextStyle(color: _muted, fontSize: 12),
                      ),
                    ] else ...[
                      const SizedBox(width: 12),
                      Text(
                        context.l10n.attendanceStatusInProgress,
                        style: TextStyle(
                          color: AppColors.mobileAccentGreen,
                          fontSize: 12,
                        ),
                      ),
                    ],
                    if (session.durationFormatted != null) ...[
                      const Spacer(),
                      Text(
                        session.durationFormatted!,
                        style: const TextStyle(
                          color: _muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Panneau d'erreur réutilisable.
class _ErrorPanel extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorPanel({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              color: AppColors.mobileAccentRedLight,
              size: 48,
            ),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AppColors.mobileDarkMuted,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: Text(context.l10n.retry),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.mobileAccentBlue,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Bannière d'erreur inline.
/// #4303 : traduit les codes d'erreur du provider en messages localisés.
String _localizeSaError(BuildContext context, String code) {
  if (code == 'sa.sessionsLoadError') {
    return context.l10n.saSessionsLoadError;
  }
  if (code == 'sa.startMonitoringError') {
    return context.l10n.saStartMonitoringError;
  }
  if (code == 'sa.permissionDenied') {
    return context.l10n.saPermissionDenied;
  }
  return code;
}

class _ErrorBanner extends StatelessWidget {
  final String message;

  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.mobileAccentRedLight.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: AppColors.mobileAccentRedLight.withValues(alpha: 0.3),
        ),
      ),
      child: Text(
        message,
        style: const TextStyle(
          color: AppColors.mobileAccentRedSoft,
          fontSize: 12,
        ),
      ),
    );
  }
}

/// Panneau affiché quand la liste des sessions est vide.
class _EmptySessionsPanel extends StatelessWidget {
  const _EmptySessionsPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.mobileDarkSurface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.mobileDarkBorder),
      ),
      child: Column(
        children: [
          Icon(
            Icons.history_toggle_off_rounded,
            color: AppColors.mobileDarkMuted.withValues(alpha: 0.5),
            size: 40,
          ),
          const SizedBox(height: 12),
          Text(
            context.l10n.smartAttendanceNoGpsSessions,
            style: TextStyle(color: AppColors.mobileDarkMuted, fontSize: 13),
          ),
        ],
      ),
    );
  }
}
