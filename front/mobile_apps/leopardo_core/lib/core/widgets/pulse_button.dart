import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// Bouton de pointage premium partagé Employee/Manager.
///
/// v4 — remplace l'ancien fond plein rouge/vert par un anneau progressif en
/// glassmorphism sombre : plus lisible en plein soleil, cohérent avec les
/// anneaux utilisés dans [AttendanceScreen], et sans la brutalité visuelle
/// d'un disque rouge saturé pour l'action "sortir".
class PulseButton extends StatefulWidget {
  final bool isCheckedIn;
  final bool isLoading;
  final VoidCallback? onTap;

  /// Taille du diamètre extérieur. Par défaut 184 (parité avec l'ancien
  /// design), réductible pour les écrans compacts.
  final double size;

  const PulseButton({
    super.key,
    required this.isCheckedIn,
    required this.isLoading,
    this.onTap,
    this.size = 184,
  });

  @override
  State<PulseButton> createState() => _PulseButtonState();
}

class _PulseButtonState extends State<PulseButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(seconds: 2),
      vsync: this,
    )..repeat(reverse: true);
    _animation = Tween<double>(
      begin: 1.0,
      end: 1.08,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label:
          widget.isCheckedIn
              ? 'Se deconnecter du pointage'
              : 'Pointer mon arrivee',
      button: true,
      enabled: !widget.isLoading,
      child: GestureDetector(
        onTap:
            widget.isLoading
                ? null
                : () {
                  HapticFeedback.mediumImpact();
                  widget.onTap?.call();
                },
        child: AnimatedBuilder(
          animation: _animation,
          builder: (context, child) {
            final accent = widget.isCheckedIn ? AppColors.danger : AppColors.rh;
            final coreColors =
                widget.isCheckedIn
                    ? const [
                      AppColors.mobilePunchOutGradientStart,
                      AppColors.mobilePunchOutGradientEnd,
                    ]
                    : const [
                      AppColors.mobilePunchInGradientStart,
                      AppColors.rhDark,
                    ];

            return Transform.scale(
              scale: widget.isLoading ? 1.0 : _animation.value,
              child: Container(
                width: widget.size,
                height: widget.size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: accent.withValues(alpha: 0.08),
                  border: Border.all(
                    color: accent.withValues(alpha: 0.28),
                    width: widget.size * 0.085,
                  ),
                ),
                child: Padding(
                  padding: EdgeInsets.all(widget.size * 0.095),
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: accent.withValues(alpha: 0.13),
                      border: Border.all(
                        color: accent.withValues(alpha: 0.42),
                        width: widget.size * 0.053,
                      ),
                    ),
                    child: Padding(
                      padding: EdgeInsets.all(widget.size * 0.095),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: coreColors,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: accent.withValues(alpha: 0.28),
                              blurRadius: 28,
                              spreadRadius:
                                  widget.isLoading ? 4 : 8 * _animation.value,
                            ),
                          ],
                        ),
                        child: Center(
                          child:
                              widget.isLoading
                                  ? const SizedBox(
                                    width: 30,
                                    height: 30,
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                      strokeWidth: 2.4,
                                    ),
                                  )
                                  : Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        widget.isCheckedIn
                                            ? Icons.logout_rounded
                                            : Icons.fingerprint_rounded,
                                        color: Colors.white,
                                        size: widget.size * 0.19,
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        widget.isCheckedIn
                                            ? 'SORTIR'
                                            : 'POINTER',
                                        style: AppTypography.subtitle.copyWith(
                                          color: Colors.white,
                                          fontSize: 18,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 0.8,
                                        ),
                                      ),
                                      const SizedBox(height: 3),
                                      Text(
                                        widget.isCheckedIn
                                            ? 'Fin de journee'
                                            : 'Arrivee',
                                        style: AppTypography.caption.copyWith(
                                          color: Colors.white.withValues(
                                            alpha: 0.82,
                                          ),
                                          fontWeight: FontWeight.w600,
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
            );
          },
        ),
      ),
    );
  }
}
