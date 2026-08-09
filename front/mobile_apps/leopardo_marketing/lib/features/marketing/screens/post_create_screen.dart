import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/core/widgets/glass_card.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/primary_button.dart';

class PostCreateScreen extends StatefulWidget {
  const PostCreateScreen({super.key});

  @override
  State<PostCreateScreen> createState() => _PostCreateScreenState();
}

class _PostCreateScreenState extends State<PostCreateScreen> {
  final TextEditingController _contentController = TextEditingController();
  final ImagePicker _picker = ImagePicker();
  List<XFile> _mediaFiles = [];

  bool _postLinkedIn = true;
  bool _postFacebook = false;
  bool _postX = false;

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
              Text('Plateformes', style: AppTypography.labelMedium(context)),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _buildPlatformToggle(
                    'LinkedIn',
                    _postLinkedIn,
                    (val) => setState(() => _postLinkedIn = val),
                  ),
                  _buildPlatformToggle(
                    'Meta',
                    _postFacebook,
                    (val) => setState(() => _postFacebook = val),
                  ),
                  _buildPlatformToggle(
                    'X',
                    _postX,
                    (val) => setState(() => _postX = val),
                  ),
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
                  Text(
                    '${_mediaFiles.length}/4 Médias',
                    style: AppTypography.labelSmall(context),
                  ),
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
            leading: Icon(Icons.schedule, color: AppColors.primary),
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
          onPressed: () {
            // Action to create post
            context.pop();
          },
          text: _scheduledDate == null ? 'Publier' : 'Planifier le Post',
        ),
        const SizedBox(height: 32),
      ],
    );
  }

  Widget _buildPlatformToggle(
    String label,
    bool value,
    ValueChanged<bool> onChanged,
  ) {
    return FilterChip(
      label: Text(label),
      selected: value,
      onSelected: onChanged,
      selectedColor: AppColors.primary.withValues(alpha: 0.2),
      checkmarkColor: AppColors.primary,
    );
  }
}
