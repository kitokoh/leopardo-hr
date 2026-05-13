import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';

class AiVoiceScreen extends ConsumerStatefulWidget {
  const AiVoiceScreen({super.key});

  @override
  ConsumerState<AiVoiceScreen> createState() => _AiVoiceScreenState();
}

class _AiVoiceScreenState extends ConsumerState<AiVoiceScreen> {
  bool _recording = false;
  bool _processing = false;
  String _transcript = '';
  String _response = '';

  Future<void> _toggleRecording() async {
    if (_recording) {
      setState(() {
        _recording = false;
        _processing = true;
      });

      try {
        final repo = ref.read(aiVoiceRepositoryProvider);
        // Placeholder: in production, audio bytes would come from a recording plugin
        final audioBytes = Uint8List(0);
        final text = await repo.transcribe(audioBytes, 'recording.wav');
        setState(() => _transcript = text);

        if (text.isNotEmpty) {
          final chatRepo = ref.read(aiChatRepositoryProvider);
          final reply = await chatRepo.sendMessage(text);
          setState(() => _response = reply);
        }
      } catch (e) {
        setState(() => _response = 'Erreur : $e');
      } finally {
        if (mounted) setState(() => _processing = false);
      }
    } else {
      setState(() {
        _recording = true;
        _transcript = '';
        _response = '';
      });
    }
  }

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
              Icon(
                _recording ? Icons.mic : Icons.mic_none,
                size: 80,
                color: _recording ? AppColors.danger : AppColors.ia,
              ),
              const SizedBox(height: 16),
              Text(
                _recording
                    ? 'Ecoute en cours...'
                    : _processing
                        ? 'Traitement...'
                        : 'Appuyez pour parler',
                style: AppTypography.subtitle.copyWith(
                  color: AppColors.textDark,
                ),
              ),
              const SizedBox(height: 32),
              GestureDetector(
                onTap: _processing ? null : _toggleRecording,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: _recording ? AppColors.danger : AppColors.ia,
                    boxShadow: _recording
                        ? [
                            BoxShadow(
                              color: AppColors.danger.withValues(alpha: 0.4),
                              blurRadius: 24,
                              spreadRadius: 4,
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
                    child: _processing
                        ? const CircularProgressIndicator(color: Colors.white)
                        : Icon(
                            _recording ? Icons.stop : Icons.mic,
                            color: Colors.white,
                            size: 36,
                          ),
                  ),
                ),
              ),
              if (_transcript.isNotEmpty) ...[
                const SizedBox(height: 32),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.cardDark,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Votre question :',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textMutedDark,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _transcript,
                        style: const TextStyle(color: AppColors.textDark),
                      ),
                    ],
                  ),
                ),
              ],
              if (_response.isNotEmpty) ...[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.iaLight.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.ia.withValues(alpha: 0.3)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Reponse IA :',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.ia,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _response,
                        style: const TextStyle(color: AppColors.textDark),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
