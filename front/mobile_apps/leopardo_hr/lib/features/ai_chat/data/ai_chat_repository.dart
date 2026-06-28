import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class AiChatRepository {
  final ApiClient apiClient;

  AiChatRepository(this.apiClient);

  Future<String> sendMessage(String message, {int? conversationId}) async {
    final response = await apiClient.requestWithRetry(
      '/ai/chat',
      method: 'POST',
      timeoutOverride: const Duration(seconds: 30),
      data: {
        'message': message,
        if (conversationId != null) 'conversation_id': conversationId,
      },
    );
    final data = extractDataMap(response.data);
    return data['response'] as String? ??
        data['message'] as String? ??
        data['content'] as String? ??
        '';
  }
}
