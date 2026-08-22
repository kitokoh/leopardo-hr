import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod/legacy.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_marketing/core/providers/core_providers.dart';
import 'package:leopardo_marketing/features/marketing/models/social_post.dart';

import '../data/social_post_repository.dart';

final _statusFilterProvider = StateProvider<String?>((ref) => null);

final socialPostRepositoryProvider = Provider<SocialPostRepository>((ref) {
  return SocialPostRepository(ref.watch(apiClientProvider));
});

final socialPostsProvider =
    FutureProvider.family<List<SocialPost>, String?>((ref, status) {
  return ref.watch(socialPostRepositoryProvider).listPosts(status: status);
});

class SocialPostsScreen extends ConsumerWidget {
  const SocialPostsScreen({super.key});

  static const _statuses = [null, 'draft', 'scheduled', 'published', 'failed'];
  static const _labels = {
    null: 'Tous',
    'draft': 'Brouillons',
    'scheduled': 'Planifiés',
    'published': 'Publiés',
    'failed': 'Échoués',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(_statusFilterProvider);
    final posts = ref.watch(socialPostsProvider(status));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Publications'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(socialPostsProvider(status)),
            tooltip: 'Rafraîchir',
          ),
        ],
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
      ),
      backgroundColor: AppColors.mobileDarkBg,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/create-post'),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Nouveau post'),
        backgroundColor: AppColors.rh,
      ),
      body: Column(
        children: [
          // Filter chips
          SizedBox(
            height: 50,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              children: _statuses.map((s) {
                final selected = status == s;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(_labels[s] ?? s ?? 'Tous'),
                    selected: selected,
                    selectedColor: AppColors.rh.withValues(alpha: 0.3),
                    labelStyle: TextStyle(
                      color: selected ? AppColors.rh : Colors.white70,
                      fontSize: 12,
                      fontWeight:
                          selected ? FontWeight.bold : FontWeight.normal,
                    ),
                    backgroundColor: Colors.white10,
                    onSelected: (_) =>
                        ref.read(_statusFilterProvider.notifier).state = s,
                  ),
                );
              }).toList(),
            ),
          ),
          // List
          Expanded(
            child: posts.when(
              data: (items) {
                if (items.isEmpty) {
                  return Center(
                    child: EmptyState(
                      icon: Icons.campaign_outlined,
                      title: 'Aucun post',
                      description: status == null
                          ? 'Créez votre premier post en tapant sur le bouton +'
                          : 'Aucun post avec ce statut.',
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () async =>
                      ref.invalidate(socialPostsProvider(status)),
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 80),
                    itemCount: items.length,
                    itemBuilder: (context, index) =>
                        _PostCard(post: items[index]),
                  ),
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.error_outline,
                          color: Colors.red, size: 48),
                      const SizedBox(height: 12),
                      Text(
                        e.toString(),
                        style: const TextStyle(color: Colors.white70),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: () =>
                            ref.invalidate(socialPostsProvider(status)),
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PostCard extends ConsumerWidget {
  const _PostCard({required this.post});

  final SocialPost post;

  Color get _statusColor => switch (post.status) {
        'published' => AppColors.success,
        'scheduled' => AppColors.warning,
        'failed' => AppColors.danger,
        _ => Colors.blueGrey,
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      color: Colors.white.withValues(alpha: 0.07),
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: () => context.push('/post/${post.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: _statusColor.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: _statusColor, width: 0.5),
                    ),
                    child: Text(
                      post.status.toUpperCase(),
                      style: TextStyle(
                        color: _statusColor,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  // Platforms
                  Expanded(
                    child: Text(
                      post.targetPlatforms.map((p) {
                        return SocialPost.platformIcons[p] ?? p;
                      }).join(' '),
                      style: const TextStyle(fontSize: 14),
                    ),
                  ),
                  // Publish now if draft
                  if (post.isDraft)
                    TextButton.icon(
                      icon: const Icon(Icons.send_rounded, size: 14),
                      label: const Text('Publier', style: TextStyle(fontSize: 12)),
                      onPressed: () => _publishNow(context, ref),
                      style: TextButton.styleFrom(
                        foregroundColor: AppColors.rh,
                        padding: EdgeInsets.zero,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              // Content preview
              Text(
                post.content,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style:
                    const TextStyle(color: Colors.white, fontSize: 13, height: 1.4),
              ),
              const SizedBox(height: 8),
              // Footer
              Row(
                children: [
                  const Icon(Icons.schedule_rounded,
                      size: 12, color: Colors.white38),
                  const SizedBox(width: 4),
                  Text(
                    _formatDate(post.scheduledAt ?? post.createdAt),
                    style:
                        const TextStyle(color: Colors.white38, fontSize: 11),
                  ),
                  if (post.providerPostRef != null) ...[
                    const Spacer(),
                    const Icon(Icons.check_circle_outline_rounded,
                        size: 12, color: Colors.green),
                    const SizedBox(width: 4),
                    Text(
                      post.providerPostRef!,
                      style: const TextStyle(
                          color: Colors.white38, fontSize: 10),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _publishNow(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(socialPostRepositoryProvider).publishPost(post.id);
      ref.invalidate(socialPostsProvider(null));
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Post publié !')),
      );
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  String _formatDate(String? date) {
    if (date == null || date.isEmpty) return '-';
    try {
      final dt = DateTime.parse(date).toLocal();
      return '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return date.length > 10 ? date.substring(0, 10) : date;
    }
  }
}
