import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/base_providers.dart';
import 'social_post_repository.dart';

final socialPostRepositoryProvider = Provider<SocialPostRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return SocialPostRepository(apiClient);
});

final postsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final repository = ref.watch(socialPostRepositoryProvider);
  return repository.getPosts();
});
