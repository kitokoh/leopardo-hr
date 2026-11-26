import 'package:material_ui/material_ui.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_marketing/features/marketing/models/social_post.dart';

import 'social_posts_screen.dart' show socialPostRepositoryProvider;

/// Détail d'une publication sociale.
///
/// ## Pourquoi cet écran existe
///
/// `social_posts_screen.dart` poussait `/post/${post.id}` depuis la carte de
/// liste, mais **aucune route `/post/:id` n'était enregistrée** dans le
/// GoRouter de `main.dart`. C'est exactement le défaut #3293 (une cible de
/// navigation déclarée dans l'UI mais absente du routeur) : au tap, GoRouter
/// lève une `GoException` et l'écran reste figé. Le test
/// `test/navigation/marketing_router_test.dart` verrouille désormais
/// l'invariant « toute cible poussée par l'UI a une route ».
///
/// ## Ce que l'écran ne fait pas
///
/// Il ne réimplémente pas la publication : `publishPost` reste appelé par la
/// liste, qui est la source de vérité de l'action. Ici on LIT une publication
/// (`GET /social-posts/{id}`) — un écran de détail qui déclencherait des
/// effets de bord rendrait le simple fait d'ouvrir une fiche dangereux.
class SocialPostDetailScreen extends ConsumerWidget {
  const SocialPostDetailScreen({super.key, required this.postId});

  final int postId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final post = ref.watch(_postDetailProvider(postId));

    return Scaffold(
      appBar: AppBar(
        title: Text('Publication #$postId'),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
      ),
      backgroundColor: AppColors.mobileDarkBg,
      body: post.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _ErrorView(
          message: error.toString(),
          onRetry: () => ref.invalidate(_postDetailProvider(postId)),
        ),
        data: (data) => _PostDetailBody(post: data),
      ),
    );
  }
}

/// `GET /social-posts/{id}` — une publication, jamais une liste filtrée côté
/// client : la fiche doit refléter l'état serveur au moment de l'ouverture.
final _postDetailProvider = FutureProvider.family<SocialPost, int>((ref, id) {
  return ref.watch(socialPostRepositoryProvider).getPost(id);
});

class _PostDetailBody extends StatelessWidget {
  const _PostDetailBody({required this.post});

  final SocialPost post;

  Color get _statusColor => switch (post.status) {
        'published' => AppColors.success,
        'scheduled' => AppColors.warning,
        'failed' => AppColors.danger,
        _ => Colors.blueGrey,
      };

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: _statusColor.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: _statusColor, width: 0.5),
                ),
                child: Text(
                  post.status.toUpperCase(),
                  style: TextStyle(
                    color: _statusColor,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  post.targetPlatforms.isEmpty
                      ? 'Aucune plateforme'
                      : post.targetPlatforms
                          .map(
                            (p) => '${SocialPost.platformIcons[p] ?? ''} $p'
                                .trim(),
                          )
                          .join('  '),
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          const Text(
            'Contenu',
            style: TextStyle(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.07),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              post.content.isEmpty ? '(vide)' : post.content,
              style: const TextStyle(color: Colors.white, height: 1.45),
            ),
          ),
          const SizedBox(height: 20),
          _MetaRow(label: 'Créé le', value: post.createdAt),
          // Les dates suivantes ne sont affichées que si elles existent : une
          // ligne « Planifié le : — » laisserait croire à une planification
          // manquante alors qu'elle n'a simplement pas lieu d'être.
          if (post.scheduledAt != null)
            _MetaRow(label: 'Planifié le', value: post.scheduledAt!),
          if (post.publishedAt != null)
            _MetaRow(label: 'Publié le', value: post.publishedAt!),
          if (post.providerPostRef != null)
            _MetaRow(label: 'Référence', value: post.providerPostRef!),
        ],
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(color: Colors.white54, fontSize: 12),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '—' : value,
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, color: AppColors.danger, size: 40),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
