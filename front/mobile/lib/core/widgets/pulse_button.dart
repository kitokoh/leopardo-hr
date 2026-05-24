import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

class PulseButton extends StatefulWidget {
  final bool isCheckedIn;
  final bool isLoading;
  final VoidCallback? onTap;

  const PulseButton({
    super.key,
    required this.isCheckedIn,
    required this.isLoading,
    this.onTap,
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
            return Transform.scale(
              scale: widget.isLoading ? 1.0 : _animation.value,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: 184,
                height: 184,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors:
                        widget.isCheckedIn
                            ? const [Color(0xFFEF4444), Color(0xFFB91C1C)]
                            : const [AppColors.rh, Color(0xFF06B6D4)],
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: (widget.isCheckedIn
                              ? AppColors.danger
                              : AppColors.rh)
                          .withValues(alpha: 0.32),
                      blurRadius: 34,
                      spreadRadius:
                          widget.isLoading ? 4 : 12 * _animation.value,
                    ),
                  ],
                ),
                child: Center(
                  child:
                      widget.isLoading
                          ? const CircularProgressIndicator(color: Colors.white)
                          : Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                widget.isCheckedIn
                                    ? Icons.logout_rounded
                                    : Icons.fingerprint_rounded,
                                color: Colors.white,
                                size: 38,
                              ),
                              const SizedBox(height: 10),
                              Text(
                                widget.isCheckedIn ? 'SORTIR' : 'POINTER',
                                style: AppTypography.subtitle.copyWith(
                                  color: Colors.white,
                                  fontSize: 21,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.8,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                widget.isCheckedIn
                                    ? 'Fin de journee'
                                    : 'Arrivee',
                                style: AppTypography.caption.copyWith(
                                  color: Colors.white.withValues(alpha: 0.82),
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
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
