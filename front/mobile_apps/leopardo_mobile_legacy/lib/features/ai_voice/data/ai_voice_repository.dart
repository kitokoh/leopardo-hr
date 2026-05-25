import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/api/api_client.dart';

class AiVoiceRepository {
  final ApiClient apiClient;

  AiVoiceRepository(this.apiClient);

  Future<String> transcribe(Uint8List audioBytes, String filename) async {
    final formData = FormData.fromMap({
      'audio': MultipartFile.fromBytes(audioBytes, filename: filename),
    });
    final response = await apiClient.dio.post(
      '/ai/voice/transcribe',
      data: formData,
    );
    return response.data['text'] as String? ?? '';
  }

  Future<Uint8List> synthesize(String text) async {
    final response = await apiClient.dio.post(
      '/ai/voice/synthesize',
      data: {'text': text},
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(response.data);
  }
}
