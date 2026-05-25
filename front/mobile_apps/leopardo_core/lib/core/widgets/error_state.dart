import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_typography.dart';

/// Consistent error display widget for API failures and exceptions.
///
/// Matches the EmptyState pattern but with error-specific styling
/// and an optional retry action.
class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, this.onRetry, this.icon});

  final String message;
  final VoidCallback? onRetry;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon ?? Icons.error_outline_rounded,
              size: 56,
              color: AppColors.danger,
            ),
            const SizedBox(height: 16),
            Text(
              message,
              style: AppTypography.bodySmall.copyWith(color: AppColors.danger),
              textAlign: TextAlign.center,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Reessayer'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.rh,
                  side: BorderSide(color: AppColors.borderFor(context)),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
