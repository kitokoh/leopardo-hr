import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class AiVoiceRepository {
  final ApiClient apiClient;

  AiVoiceRepository(this.apiClient);

  Future<String> transcribe(Uint8List audioBytes, String filename) async {
    if (audioBytes.isEmpty) {
      // Garde #2213 : ne jamais envoyer un payload audio vide à l'API —
      // le placeholder Voice IA n'a pas le droit de consommer /ai/voice/transcribe.
      throw ArgumentError.value(
        audioBytes,
        'audioBytes',
        'Un payload audio vide ne doit jamais être envoyé à /ai/voice/transcribe (#2213).',
      );
    }
    final formData = FormData.fromMap({
      'audio': MultipartFile.fromBytes(audioBytes, filename: filename),
    });
    final response = await apiClient.requestWithRetry(
      '/ai/voice/transcribe',
      // #3403 : POST non-idempotent — pas de retry automatique (doublon de
      // transcription / coût IA sur 502/timeout).
      maxRetriesOverride: 0,
      method: 'POST',
      maxRetriesOverride: 0,
      timeoutOverride: const Duration(seconds: 45),
      data: formData,
    );
    return extractDataMap(response.data)['text'] as String? ?? '';
  }

  Future<Uint8List> synthesize(String text) async {
    final response = await apiClient.requestWithRetry<List<int>>(
      '/ai/voice/synthesize',
      // #3403 : POST non-idempotent — pas de retry automatique.
      maxRetriesOverride: 0,
      method: 'POST',
      maxRetriesOverride: 0,
      data: {'text': text},
      timeoutOverride: const Duration(seconds: 45),
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(response.data ?? const <int>[]);
  }
}
