import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

class LeopardoQrCard extends StatelessWidget {
  const LeopardoQrCard({
    super.key,
    required this.data,
    required this.title,
    required this.subtitle,
    this.expiresAt,
    this.copyLabel = 'Copier le code',
  });

  final String data;
  final String title;
  final String subtitle;
  final String? expiresAt;
  final String copyLabel;

  @override
  Widget build(BuildContext context) {
    final hasData = data.trim().isNotEmpty;

    return MobilePanel(
      color: MobileSurface.chip,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const MobileIconBubble(
                icon: Icons.qr_code_2_rounded,
                color: AppColors.rh,
                size: 34,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.text,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Center(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.18),
                    blurRadius: 18,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child:
                  hasData
                      ? QrImageView(
                        data: data,
                        version: QrVersions.auto,
                        size: 196,
                        gapless: false,
                        backgroundColor: Colors.white,
                        eyeStyle: const QrEyeStyle(
                          eyeShape: QrEyeShape.rounded,
                          color: Color(0xFF0B1120),
                        ),
                        dataModuleStyle: const QrDataModuleStyle(
                          dataModuleShape: QrDataModuleShape.rounded,
                          color: Color(0xFF0B1120),
                        ),
                        errorCorrectionLevel: QrErrorCorrectLevel.M,
                      )
                      : const SizedBox(
                        width: 196,
                        height: 196,
                        child: Center(child: Icon(Icons.qr_code_2_rounded)),
                      ),
            ),
          ),
          if (expiresAt != null && expiresAt!.trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'Valide jusqu au ${expiresAt!}',
              textAlign: TextAlign.center,
              style: AppTypography.caption.copyWith(
                color: MobileSurface.disabled,
              ),
            ),
          ],
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed:
                hasData
                    ? () async {
                      await Clipboard.setData(ClipboardData(text: data));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Code QR copie.')),
                        );
                      }
                    }
                    : null,
            icon: const Icon(Icons.copy_rounded),
            label: Text(copyLabel),
          ),
        ],
      ),
    );
  }
}
