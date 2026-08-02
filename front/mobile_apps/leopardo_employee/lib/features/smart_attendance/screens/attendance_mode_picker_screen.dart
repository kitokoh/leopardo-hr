import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_employee/features/smart_attendance/providers/smart_attendance_provider.dart';

/// Ã‰cran de sÃ©lection du mode de pointage prÃ©fÃ©rÃ© pour l'employÃ©.
///
/// AffichÃ© uniquement si l'entreprise n'impose pas de mode forcÃ©.
/// L'employÃ© peut choisir parmi : GPS Auto / QR Code / Manuel.
class AttendanceModePickerScreen extends ConsumerStatefulWidget {
  /// Mode actuellement actif (pour prÃ©-sÃ©lectionner)
  final String currentMode;

  const AttendanceModePickerScreen({
    super.key,
    required this.currentMode,
  });

  @override
  ConsumerState<AttendanceModePickerScreen> createState() =>
      _AttendanceModePickerScreenState();
}

class _AttendanceModePickerScreenState
    extends ConsumerState<AttendanceModePickerScreen> {
  // Couleurs de l'app (cohÃ©rentes avec AttendanceScreen)
  static const Color _bg = AppColors.mobileDarkBg;
  static const Color _text = AppColors.mobileDarkText;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _border = AppColors.mobileDarkBorder;
  static const Color _accent = AppColors.mobileAccentBlue;

  late String _selectedMode;
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _selectedMode = widget.currentMode;
  }

  /// Liste des modes disponibles avec leur libellÃ©, icÃ´ne et description.
  List<Map<String, dynamic>> get _modes => [
        {
          'id': 'gps_auto',
          'label': 'GPS Automatique',
          'icon': Icons.location_on_rounded,
          'color': AppColors.mobileAccentGreen,
          'description':
              'Votre prÃ©sence est dÃ©tectÃ©e automatiquement dÃ¨s que vous entrez '
                  'dans la zone de l\'entreprise. Aucune action requise de votre part.',
          'badge': 'RecommandÃ©',
        },
        {
          'id': 'qr_code',
          'label': 'QR Code',
          'icon': Icons.qr_code_scanner_rounded,
          'color': AppColors.mobileAccentPurple,
          'description':
              'Scannez le QR Code affichÃ© Ã  l\'entrÃ©e de l\'entreprise pour '
                  'pointer votre arrivÃ©e et votre dÃ©part.',
          'badge': null,
        },
        {
          'id': 'manual',
          'label': 'Manuel',
          'icon': Icons.touch_app_rounded,
          'color': _muted,
          'description':
              'Pointez manuellement en appuyant sur les boutons ArrivÃ©e et '
                  'DÃ©part dans l\'Ã©cran de prÃ©sence.',
          'badge': null,
        },
      ];

  Future<void> _confirmSelection() async {
    if (_isSaving) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      final repository = ref.read(smartAttendanceRepositoryProvider);
      await repository.updatePreference(_selectedMode);

      if (mounted) {
        // Invalidation du provider pour recharger la config
        ref.invalidate(smartAttendanceConfigProvider);
        context.pop(true); // Retourner true = succÃ¨s
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isSaving = false;
          _error =
              'Impossible de sauvegarder votre prÃ©fÃ©rence. VÃ©rifiez votre connexion.';
        });
      }
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
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _text),
          onPressed: () => context.pop(),
        ),
        title: const Text(
          'Mode de pointage',
          style: TextStyle(color: _text, fontWeight: FontWeight.w600),
        ),
      ),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // En-tÃªte descriptif
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              child: Text(
                'Choisissez comment vous souhaitez pointer votre prÃ©sence chaque jour.',
                style: TextStyle(
                  color: _muted,
                  fontSize: 14,
                  height: 1.5,
                ),
              ),
            ),

            // Liste des modes
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _modes.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final mode = _modes[index];
                  return _ModeGlassCard(
                    modeId: mode['id'] as String,
                    label: mode['label'] as String,
                    icon: mode['icon'] as IconData,
                    color: mode['color'] as Color,
                    description: mode['description'] as String,
                    badge: mode['badge'] as String?,
                    isSelected: _selectedMode == mode['id'],
                    onTap: () {
                      setState(() => _selectedMode = mode['id'] as String);
                    },
                  );
                },
              ),
            ),

            // Erreur Ã©ventuelle
            if (_error != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.mobileAccentRed.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: AppColors.mobileAccentRed.withValues(alpha: 0.4)),
                  ),
                  child: Text(
                    _error!,
                    style:
                        const TextStyle(color: AppColors.mobileAccentRedSoft, fontSize: 13),
                  ),
                ),
              ),

            // Bouton Confirmer
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSaving || _selectedMode == widget.currentMode
                      ? null
                      : _confirmSelection,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _accent,
                    disabledBackgroundColor: _border,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text(
                          'Confirmer',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte de sÃ©lection d'un mode de pointage.
class _ModeCard extends StatelessWidget {
  final String modeId;
  final String label;
  final IconData icon;
  final Color color;
  final String description;
  final String? badge;
  final bool isSelected;
  final VoidCallback onTap;

  static const Color _card = AppColors.mobileDarkSurface;
  static const Color _text = AppColors.mobileDarkText;
  static const Color _muted = AppColors.mobileDarkMuted;
  static const Color _border = AppColors.mobileDarkBorder;

  const _ModeGlassCard({
    required this.modeId,
    required this.label,
    required this.icon,
    required this.color,
    required this.description,
    this.badge,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? color.withValues(alpha: 0.12) : _card,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? color : _border,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // IcÃ´ne du mode
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(width: 14),

            // Contenu textuel
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        label,
                        style: TextStyle(
                          color: isSelected ? color : _text,
                          fontWeight: FontWeight.w600,
                          fontSize: 15,
                        ),
                      ),
                      if (badge != null) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            badge!,
                            style: TextStyle(
                              color: color,
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    description,
                    style: const TextStyle(
                      color: _muted,
                      fontSize: 12,
                      height: 1.5,
                    ),
                  ),
                ],
              ),
            ),

            // Indicateur de sÃ©lection
            const SizedBox(width: 8),
            Icon(
              isSelected
                  ? Icons.check_circle_rounded
                  : Icons.radio_button_unchecked_rounded,
              color: isSelected ? color : _muted,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }
}

