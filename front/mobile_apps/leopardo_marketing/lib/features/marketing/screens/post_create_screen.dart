import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/primary_button.dart';
import '../repositories/providers.dart';

class PostCreateScreen extends ConsumerStatefulWidget {
  const PostCreateScreen({super.key});

  @override
  ConsumerState<PostCreateScreen> createState() => _PostCreateScreenState();
}

class _PostCreateScreenState extends ConsumerState<PostCreateScreen> {
  final TextEditingController _contentController = TextEditingController();
  final ImagePicker _picker = ImagePicker();
  List<XFile> _mediaFiles = [];

  bool _postLinkedIn = true;
  bool _postFacebook = false;
  bool _postX = false;
  bool _isSubmitting = false;

  DateTime? _scheduledDate;
  TimeOfDay? _scheduledTime;

  Future<void> _pickMedia() async {
    if (_mediaFiles.length >= 4) return;
    final List<XFile> images = await _picker.pickMultiImage();
    if (images.isNotEmpty) {
      setState(() {
        _mediaFiles.addAll(images);
        if (_mediaFiles.length > 4) {
          _mediaFiles = _mediaFiles.sublist(0, 4);
        }
      });
    }
  }

  Future<void> _pickDateTime() async {
    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (pickedDate != null) {
      final TimeOfDay? pickedTime = await showTimePicker(
        context: context,
        initialTime: TimeOfDay.now(),
      );
      if (pickedTime != null) {
        setState(() {
          _scheduledDate = pickedDate;
          _scheduledTime = pickedTime;
        });
      }
    }
  }

  Future<void> _submitPost() async {
    if (_contentController.text.trim().isEmpty) return;

    setState(() => _isSubmitting = true);
    try {
      final repository = ref.read(socialPostRepositoryProvider);
      List<String> platforms = [];
      if (_postLinkedIn) platforms.add('linkedin');
      if (_postFacebook) platforms.add('meta');
      if (_postX) platforms.add('twitter');

      String? scheduledAt;
      if (_scheduledDate != null && _scheduledTime != null) {
        final dt = DateTime(
          _scheduledDate!.year,
          _scheduledDate!.month,
          _scheduledDate!.day,
          _scheduledTime!.hour,
          _scheduledTime!.minute,
        );
        scheduledAt = dt.toIso8601String();
      }

      await repository.createPost({
        'content': _contentController.text.trim(),
        'platforms': platforms,
        'scheduled_at': scheduledAt,
      });

      // Refresh the calendar provider
      ref.invalidate(postsProvider);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Post créé avec succès')),
        );
        context.pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Erreur: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  void dispose() {
    _contentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MobileSurface(
      title: 'Créer un Post',
      showBackButton: true,
      onBack: () => context.pop(),
      children: [
        GlassCard(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Plateformes',
                style: AppTypography.labelMedium(context),
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _buildPlatformToggle('LinkedIn', _postLinkedIn, (val) => setState(() => _postLinkedIn = val)),
                  _buildPlatformToggle('Meta', _postFacebook, (val) => setState(() => _postFacebook = val)),
                  _buildPlatformToggle('X', _postX, (val) => setState(() => _postX = val)),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        GlassCard(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: _contentController,
                maxLines: 6,
                decoration: const InputDecoration(
                  hintText: 'Quoi de neuf ?',
                  border: InputBorder.none,
                ),
              ),
              const Divider(),
              Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.image),
                    onPressed: _pickMedia,
                    color: AppColors.primary,
                  ),
                  Text('${_mediaFiles.length}/4 Médias', style: AppTypography.labelSmall(context)),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        GlassCard(
          padding: const EdgeInsets.all(16.0),
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.schedule, color: AppColors.primary),
            title: Text(
              _scheduledDate == null
                  ? 'Publier maintenant'
                  : 'Planifié le ${_scheduledDate!.day}/${_scheduledDate!.month} à ${_scheduledTime?.format(context)}',
              style: AppTypography.bodyMedium(context),
            ),
            trailing: TextButton(
              onPressed: _pickDateTime,
              child: const Text('Modifier'),
            ),
          ),
        ),
        const SizedBox(height: 32),
        PrimaryButton(
          onPressed: _isSubmitting ? () {} : _submitPost,
          text: _isSubmitting 
              ? 'Création en cours...' 
              : (_scheduledDate == null ? 'Publier' : 'Planifier le Post'),
        ),
        const SizedBox(height: 32),
      ],
    );
  }

  Widget _buildPlatformToggle(String label, bool value, ValueChanged<bool> onChanged) {
    return FilterChip(
      label: Text(label),
      selected: value,
      onSelected: onChanged,
      selectedColor: AppColors.primary.withValues(alpha: 0.2),
      checkmarkColor: AppColors.primary,
    );
  }
}
