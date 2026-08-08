import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'dart:io' show Platform;
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:permission_handler/permission_handler.dart';

/// Ã‰cran d'onboarding pour la permission "Toujours autoriser" la localisation
/// sur Android 11+ (ACCESS_BACKGROUND_LOCATION ne peut pas Ãªtre demandÃ©e
/// dans le dialog standard â€” l'utilisateur doit aller dans les paramÃ¨tres systÃ¨me).
///
/// Ã€ afficher une seule fois, avant l'activation du monitoring GPS.
/// Naviguer vers cet Ã©cran depuis SmartAttendanceScreen quand :
///   - Platform.isAndroid
///   - backgroundLocationPermission n'est pas granted
class BackgroundPermissionOnboardingScreen extends StatefulWidget {
  /// Route de destination aprÃ¨s l'onboarding (par dÃ©faut : retour en arriÃ¨re)
  final String? nextRoute;

  const BackgroundPermissionOnboardingScreen({super.key, this.nextRoute});

  @override
  State<BackgroundPermissionOnboardingScreen> createState() =>
      _BackgroundPermissionOnboardingScreenState();
}

class _BackgroundPermissionOnboardingScreenState
    extends State<BackgroundPermissionOnboardingScreen> {
  static const Color _bg = AppColors.mobileDarkBg;
  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _accent = AppColors.mobileAccentTeal;

  bool _isLoading = false;

  Future<void> _openSettings() async {
    setState(() => _isLoading = true);
    try {
      await openAppSettings();
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _checkAndContinue() async {
    if (!Platform.isAndroid) {
      _navigate();
      return;
    }
    final status = await Permission.locationAlways.status;
    if (status.isGranted) {
      _navigate();
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Permission "Toujours autoriser" requise pour le pointage automatique.',
            ),
            duration: Duration(seconds: 3),
          ),
        );
      }
    }
  }

  void _navigate() {
    if (widget.nextRoute != null) {
      context.go(widget.nextRoute!);
    } else {
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _bg,
        elevation: 0,
        leading: IconButton(
          tooltip: 'Fermer',
          icon: const Icon(Icons.close_rounded, color: Colors.white70),
          onPressed: () => context.pop(),
        ),
        title: const Text(
          'Autoriser la localisation',
          style: TextStyle(
              color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Spacer(),
              // Illustration
              Container(
                padding: const EdgeInsets.all(28),
                decoration: BoxDecoration(
                  color: _card,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.my_location_rounded,
                  size: 72,
                  color: _accent,
                ),
              ),
              const SizedBox(height: 32),

              const Text(
                'Pointage automatique GPS',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 16),

              const Text(
                'Pour dÃ©tecter automatiquement votre arrivÃ©e et votre dÃ©part du bureau, '
                'Leopardo a besoin d\'accÃ©der Ã  votre position mÃªme quand l\'application '
                'est fermÃ©e.',
                textAlign: TextAlign.center,
                style:
                    TextStyle(color: Colors.white70, fontSize: 15, height: 1.5),
              ),
              const SizedBox(height: 24),

              // Steps
              _StepCard(
                step: '1',
                text: 'Appuyez sur "Ouvrir les paramÃ¨tres" ci-dessous',
              ),
              const SizedBox(height: 10),
              _StepCard(
                step: '2',
                text: 'Touchez "Autorisations" â†’ "Position"',
              ),
              const SizedBox(height: 10),
              _StepCard(
                step: '3',
                text: 'SÃ©lectionnez "Toujours autoriser"',
              ),

              const Spacer(),

              // CTA principal
              FilledButton.icon(
                onPressed: _isLoading ? null : _openSettings,
                icon: _isLoading
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.settings_rounded),
                label: const Text('Ouvrir les paramÃ¨tres'),
                style: FilledButton.styleFrom(
                  backgroundColor: _accent,
                  foregroundColor: Colors.black,
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 12),

              // Bouton "DÃ©jÃ  fait"
              OutlinedButton(
                onPressed: _checkAndContinue,
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white70,
                  side: const BorderSide(color: Colors.white24),
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text('J\'ai dÃ©jÃ  autorisÃ© â€” Continuer'),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.step, required this.text});
  final String step;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AppColors.mobileAccentTeal.withValues(alpha: 0.15),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.mobileAccentTeal, width: 1.5),
          ),
          child: Text(
            step,
            style: const TextStyle(
              color: AppColors.mobileAccentTeal,
              fontWeight: FontWeight.bold,
              fontSize: 13,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
                color: Colors.white70, fontSize: 14, height: 1.4),
          ),
        ),
      ],
    );
  }
}

