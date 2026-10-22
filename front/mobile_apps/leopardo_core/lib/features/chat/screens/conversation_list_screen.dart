import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/chat/providers/conversation_provider.dart';
import 'package:leopardo_core/models/chat_thread.dart';

/// #8754 [BOS-CHAT] — liste des conversations (chat humain, BC-13 COMMS).
///
/// ⚠️ Chat HUMAIN. L'assistant IA (Léa) est un autre produit : aucun
/// historique partagé.
///
/// Réutilise le design system mobile existant (`MobileSurface`, `MobileTopBar`,
/// `MobileIconBubble`, `EmptyState`, `AppTypography`) — aucun nouveau thème,
/// aucun frontend séparé.
class ConversationListScreen extends ConsumerWidget {
  const ConversationListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final threadsAsync = ref.watch(chatThreadsProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Messagerie',
        subtitle: 'Conversations et groupes de travail',
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            icon: const Icon(Icons.refresh, color: MobileSurface.secondary),
            onPressed: () => ref.invalidate(chatThreadsProvider),
          ),
        ],
        leading: IconButton(
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: threadsAsync.when(
        // --- État chargement ---
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.rh),
        ),

        // --- État erreur ---
        error: (error, _) => ListView(
          padding: const EdgeInsets.all(20),
          children: [
            EmptyState(
              icon: Icons.cloud_off,
              title: 'Messagerie indisponible',
              description:
                  'Vérifiez votre connexion, puis réessayez. Vos conversations restent enregistrées côté serveur.',
            ),
            const SizedBox(height: 16),
            Center(
              child: FilledButton.tonal(
                onPressed: () => ref.invalidate(chatThreadsProvider),
                child: const Text('Réessayer'),
              ),
            ),
          ],
        ),

        // --- Données ---
        data: (threads) => RefreshIndicator(
          onRefresh: () async => ref.refresh(chatThreadsProvider.future),
          color: AppColors.rh,
          backgroundColor: MobileSurface.background,
          child: threads.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(20),
                  children: const [
                    EmptyState(
                      icon: Icons.forum_outlined,
                      title: 'Aucune conversation',
                      description:
                          'Les échanges avec votre manager ou vos groupes apparaîtront ici.',
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(20),
                  itemCount: threads.length,
                  itemBuilder: (context, index) {
                    final thread = threads[index];

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _ThreadTile(
                        key: ValueKey(thread.id),
                        thread: thread,
                        onTap: () => context.push('/chat/${thread.id}'),
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

class _ThreadTile extends StatelessWidget {
  const _ThreadTile({super.key, required this.thread, required this.onTap});

  final ChatThread thread;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = thread.isGroup ? AppColors.success : AppColors.rh;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Ink(
        padding: const EdgeInsets.all(14),
        decoration: MobileSurface.cardDecoration(
          color: thread.unread
              ? AppColors.info.withValues(alpha: 0.08)
              : MobileSurface.surface,
          radius: 14,
        ),
        child: Row(
          children: [
            MobileIconBubble(
              icon: thread.isGroup ? Icons.groups : Icons.person,
              color: accent,
              size: 38,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          thread.title.trim().isEmpty
                              ? (thread.isGroup ? 'Groupe' : 'Conversation')
                              : thread.title,
                          style: AppTypography.bodySmall.copyWith(
                            color: MobileSurface.text,
                            fontWeight:
                                thread.unread ? FontWeight.w700 : FontWeight.w500,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (thread.unread)
                        Container(
                          width: 7,
                          height: 7,
                          decoration: const BoxDecoration(
                            color: AppColors.info,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    thread.subjectType ?? 'Discussion',
                    style: AppTypography.caption.copyWith(
                      color: MobileSurface.secondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(Icons.chevron_right, color: MobileSurface.secondary),
          ],
        ),
      ),
    );
  }
}
