import 'dart:ui';
import 'package:flutter/material.dart';

/// A premium glassmorphism card component.
/// This is the standard surface container for the new Leopardo RH design system.
class GlassCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final double radius;
  final Color? color;
  final Color? borderColor;
  final double blurSigma;

  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16.0),
    this.margin,
    this.onTap,
    this.radius = 16.0,
    this.color,
    this.borderColor,
    this.blurSigma = 10.0,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final bgColor = color ??
        (isDark
            ? AppColors.mobileDarkGlass.withValues(alpha: 0.4)
            : Colors.white.withValues(alpha: 0.7));

    final defaultBorderColor = isDark
        ? Colors.white.withValues(alpha: 0.1)
        : Colors.black.withValues(alpha: 0.05);

    final shadowColor = isDark
        ? Colors.black.withValues(alpha: 0.2)
        : Colors.black.withValues(alpha: 0.05);

    Widget cardContent = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(
          color: borderColor ?? defaultBorderColor,
          width: 1.0,
        ),
        boxShadow: [
          BoxShadow(
            color: shadowColor,
            blurRadius: 16.0,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );

    Widget result = ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blurSigma, sigmaY: blurSigma),
        child: cardContent,
      ),
    );

    if (onTap != null) {
      result = GestureDetector(
        onTap: onTap,
        child: result,
      );
    }

    if (margin != null) {
      result = Padding(
        padding: margin!,
        child: result,
      );
    }

    return result;
  }
}
