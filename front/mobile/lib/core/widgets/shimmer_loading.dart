import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

import '../theme/app_colors.dart';

class ShimmerLoading extends StatelessWidget {
  final double width;
  final double height;
  final double borderRadius;

  const ShimmerLoading({
    super.key,
    required this.width,
    required this.height,
    this.borderRadius = 8.0,
  });

  @override
  Widget build(BuildContext context) {
    final surface = AppColors.surfaceFor(context);
    return Shimmer.fromColors(
      baseColor: surface,
      highlightColor: surface.withValues(alpha: 0.55),
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(borderRadius),
        ),
      ),
    );
  }
}
