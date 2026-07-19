import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/geo_attendance_session.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';
import 'package:leopardo_employee/features/smart_attendance/providers/smart_attendance_provider.dart';
import 'package:leopardo_employee/features/smart_attendance/screens/attendance_mode_picker_screen.dart';

/// Écran principal du module Pointage Intelligent.
///
/// Affiche :
/// - Le mode de pointage effectif (GPS auto / QR / Manuel)
/// - Si mode GPS : statut zone (dedans/dehors) avec badge coloré
/// - Bouton d'activation du monitoring GPS si non démarré
/// - Liste des dernières sessions GPS avec badge de statut
/// - Bouton pour changer sa préférence (si non forcé par l'entreprise)
class SmartAttendanceScreen extends ConsumerStatefulWidget {
  const SmartAttendanceScreen({super.key});

  @override
  ConsumerState<SmartAttendanceScreen> createState() =>
      _SmartAttendanceScreenState();
}

class _SmartAttendanceScreenState extends ConsumerState<SmartAttendanceScreen> {
  // Palette de couleurs cohérente avec le reste de l'app
  static const Color _bg = Color(0xFF0B1120);
  static const Color _card = Color(0xFF111B2E);
  static const Color _text = Color(0xFFE2EAF6);
  static const Color _muted = Color(0xFF7A9CC0);
  static const Color _border = Color(0xFF1A2B44);
  static const Color _accent = Color(0xFF2196F3);

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
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _text),
          onPressed: () => context.pop(),
        ),
        title: const Text(
          'Pointage Intelligent',
          style: TextStyle(
            color: _text,
            fontWeight: FontWeight.w600,
            fontSize: 18,
          ),
        ),
        actions: [
          // Bouton de rafraîchissement
          IconButton(
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
            child: CircularProgressIndicator(color: Color(0xFF2196F3)),
          ),
          error: (error, _) => _ErrorPanel(
            message: 'Impossible de charger la configuration.\n$error',
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
    required SmartAttendanceConfig config,
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
              await ref
                  .read(activeGeoSessionProvider.notifier)
                  .startMonitoring(config);
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
              const Text(
                'Sessions récentes',
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
                      strokeWidth: 2, color: Color(0xFF2196F3)),
                ),
            ],
          ),
        ),

        // Erreur de chargement des sessions
        if (sessionState.error != null)
          _ErrorBanner(message: sessionState.error!),

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
  Future<void> _openModePicker(String currentMode) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => AttendanceModePickerScreen(currentMode: currentMode),
      ),
    );

    // Si l'utilisateur a sauvegardé un nouveau mode, rafraîchir
    if (result == true) {
      ref.invalidate(smartAttendanceConfigProvider);
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Composants internes
// ─────────────────────────────────────────────────────────────────────────────

/// Carte affichant le mode de pointage actif.
class _ModeStatusCard extends StatelessWidget {
  final String effectiveMode;
  final SmartAttendanceConfig config;
  final bool canChangeMode;
  final VoidCallback onChangeTap;

  static const Color _card = Color(0xFF111B2E);
  static const Color _text = Color(0xFFE2EAF6);
  static const Color _muted = Color(0xFF7A9CC0);
  static const Color _border = Color(0xFF1A2B44);
  static const Color _accent = Color(0xFF2196F3);

  const _ModeStatusCard({
    required this.effectiveMode,
    required this.config,
    required this.canChangeMode,
    required this.onChangeTap,
  });

  String get _modeLabelFr {
    switch (effectiveMode) {
      case 'gps_auto':
        return 'GPS Automatique';
      case 'qr_code':
        return 'QR Code';
      case 'manual':
      default:
        return 'Manuel';
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
        return const Color(0xFF4CAF50);
      case 'qr_code':
        return const Color(0xFF9C27B0);
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
                  color: _modeColor.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(_modeIcon, color: _modeColor, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Mode actif',
                      style: TextStyle(color: _muted, fontSize: 12),
                    ),
                    Text(
                      _modeLabelFr,
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
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: _modeColor.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    'Imposé',
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
            const Divider(color: Color(0xFF1A2B44), height: 1),
            const SizedBox(height: 12),
            GestureDetector(
              onTap: onChangeTap,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.settings_rounded, color: _accent, size: 16),
                  const SizedBox(width: 6),
                  Text(
                    'Changer mon mode de pointage',
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
  final SmartAttendanceConfig config;
  final ActiveGeoSessionState sessionState;
  final VoidCallback onStartMonitoring;
  final VoidCallback onStopMonitoring;

  static const Color _card = Color(0xFF111B2E);
  static const Color _text = Color(0xFFE2EAF6);
  static const Color _muted = Color(0xFF7A9CC0);
  static const Color _border = Color(0xFF1A2B44);
  static const Color _green = Color(0xFF4CAF50);
  static const Color _red = Color(0xFFEF5350);
  static const Color _accent = Color(0xFF2196F3);

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
          const Row(
            children: [
              Icon(Icons.radar_rounded, color: _muted, size: 18),
              SizedBox(width: 8),
              Text(
                'Surveillance de zone',
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
                            color: _green.withOpacity(0.5),
                            blurRadius: 8,
                            spreadRadius: 2,
                          ),
                        ]
                      : null,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                isMonitoring ? 'Surveillance active' : 'Surveillance inactive',
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
                color: _green.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: _green.withOpacity(0.3)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.check_circle_outline_rounded,
                      color: _green, size: 16),
                  const SizedBox(width: 8),
                  Text(
                    'Présence en cours depuis ${_formatTime(sessionState.activeSession!.startedAt)}',
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
                color: const Color(0xFFFFA726).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
                border:
                    Border.all(color: const Color(0xFFFFA726).withOpacity(0.3)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.warning_amber_rounded,
                      color: Color(0xFFFFA726), size: 16),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'La zone GPS de votre entreprise n\'est pas encore configurée.',
                      style: TextStyle(color: Color(0xFFFFA726), fontSize: 12),
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
                      ? 'Désactiver le GPS automatique'
                      : 'Activer le GPS automatique',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor:
                      isMonitoring ? _red.withOpacity(0.8) : _accent,
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

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final diff = now.difference(dateTime);
    if (diff.inMinutes < 60) return '${diff.inMinutes} min';
    return DateFormat('HH:mm').format(dateTime);
  }
}

/// Carte d'une session GPS avec badge de statut.
class _SessionCard extends StatelessWidget {
  final GeoAttendanceSession session;

  static const Color _card = Color(0xFF111B2E);
  static const Color _text = Color(0xFFE2EAF6);
  static const Color _muted = Color(0xFF7A9CC0);
  static const Color _border = Color(0xFF1A2B44);

  const _SessionCard({required this.session});

  Color get _statusColor {
    switch (session.status) {
      case 'approved':
        return const Color(0xFF4CAF50);
      case 'detected':
      case 'pending_validation':
        return const Color(0xFFFFA726);
      case 'rejected':
        return const Color(0xFFEF5350);
      case 'cancelled':
        return const Color(0xFF607D8B);
      default:
        return const Color(0xFF7A9CC0);
    }
  }

  String get _statusLabel {
    switch (session.status) {
      case 'approved':
        return 'Approuvée';
      case 'detected':
        return 'Détectée';
      case 'pending_validation':
        return 'En validation';
      case 'rejected':
        return 'Rejetée';
      case 'cancelled':
        return 'Annulée';
      default:
        return session.status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final dateFmt = DateFormat('dd/MM/yyyy');
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
                          horizontal: 10, vertical: 3),
                      decoration: BoxDecoration(
                        color: _statusColor.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        _statusLabel,
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
                      const Text(
                        'En cours',
                        style:
                            TextStyle(color: Color(0xFF4CAF50), fontSize: 12),
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
            const Icon(Icons.error_outline_rounded,
                color: Color(0xFFEF5350), size: 48),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Color(0xFF7A9CC0), fontSize: 13),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Réessayer'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2196F3),
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
class _ErrorBanner extends StatelessWidget {
  final String message;

  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFEF5350).withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFEF5350).withOpacity(0.3)),
      ),
      child: Text(
        message,
        style: const TextStyle(color: Color(0xFFEF9A9A), fontSize: 12),
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
        color: const Color(0xFF111B2E),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF1A2B44)),
      ),
      child: Column(
        children: [
          Icon(Icons.history_toggle_off_rounded,
              color: const Color(0xFF7A9CC0).withOpacity(0.5), size: 40),
          const SizedBox(height: 12),
          const Text(
            'Aucune session GPS pour le moment.',
            style: TextStyle(color: Color(0xFF7A9CC0), fontSize: 13),
          ),
        ],
      ),
    );
  }
}
