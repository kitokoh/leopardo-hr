import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_marketing/features/marketing/models/social_post.dart';
import 'package:leopardo_marketing/features/marketing/screens/social_posts_screen.dart';

/// Screen for creating a new social post.
///
/// Supports text content, multi-platform selection, optional scheduling,
/// and immediate publish or save as draft.
class CreatePostScreen extends ConsumerStatefulWidget {
  const CreatePostScreen({super.key});

  @override
  ConsumerState<CreatePostScreen> createState() => _CreatePostScreenState();
}

class _CreatePostScreenState extends ConsumerState<CreatePostScreen> {
  final _contentCtrl = TextEditingController();
  final Set<String> _selectedPlatforms = {'linkedin'};
  bool _scheduleEnabled = false;
  DateTime _scheduledAt = DateTime.now().add(const Duration(hours: 1));
  bool _submitting = false;

  @override
  void dispose() {
    _contentCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit({required bool publishNow}) async {
    final content = _contentCtrl.text.trim();
    if (content.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Le contenu ne peut pas être vide.')),
      );
      return;
    }
    if (_selectedPlatforms.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sélectionnez au moins un réseau.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final repo = ref.read(socialPostRepositoryProvider);

      // Create the post (as draft or scheduled)
      final post = await repo.createPost(
        content: content,
        targetPlatforms: _selectedPlatforms.toList(),
        scheduledAt: !publishNow && _scheduleEnabled ? _scheduledAt : null,
      );

      // Optionally publish immediately
      if (publishNow) {
        await repo.publishPost(post.id);
      }

      ref.invalidate(socialPostsProvider(null));

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            publishNow ? 'Post publié !' : 'Post enregistré.',
          ),
        ),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _scheduledAt,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_scheduledAt),
    );
    if (time == null) return;
    setState(() {
      _scheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nouveau post'),
        backgroundColor: AppColors.mobileDarkBg,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      backgroundColor: AppColors.mobileDarkBg,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Content
              const Text(
                'Contenu du post',
                style: TextStyle(
                    color: Colors.white70,
                    fontWeight: FontWeight.bold,
                    fontSize: 13),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: TextField(
                  controller: _contentCtrl,
                  maxLines: 8,
                  maxLength: 2200,
                  style: const TextStyle(color: Colors.white),
                  decoration: const InputDecoration(
                    hintText: 'Rédigez votre message…',
                    hintStyle: TextStyle(color: Colors.white38),
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.all(14),
                    counterStyle: TextStyle(color: Colors.white38),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              // Platforms
              const Text(
                'Réseaux cibles',
                style: TextStyle(
                    color: Colors.white70,
                    fontWeight: FontWeight.bold,
                    fontSize: 13),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: SocialPost.availablePlatforms.map((platform) {
                  final selected = _selectedPlatforms.contains(platform);
                  return FilterChip(
                    label: Text(
                      '${SocialPost.platformIcons[platform] ?? ''} $platform',
                    ),
                    selected: selected,
                    selectedColor: AppColors.rh.withValues(alpha: 0.3),
                    backgroundColor: Colors.white10,
                    labelStyle: TextStyle(
                      color: selected ? AppColors.rh : Colors.white70,
                    ),
                    onSelected: (value) {
                      setState(() {
                        if (value) {
                          _selectedPlatforms.add(platform);
                        } else {
                          _selectedPlatforms.remove(platform);
                        }
                      });
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 20),
              // Scheduling
              Row(
                children: [
                  const Text(
                    'Planifier',
                    style: TextStyle(
                        color: Colors.white70,
                        fontWeight: FontWeight.bold,
                        fontSize: 13),
                  ),
                  const Spacer(),
                  Switch.adaptive(
                    value: _scheduleEnabled,
                    activeThumbColor: AppColors.rh,
                    onChanged: (value) =>
                        setState(() => _scheduleEnabled = value),
                  ),
                ],
              ),
              if (_scheduleEnabled) ...[
                const SizedBox(height: 8),
                InkWell(
                  onTap: _pickDateTime,
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.07),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.rh, width: 0.5),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.schedule_rounded,
                            color: AppColors.rh, size: 18),
                        const SizedBox(width: 8),
                        Text(
                          _formatDateTime(_scheduledAt),
                          style: const TextStyle(
                              color: Colors.white, fontSize: 14),
                        ),
                        const Spacer(),
                        const Icon(Icons.edit_rounded,
                            color: Colors.white38, size: 16),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 28),
              // Actions
              if (_submitting)
                const Center(child: CircularProgressIndicator())
              else
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ElevatedButton.icon(
                      icon: const Icon(Icons.send_rounded),
                      label: const Text('Publier maintenant'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.rh,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      onPressed: () => _submit(publishNow: true),
                    ),
                    const SizedBox(height: 10),
                    OutlinedButton.icon(
                      icon: Icon(
                        _scheduleEnabled
                            ? Icons.event_rounded
                            : Icons.drafts_rounded,
                      ),
                      label: Text(
                        _scheduleEnabled
                            ? 'Planifier pour ${_formatDateTime(_scheduledAt)}'
                            : 'Enregistrer en brouillon',
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.white70,
                        side: const BorderSide(color: Colors.white24),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      onPressed: () => _submit(publishNow: false),
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDateTime(DateTime dt) {
    return '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
  }
}
