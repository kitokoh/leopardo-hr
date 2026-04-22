import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

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

class _PulseButtonState extends State<PulseButton> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(duration: const Duration(seconds: 2), vsync: this)..repeat(reverse: true);
    _animation = Tween<double>(begin: 1.0, end: 1.08).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: widget.isCheckedIn ? 'Se deconnecter du pointage' : 'Pointer mon arrivee',
      button: true,
      enabled: !widget.isLoading,
      child: GestureDetector(
        onTap: widget.isLoading
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
                width: 200,
                height: 200,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: widget.isCheckedIn ? Theme.of(context).colorScheme.error : Theme.of(context).primaryColor,
                  boxShadow: [
                    BoxShadow(
                      color: (widget.isCheckedIn ? Theme.of(context).colorScheme.error : Theme.of(context).primaryColor).withValues(alpha: 0.3),
                      blurRadius: 30,
                      spreadRadius: widget.isLoading ? 5 : 15 * _animation.value,
                    )
                  ],
                ),
                child: Center(
                  child: widget.isLoading
                      ? const CircularProgressIndicator(color: Colors.white)
                      : Text(
                          widget.isCheckedIn ? 'CHECK OUT' : 'CHECK IN',
                          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
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
