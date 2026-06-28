import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class AiVoiceRepository {
  final ApiClient apiClient;

  AiVoiceRepository(this.apiClient);

  Future<String> transcribe(Uint8List audioBytes, String filename) async {
    final formData = FormData.fromMap({
      'audio': MultipartFile.fromBytes(audioBytes, filename: filename),
    });
    final response = await apiClient.requestWithRetry(
      '/ai/voice/transcribe',
      method: 'POST',
      timeoutOverride: const Duration(seconds: 45),
      data: formData,
    );
    return extractDataMap(response.data)['text'] as String? ?? '';
  }

  Future<Uint8List> synthesize(String text) async {
    final response = await apiClient.requestWithRetry<List<int>>(
      '/ai/voice/synthesize',
      method: 'POST',
      data: {'text': text},
      timeoutOverride: const Duration(seconds: 45),
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(response.data ?? const <int>[]);
  }
}
