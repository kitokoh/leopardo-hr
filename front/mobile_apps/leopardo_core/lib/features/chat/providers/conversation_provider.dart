import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
// Riverpod 3 a sorti `StateNotifier`/`StateNotifierProvider` du point d'entrée
// principal : ils vivent désormais dans `legacy.dart`. Sans cet import, le
// build échoue sur « Type 'StateNotifier' not found » (#8754 — le smoke build
// mobile était rouge sur toutes les PR, y compris docs-only).
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/chat/data/conversation_repository.dart';
import 'package:leopardo_core/models/chat_thread.dart';

/// #8754 [BOS-CHAT] — état du chat horizontal (BC-13 COMMS).
///
/// Le polling adaptatif est ici l'équivalent mobile de
/// `useChatPolling.js` (Vue) et de `use-conversation-polling.ts` (Next) :
/// **pas** de WebSocket. Décision de l'audit §6 : l'hébergement ne tient pas de
/// connexion longue durée, et le SSE a été retiré volontairement (#7481).
///
/// Différence mobile importante : quand l'app passe en arrière-plan, le
/// système suspend les timers. Au retour, `refreshNow()` demande
/// `after_seq=<dernier seq connu>` — le serveur ayant persisté les messages,
/// rien n'est perdu. C'est exactement le scénario « téléphone verrouillé »
/// couvert par le test de reconnexion.

/// Provider du repository : défini dans `core_providers.dart` pour être
/// partagé par toutes les apps (employé et manager), comme
/// `notificationRepositoryProvider`. Rien n'est dupliqué ici.

/// Cadences, alignées sur le client web.
const _activeInterval = Duration(seconds: 6);
const _idleInterval = Duration(seconds: 24);
const _idleAfterTicks = 3;

/// Liste des fils, rafraîchie périodiquement.
final chatThreadsProvider = FutureProvider<List<ChatThread>>((ref) async {
  final repository = ref.watch(conversationRepositoryProvider);

  var idleTicks = 0;
  Timer? timer;

  void schedule() {
    timer?.cancel();
    timer = Timer(
      idleTicks >= _idleAfterTicks ? _idleInterval : _activeInterval,
      () async {
        // `invalidateSelf` relance le provider ET recrée ce timer : sans
        // `onDispose`, chaque tick empilerait un timer supplémentaire et le
        // polling s'emballerait après quelques minutes.
        ref.invalidateSelf();
      },
    );
  }

  ref.onDispose(() => timer?.cancel());

  final threads = await repository.getThreads();
  idleTicks = threads.isEmpty ? idleTicks + 1 : 0;
  schedule();

  return threads;
});

/// État d'un fil ouvert : messages, curseur, pagination, chargement.
class ChatThreadState {
  const ChatThreadState({
    this.messages = const <ChatMessage>[],
    this.loading = true,
    this.loadingOlder = false,
    this.hasMore = false,
    this.nextBeforeSeq,
    this.lastSeq = 0,
    this.error,
    this.offline = false,
  });

  final List<ChatMessage> messages;
  final bool loading;
  final bool loadingOlder;
  final bool hasMore;
  final int? nextBeforeSeq;
  final int lastSeq;

  /// `null` = pas d'erreur. Une erreur 404 signifie « fil inexistant OU
  /// non-participant » : le serveur ne distingue pas, volontairement.
  final String? error;
  final bool offline;

  ChatThreadState copyWith({
    List<ChatMessage>? messages,
    bool? loading,
    bool? loadingOlder,
    bool? hasMore,
    int? nextBeforeSeq,
    int? lastSeq,
    String? error,
    bool? offline,
    bool clearError = false,
  }) {
    return ChatThreadState(
      messages: messages ?? this.messages,
      loading: loading ?? this.loading,
      loadingOlder: loadingOlder ?? this.loadingOlder,
      hasMore: hasMore ?? this.hasMore,
      nextBeforeSeq: nextBeforeSeq ?? this.nextBeforeSeq,
      lastSeq: lastSeq ?? this.lastSeq,
      error: clearError ? null : (error ?? this.error),
      offline: offline ?? this.offline,
    );
  }
}

/// Contrôleur d'un fil : chargement, pagination, envoi, polling adaptatif.
class ChatThreadController extends StateNotifier<ChatThreadState> {
  ChatThreadController(this._repository, this.threadId)
      : super(const ChatThreadState()) {
    _initialize();
  }

  final ConversationRepository _repository;
  final int threadId;

  Timer? _timer;
  int _idleTicks = 0;
  int _failures = 0;
  bool _inFlight = false;

  Future<void> _initialize() async {
    await loadInitial();
    _schedule();
  }

  /// Chargement initial : la page la plus récente, puis `seq` comme curseur.
  Future<void> loadInitial() async {
    state = state.copyWith(loading: true, clearError: true);

    try {
      final page = await _repository.getMessages(threadId);

      state = ChatThreadState(
        messages: page.messages,
        loading: false,
        hasMore: page.hasMore,
        nextBeforeSeq: page.nextBeforeSeq,
        lastSeq: page.lastSeq,
        offline: false,
      );

      // Marque le fil comme lu (le détail déclenche `markReadFor()` côté
      // serveur). Un échec ici n'empêche pas de lire les messages.
      unawaited(_repository.getThread(threadId).catchError((_) => null));
    } catch (error) {
      state = state.copyWith(
        loading: false,
        error: _describe(error),
        offline: _isNetwork(error),
      );
    }
  }

  /// Remonte l'historique (curseur `before_seq`).
  Future<void> loadOlder() async {
    if (state.loadingOlder || state.nextBeforeSeq == null) return;

    state = state.copyWith(loadingOlder: true);

    try {
      final page = await _repository.getMessages(
        threadId,
        beforeSeq: state.nextBeforeSeq,
      );

      state = state.copyWith(
        messages: _merge(state.messages, page.messages),
        loadingOlder: false,
        hasMore: page.hasMore,
        nextBeforeSeq: page.nextBeforeSeq,
      );
    } catch (error) {
      state = state.copyWith(loadingOlder: false, error: _describe(error));
    }
  }

  /// Envoie un message. [clientMessageId] garantit l'absence de doublon en cas
  /// de rejeu réseau (voir `ConversationRepository.sendMessage`).
  Future<String?> send({
    String? body,
    String? attachmentPath,
    String? attachmentName,
    int? replyToMessageId,
  }) async {
    try {
      final message = await _repository.sendMessage(
        threadId,
        body: body,
        attachmentPath: attachmentPath,
        attachmentName: attachmentName,
        clientMessageId: _newClientMessageId(),
        replyToMessageId: replyToMessageId,
      );

      state = state.copyWith(
        messages: _merge(state.messages, [message]),
        lastSeq: message.seq > state.lastSeq ? message.seq : state.lastSeq,
        clearError: true,
      );

      // On rattrape immédiatement : si un autre participant a écrit entre
      // deux ticks, il apparaît sans attendre le cycle suivant.
      unawaited(refreshNow());

      return null;
    } catch (error) {
      return _describeSendError(error);
    }
  }

  Future<void> edit(int messageId, String body) async {
    try {
      final updated = await _repository.editMessage(threadId, messageId, body);

      state = state.copyWith(
        messages: state.messages
            .map((m) => m.id == messageId ? updated : m)
            .toList(growable: false),
      );
    } catch (error) {
      state = state.copyWith(error: _describe(error));
    }
  }

  Future<void> delete(int messageId) async {
    try {
      await _repository.deleteMessage(threadId, messageId);

      // Suppression LOGIQUE : on ne retire pas la ligne, on la marque, sinon
      // `seq` percerait l'ordre et les réponses pointeraient dans le vide.
      state = state.copyWith(
        messages: state.messages
            .map(
              (m) => m.id == messageId
                  ? ChatMessage(
                      id: m.id,
                      threadId: m.threadId,
                      seq: m.seq,
                      body: '',
                      authorId: m.authorId,
                      author: m.author,
                      deleted: true,
                      createdAt: m.createdAt,
                    )
                  : m,
            )
            .toList(growable: false),
      );
    } catch (error) {
      state = state.copyWith(error: _describe(error));
    }
  }

  Future<String?> report(int messageId, String reason) async {
    try {
      await _repository.reportMessage(threadId, messageId, reason: reason);

      return null;
    } catch (error) {
      return _describeSendError(error);
    }
  }

  /// Tick immédiat : après un envoi, au retour au premier plan, au retour
  /// du réseau. Reprise par `after_seq` — aucun message perdu.
  Future<void> refreshNow() async {
    if (_inFlight) return;
    _inFlight = true;

    try {
      final page = await _repository.getMessages(
        threadId,
        afterSeq: state.lastSeq,
      );

      _failures = 0;

      if (page.messages.isNotEmpty) {
        _idleTicks = 0;
        state = state.copyWith(
          messages: _merge(state.messages, page.messages),
          lastSeq: page.lastSeq > state.lastSeq ? page.lastSeq : state.lastSeq,
          offline: false,
          clearError: true,
        );
      } else {
        _idleTicks += 1;
        state = state.copyWith(offline: false);
      }
    } catch (error) {
      _failures += 1;
      state = state.copyWith(offline: _isNetwork(error));
    } finally {
      _inFlight = false;
    }
  }

  void _schedule() {
    _timer?.cancel();

    var delay = _activeInterval;
    if (_failures > 0) {
      // Backoff exponentiel plafonné : un serveur en difficulté ne doit pas
      // recevoir une requête par client toutes les six secondes.
      final backoff = _activeInterval.inSeconds * (1 << _failures.clamp(0, 4));
      delay = Duration(seconds: backoff.clamp(0, 60));
    } else if (_idleTicks >= _idleAfterTicks) {
      delay = _idleInterval;
    }

    _timer = Timer(delay, () async {
      await refreshNow();
      if (mounted) _schedule();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  /// Fusion par `id` + tri sur `seq` : les messages manqués s'insèrent au bon
  /// endroit, sans doublon, quel que soit l'ordre d'arrivée.
  List<ChatMessage> _merge(List<ChatMessage> existing, List<ChatMessage> incoming) {
    final byId = <int, ChatMessage>{
      for (final message in existing) message.id: message,
      for (final message in incoming) message.id: message,
    };

    final merged = byId.values.toList()
      ..sort((a, b) => a.seq == b.seq ? a.id.compareTo(b.id) : a.seq.compareTo(b.seq));

    return merged;
  }

  String _newClientMessageId() {
    // `DateTime` en microsecondes + un suffixe aléatoire : unique en pratique,
    // et surtout STABLE pendant l'envoi — c'est tout ce que le serveur exige.
    final micros = DateTime.now().microsecondsSinceEpoch;
    final suffix = (micros % 100000).toRadixString(36);

    return 'm_${micros.toRadixString(36)}_$suffix';
  }

  bool _isNetwork(Object error) {
    final text = error.toString().toLowerCase();

    return text.contains('timeout') ||
        text.contains('socket') ||
        text.contains('network') ||
        text.contains('connexion');
  }

  String _describe(Object error) {
    return _isNetwork(error) ? 'offline' : 'server';
  }

  /// Messages d'erreur d'envoi, alignés sur les statuts du serveur.
  String _describeSendError(Object error) {
    final text = error.toString();

    if (text.contains('404')) return 'not_participant';
    if (text.contains('429')) return 'rate_limited';
    if (text.contains('422')) return 'invalid';
    if (_isNetwork(error)) return 'offline';

    return 'failed';
  }
}

final chatThreadControllerProvider =
    StateNotifierProvider.family<ChatThreadController, ChatThreadState, int>(
  (ref, threadId) {
    final repository = ref.watch(conversationRepositoryProvider);

    return ChatThreadController(repository, threadId);
  },
);
