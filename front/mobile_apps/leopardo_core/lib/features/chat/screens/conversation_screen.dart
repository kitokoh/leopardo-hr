import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/chat/providers/conversation_provider.dart';
import 'package:leopardo_core/models/chat_thread.dart';

/// #8754 [BOS-CHAT] — écran de conversation (chat humain, BC-13 COMMS).
///
/// ⚠️ Chat HUMAIN. Aucune instruction écrite ici ne déclenche d'action métier :
/// l'assistant IA (Léa) est un produit distinct, avec son propre point d'entrée.
///
/// États couverts : chargement, erreur, hors-ligne, vide, envoi en cours.
/// Le fil est alimenté par `ChatThreadController` (polling adaptatif avec
/// curseur `after_seq` — reprise sans perte après verrouillage du téléphone).
class ConversationScreen extends ConsumerStatefulWidget {
  const ConversationScreen({super.key, required this.threadId});

  final int threadId;

  @override
  ConsumerState<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends ConsumerState<ConversationScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  bool _sending = false;

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;

      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() => _sending = true);
    _controller.clear();

    final error = await ref
        .read(chatThreadControllerProvider(widget.threadId).notifier)
        .send(body: text);

    if (!mounted) return;

    setState(() => _sending = false);

    if (error != null) {
      // Le message n'est PAS perdu silencieusement : on le dit et on rend le
      // texte à l'utilisateur pour qu'il puisse réessayer.
      _controller.text = text;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_sendErrorLabel(error))),
      );
    }

    _scrollToBottom();
  }

  String _sendErrorLabel(String code) {
    switch (code) {
      case 'offline':
        return 'Vous êtes hors ligne : le message n’a pas été envoyé.';
      case 'rate_limited':
        return 'Trop de messages envoyés. Patientez un instant.';
      case 'not_participant':
        return 'Cette conversation n’est plus accessible.';
      case 'invalid':
        return 'Message invalide.';
      default:
        return 'Envoi impossible. Réessayez.';
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(chatThreadControllerProvider(widget.threadId));
    final controller =
        ref.read(chatThreadControllerProvider(widget.threadId).notifier);

    // Suit l'arrivée de nouveaux messages (polling, envoi, rattrapage).
    ref.listen(chatThreadControllerProvider(widget.threadId), (previous, next) {
      if (previous != null && next.messages.length > previous.messages.length) {
        _scrollToBottom();
      }
    });

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Conversation',
        subtitle: state.offline ? 'Hors ligne — reprise automatique' : 'À jour',
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          onPressed: () => Navigator.of(context).pop(),
        ),
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            icon: const Icon(Icons.refresh, color: MobileSurface.secondary),
            onPressed: () => controller.refreshNow(),
          ),
        ],
      ),
      body: Column(
        children: [
          if (state.offline)
            Container(
              width: double.infinity,
              color: AppColors.warning.withValues(alpha: 0.15),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  const Icon(Icons.cloud_off, size: 16, color: AppColors.warning),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Hors ligne. Les messages reçus entre-temps apparaîtront automatiquement.',
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.text,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          Expanded(
            child: state.loading
                ? const Center(
                    child: CircularProgressIndicator(color: AppColors.rh),
                  )
                : state.error != null && state.messages.isEmpty
                    ? _ErrorState(
                        message: state.error == 'offline'
                            ? 'Connexion indisponible.'
                            : 'Impossible de charger les messages.',
                        onRetry: controller.loadInitial,
                      )
                    : state.messages.isEmpty
                        ? ListView(
                            padding: const EdgeInsets.all(20),
                            children: const [
                              EmptyState(
                                icon: Icons.chat_bubble_outline,
                                title: 'Aucun message',
                                description:
                                    'Écrivez le premier message de cette conversation.',
                              ),
                            ],
                          )
                        : ListView.builder(
                            controller: _scrollController,
                            padding: const EdgeInsets.all(16),
                            itemCount: state.messages.length + 1,
                            itemBuilder: (context, index) {
                              if (index == 0) {
                                return state.hasMore
                                    ? Center(
                                        child: TextButton(
                                          onPressed: state.loadingOlder
                                              ? null
                                              : controller.loadOlder,
                                          child: Text(
                                            state.loadingOlder
                                                ? 'Chargement…'
                                                : 'Charger les messages précédents',
                                            style: AppTypography.caption,
                                          ),
                                        ),
                                      )
                                    : const SizedBox.shrink();
                              }

                              final message = state.messages[index - 1];

                              return _MessageBubble(
                                key: ValueKey(message.id),
                                message: message,
                              );
                            },
                          ),
          ),
          _Composer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
          ),
        ],
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({super.key, required this.message});

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: MobileSurface.cardDecoration(
          color: MobileSurface.surface,
          radius: 14,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              message.author?.name ?? 'Participant',
              style: AppTypography.caption.copyWith(
                color: MobileSurface.secondary,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              message.deleted ? 'Message supprimé' : message.body,
              style: AppTypography.bodySmall.copyWith(
                color: MobileSurface.text,
                fontStyle: message.deleted ? FontStyle.italic : null,
              ),
            ),
            if (message.hasAttachment && !message.deleted) ...[
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(
                    Icons.attach_file,
                    size: 14,
                    color: MobileSurface.secondary,
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      message.attachment!.originalName,
                      style: AppTypography.caption,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.sending,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool sending;
  final Future<void> Function() onSend;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
        decoration: BoxDecoration(
          color: MobileSurface.surface,
          border: Border(
            top: BorderSide(color: MobileSurface.border, width: 1),
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                minLines: 1,
                maxLines: 5,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => onSend(),
                decoration: InputDecoration(
                  hintText: 'Écrivez un message…',
                  filled: true,
                  fillColor: MobileSurface.background,
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(22),
                    borderSide: BorderSide.none,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: sending ? null : onSend,
              icon: sending
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        EmptyState(
          icon: Icons.error_outline,
          title: 'Erreur',
          description: message,
        ),
        const SizedBox(height: 16),
        Center(
          child: FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Réessayer'),
          ),
        ),
      ],
    );
  }
}
