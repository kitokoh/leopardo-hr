import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';

/// Écran Voice IA — état « Bientôt disponible ».
///
/// La capture audio réelle n'est pas encore implémentée : l'ancien placeholder
/// envoyait un `Uint8List(0)` vide à `/ai/voice/transcribe` (#2213). Pour ne
/// jamais appeler l'API avec un payload vide, l'interaction est désactivée et
/// l'écran annonce honnêtement l'arrivée prochaine de la fonctionnalité.
class AiVoiceScreen extends StatelessWidget {
  const AiVoiceScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Voice IA',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.mic_none, size: 80, color: AppColors.ia),
              const SizedBox(height: 16),
              Text(
                'Voice IA',
                style: AppTypography.subtitle.copyWith(
                  color: AppColors.textDark,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Bientôt disponible — la transcription vocale arrive dans une prochaine version.',
                textAlign: TextAlign.center,
                style: AppTypography.bodySmall.copyWith(
                  color: AppColors.textMutedDark,
                ),
              ),
              const SizedBox(height: 32),
              // Bouton volontairement désactivé : aucun enregistrement audio
              // n'est encore branché, on n'envoie jamais de payload vide à
              // l'API (#2213).
              const Icon(
                Icons.mic_off,
                size: 32,
                color: AppColors.textMutedDark,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
