/// #8754 [BOS-CHAT] — modèles du chat horizontal côté Flutter.
///
/// Miroirs stricts des ressources servies par `ConversationController`
/// (`api/app/Modules/Notification`) : aucun contrat inventé, aucun champ
/// renommé. Le pendant JS est `front/web/src/lib/chat.ts`, le pendant Vue
/// `front/admin-dashboard/src/lib/chat-api.js`.
///
/// ⚠️ Chat HUMAIN. L'assistant IA (Léa) est un autre produit : aucun
/// historique partagé, et aucun message humain ne déclenche d'action métier.
library;

/// Une pièce jointe telle que le serveur la décrit dans un message.
///
/// Le nom du disque n'est volontairement PAS exposé au client : la résolution
/// se fait côté serveur, qui rejoue le contrôle d'accès à chaque
/// téléchargement.
class ChatAttachment {
  const ChatAttachment({
    required this.originalName,
    required this.mimeType,
    required this.size,
  });

  final String originalName;
  final String mimeType;
  final int size;

  static ChatAttachment? fromJson(dynamic json) {
    if (json is! Map) return null;

    final map = json.cast<String, dynamic>();

    return ChatAttachment(
      originalName: (map['original_name'] ?? '') as String,
      mimeType: (map['mime_type'] ?? '') as String,
      size: (map['size'] as num?)?.toInt() ?? 0,
    );
  }
}

/// Référence courte vers le message auquel celui-ci répond.
class ChatReplyTo {
  const ChatReplyTo({required this.id, required this.body, required this.deleted});

  final int id;
  final String body;
  final bool deleted;

  static ChatReplyTo? fromJson(dynamic json) {
    if (json is! Map) return null;

    final map = json.cast<String, dynamic>();

    return ChatReplyTo(
      id: (map['id'] as num?)?.toInt() ?? 0,
      body: (map['body'] ?? '') as String,
      deleted: map['deleted'] == true,
    );
  }
}

/// Auteur d'un message, tel quel dans la ressource.
class ChatAuthor {
  const ChatAuthor({required this.id, required this.name});

  final int id;
  final String name;

  static ChatAuthor? fromJson(dynamic json) {
    if (json is! Map) return null;

    final map = json.cast<String, dynamic>();

    return ChatAuthor(
      id: (map['id'] as num?)?.toInt() ?? 0,
      name: (map['name'] ?? '') as String,
    );
  }
}

/// Un message d'une conversation.
///
/// `seq` porte l'ordre TOTAL du fil : il est attribué par le serveur et ne
/// change jamais. C'est lui — et jamais `createdAt` — qui doit trier l'affichage
/// et servir de curseur de pagination (deux messages d'une même seconde ont le
/// même `createdAt`).
class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.threadId,
    required this.seq,
    required this.body,
    required this.authorId,
    required this.author,
    required this.deleted,
    this.attachment,
    this.replyTo,
    this.editedAt,
    this.createdAt,
  });

  final int id;
  final int threadId;
  final int seq;
  final String body;
  final int authorId;
  final ChatAuthor? author;
  final bool deleted;
  final ChatAttachment? attachment;
  final ChatReplyTo? replyTo;
  final DateTime? editedAt;
  final DateTime? createdAt;

  bool get hasAttachment => attachment != null;

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      threadId:
          (json['conversation_thread_id'] as num?)?.toInt() ??
          (json['thread_id'] as num?)?.toInt() ??
          0,
      seq: (json['seq'] as num?)?.toInt() ?? 0,
      body: (json['body'] ?? '') as String,
      authorId: (json['author_id'] as num?)?.toInt() ?? 0,
      author: ChatAuthor.fromJson(json['author']),
      deleted: json['deleted'] == true || json['deleted_at'] != null,
      attachment: ChatAttachment.fromJson(json['attachment']),
      replyTo: ChatReplyTo.fromJson(json['reply_to']),
      editedAt: DateTime.tryParse(json['edited_at']?.toString() ?? ''),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }
}

/// Métadonnées de pagination de l'historique.
class ChatMessagePage {
  const ChatMessagePage({
    required this.messages,
    required this.hasMore,
    required this.nextBeforeSeq,
    required this.lastSeq,
  });

  final List<ChatMessage> messages;
  final bool hasMore;
  final int? nextBeforeSeq;
  final int lastSeq;

  static const empty = ChatMessagePage(
    messages: <ChatMessage>[],
    hasMore: false,
    nextBeforeSeq: null,
    lastSeq: 0,
  );
}

/// Un participant d'un fil de groupe, avec son rôle.
class ChatParticipant {
  const ChatParticipant({
    required this.employeeId,
    required this.role,
    this.name,
  });

  final int employeeId;
  final String role;
  final String? name;

  bool get canModerate => role == 'creator' || role == 'admin';

  static ChatParticipant? fromJson(dynamic json) {
    if (json is! Map) return null;

    final map = json.cast<String, dynamic>();

    return ChatParticipant(
      employeeId: (map['employee_id'] as num?)?.toInt() ?? 0,
      role: (map['role'] ?? 'member') as String,
      name: map['name'] as String?,
    );
  }
}

/// Une conversation : fil direct (employé ↔ manager) ou groupe.
class ChatThread {
  const ChatThread({
    required this.id,
    required this.kind,
    required this.title,
    required this.unread,
    this.subjectType,
    this.subjectId,
    this.lastMessageAt,
    this.participants = const <ChatParticipant>[],
  });

  final int id;
  final String kind;
  final String title;
  final bool unread;
  final String? subjectType;
  final int? subjectId;
  final DateTime? lastMessageAt;
  final List<ChatParticipant> participants;

  bool get isGroup => kind == 'group';

  /// Titre affichable : le titre libre s'il existe, sinon un libellé générique.
  String displayTitle(String Function(String key, String fallback) tr) {
    if (title.trim().isNotEmpty) return title;

    return isGroup ? tr('chatHub.list.kindGroup', 'Groupe') : tr('chatHub.list.conversation', 'Conversation');
  }

  factory ChatThread.fromJson(Map<String, dynamic> json) {
    final rawParticipants = json['participants'];

    return ChatThread(
      id: (json['id'] as num?)?.toInt() ?? 0,
      kind: (json['kind'] ?? 'direct') as String,
      title: (json['title'] ?? '') as String,
      unread: json['unread'] == true,
      subjectType: json['subject_type'] as String?,
      subjectId: (json['subject_id'] as num?)?.toInt(),
      lastMessageAt: DateTime.tryParse(json['last_message_at']?.toString() ?? ''),
      participants: rawParticipants is List
          ? rawParticipants
                .map(ChatParticipant.fromJson)
                .whereType<ChatParticipant>()
                .toList(growable: false)
          : const <ChatParticipant>[],
    );
  }
}
