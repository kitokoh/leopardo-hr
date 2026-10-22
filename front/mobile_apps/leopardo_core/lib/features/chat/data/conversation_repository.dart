import 'dart:async';

import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/chat_thread.dart';

/// #8754 [BOS-CHAT] — accès API du chat horizontal (BC-13 COMMS).
///
/// Miroir mobile de `front/web/src/lib/chat.ts` et de
/// `front/admin-dashboard/src/lib/chat-api.js`. Aucun endpoint inventé : tout
/// passe par `/conversations/*` livré en P1→P5.
///
/// ⚠️ Aucune règle d'autorisation ici. Le serveur autorise seul et répond
/// **404** — jamais 403 — à un non-participant : un 403 confirmerait à un tiers
/// que le fil existe. Les erreurs sont donc propagées telles quelles, sans
/// interprétation locale.
class ConversationRepository {
  ConversationRepository(this.apiClient);

  final ApiClient apiClient;

  static const _readTimeout = Duration(seconds: 12);
  static const _uploadTimeout = Duration(seconds: 45);

  /// Fils visibles par l'acteur authentifié.
  Future<List<ChatThread>> getThreads({int perPage = 30}) async {
    final response = await apiClient.requestWithRetry<dynamic>(
      '/conversations',
      queryParameters: {'per_page': perPage},
      timeoutOverride: _readTimeout,
    );

    final items = extractDataList(response.data);

    return items
        .whereType<Map>()
        .map((item) => ChatThread.fromJson(item.cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// Détail d'un fil. Marque aussi le fil comme lu côté serveur.
  Future<ChatThread?> getThread(int id) async {
    final response = await apiClient.requestWithRetry<dynamic>(
      '/conversations/$id',
      timeoutOverride: _readTimeout,
    );

    final map = extractDataEnvelopeMap(response.data);

    return map.isEmpty ? null : ChatThread.fromJson(map);
  }

  /// Historique paginé.
  ///
  /// - `beforeSeq` : remonte l'historique (messages antérieurs) ;
  /// - `afterSeq` : rattrape ce qui est arrivé pendant une déconnexion.
  /// Les deux ne sont jamais combinés, comme côté serveur.
  Future<ChatMessagePage> getMessages(
    int threadId, {
    int? beforeSeq,
    int? afterSeq,
    int perPage = 30,
  }) async {
    final query = <String, dynamic>{'per_page': perPage};
    if (beforeSeq != null) query['before_seq'] = beforeSeq;
    if (afterSeq != null) query['after_seq'] = afterSeq;

    final response = await apiClient.requestWithRetry<dynamic>(
      '/conversations/$threadId/messages',
      queryParameters: query,
      timeoutOverride: _readTimeout,
    );

    final payload = response.data;
    final items = extractDataList(payload);
    final meta = payload is Map && payload['meta'] is Map
        ? (payload['meta'] as Map).cast<String, dynamic>()
        : const <String, dynamic>{};

    return ChatMessagePage(
      messages: items
          .whereType<Map>()
          .map((item) => ChatMessage.fromJson(item.cast<String, dynamic>()))
          .toList(growable: false),
      hasMore: meta['has_more'] == true,
      nextBeforeSeq: (meta['next_before_seq'] as num?)?.toInt(),
      lastSeq: (meta['last_seq'] as num?)?.toInt() ?? 0,
    );
  }

  /// Envoie un message, éventuellement avec une pièce jointe.
  ///
  /// [clientMessageId] rend l'envoi idempotent : un rejeu réseau après une
  /// coupure ne crée pas de doublon — le serveur reconnaît la clé et renvoie le
  /// message existant. C'est indispensable en mobile, où les retries sont
  /// fréquents (réveil réseau, changement d'antenne).
  Future<ChatMessage> sendMessage(
    int threadId, {
    String? body,
    String? attachmentPath,
    String? attachmentName,
    String? clientMessageId,
    int? replyToMessageId,
  }) async {
    final formData = FormData.fromMap({
      if (body != null && body.isNotEmpty) 'body': body,
      if (attachmentPath != null)
        'attachment': await MultipartFile.fromFile(
          attachmentPath,
          filename: attachmentName,
        ),
      if (clientMessageId != null) 'client_message_id': clientMessageId,
      if (replyToMessageId != null)
        'reply_to_message_id': '$replyToMessageId',
    });

    final response = await apiClient.requestWithRetry<dynamic>(
      '/conversations/$threadId/messages',
      method: 'POST',
      data: formData,
      // Pas de retry automatique sur un POST multipart : la clé d'idempotence
      // protège déjà côté serveur, et rejouer un gros upload coûte cher.
      maxRetriesOverride: 0,
      timeoutOverride: _uploadTimeout,
    );

    return ChatMessage.fromJson(extractDataMap(response.data));
  }

  Future<ChatMessage> editMessage(int threadId, int messageId, String body) async {
    final response = await apiClient.requestWithRetry<dynamic>(
      '/conversations/$threadId/messages/$messageId',
      method: 'PATCH',
      data: {'body': body},
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );

    return ChatMessage.fromJson(extractDataMap(response.data));
  }

  /// Suppression logique : la ligne subsiste côté serveur pour préserver `seq`.
  Future<void> deleteMessage(int threadId, int messageId) async {
    await apiClient.requestWithRetry<dynamic>(
      '/conversations/$threadId/messages/$messageId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
  }

  /// Télécharge une pièce jointe dans [savePath].
  ///
  /// Le fichier n'est PAS public : la route rejoue le contrôle d'appartenance
  /// au fil à chaque appel. Un 410 signifie que le message existe mais que le
  /// fichier a disparu (risque R1 : redéploiement sans disque persistant).
  Future<void> downloadAttachment(
    int threadId,
    int messageId,
    String savePath,
  ) async {
    // `downloadWithRetry` écrit directement dans le fichier : pas de binaire
    // en mémoire (crucial sur un mobile à faible RAM), retry GET idempotent,
    // et suppression du fichier partiel si le serveur a renvoyé une page
    // d'erreur au lieu du binaire.
    await apiClient.downloadWithRetry(
      '/conversations/$threadId/messages/$messageId/attachment',
      savePath,
      timeoutOverride: _uploadTimeout,
    );
  }

  /// Signale un message (`reason` ∈ `REPORT_REASONS`, côté serveur).
  Future<void> reportMessage(
    int threadId,
    int messageId, {
    required String reason,
    String? details,
  }) async {
    await apiClient.requestWithRetry<dynamic>(
      '/conversations/$threadId/reports',
      method: 'POST',
      data: {
        'message_id': messageId,
        'reason': reason,
        if (details != null && details.isNotEmpty) 'details': details,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
  }

  /// Bloque un collègue (idempotent côté serveur).
  Future<void> blockEmployee(int employeeId, {String? reason}) async {
    await apiClient.requestWithRetry<dynamic>(
      '/conversations/blocks',
      method: 'POST',
      data: {
        'employee_id': employeeId,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
  }

  /// Débloque un collègue.
  Future<void> unblockEmployee(int employeeId) async {
    await apiClient.requestWithRetry<dynamic>(
      '/conversations/blocks/$employeeId',
      method: 'DELETE',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
  }
}
