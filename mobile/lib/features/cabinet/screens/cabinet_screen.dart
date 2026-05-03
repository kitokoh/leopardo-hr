import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/core/widgets/shimmer_loading.dart';
import 'package:leopardo_rh/features/cabinet/providers/cabinet_provider.dart';
import 'package:leopardo_rh/models/cabinet_document.dart';
import 'package:leopardo_rh/models/cabinet_folder.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';

class CabinetScreen extends ConsumerStatefulWidget {
  final int? folderId;
  final String? folderName;

  const CabinetScreen({super.key, this.folderId, this.folderName});

  @override
  ConsumerState<CabinetScreen> createState() => _CabinetScreenState();
}

class _CabinetScreenState extends ConsumerState<CabinetScreen> {
  final _picker = ImagePicker();

  @override
  Widget build(BuildContext context) {
    final foldersAsync = ref.watch(cabinetFoldersProvider(widget.folderId));
    final documentsAsync = ref.watch(cabinetDocumentsProvider(widget.folderId));
    final background = AppColors.backgroundFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    final isRoot = widget.folderId == null;
    final title = widget.folderName ?? 'Mon Placard';

    return Scaffold(
      backgroundColor: background,
      appBar: AppBar(
        backgroundColor: background,
        elevation: 0,
        title: Row(
          children: [
            Icon(
              isRoot ? Icons.door_sliding_outlined : Icons.folder_open,
              color: const Color(0xFF8B6914),
              size: 24,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: AppTypography.subtitle.copyWith(color: text),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: text),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
      ),
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.tint(
                context,
                const Color(0xFF8B6914),
                lightAlpha: 0.06,
              ),
              background,
            ],
          ),
        ),
        child: RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(cabinetFoldersProvider(widget.folderId));
            ref.invalidate(cabinetDocumentsProvider(widget.folderId));
          },
          child: _buildBody(foldersAsync, documentsAsync, text, muted),
        ),
      ),
      floatingActionButton: _buildFab(text),
    );
  }

  Widget _buildBody(
    AsyncValue<List<CabinetFolder>> foldersAsync,
    AsyncValue<List<CabinetDocument>> documentsAsync,
    Color text,
    Color muted,
  ) {
    final isLoading = foldersAsync.isLoading || documentsAsync.isLoading;
    final hasError = foldersAsync.hasError || documentsAsync.hasError;

    if (isLoading) {
      return ListView.builder(
        padding: const EdgeInsets.all(20),
        itemCount: 6,
        itemBuilder:
            (_, __) => const Padding(
              padding: EdgeInsets.only(bottom: 12),
              child: ShimmerLoading(
                width: double.infinity,
                height: 64,
                borderRadius: 16,
              ),
            ),
      );
    }

    if (hasError) {
      final error =
          foldersAsync.error?.toString() ?? documentsAsync.error?.toString();
      return Center(
        child: Text(
          error ?? 'Erreur de chargement',
          style: AppTypography.bodySmall.copyWith(color: muted),
        ),
      );
    }

    final folders = foldersAsync.value ?? [];
    final documents = documentsAsync.value ?? [];

    if (folders.isEmpty && documents.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 80),
          EmptyState(
            icon: Icons.door_sliding_outlined,
            title: 'Placard vide',
            description:
                'Ajoutez des dossiers et documents pour organiser votre espace.',
          ),
        ],
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 100),
      children: [
        if (folders.isNotEmpty) ...[
          Text(
            'Dossiers',
            style: AppTypography.caption.copyWith(
              color: muted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          ...folders.map((f) => _FolderTile(folder: f)),
          const SizedBox(height: 20),
        ],
        if (documents.isNotEmpty) ...[
          Text(
            'Documents',
            style: AppTypography.caption.copyWith(
              color: muted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          ...documents.map(
            (d) => _DocumentTile(
              document: d,
              onShare: () => _showShareSheet(d),
              onDelete: () => _confirmDeleteDocument(d),
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildFab(Color text) {
    return FloatingActionButton(
      backgroundColor: const Color(0xFF8B6914),
      foregroundColor: Colors.white,
      onPressed: () => _showAddMenu(),
      child: const Icon(Icons.add),
    );
  }

  void _showAddMenu() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (ctx) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const SizedBox(height: 8),
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 16),
                ListTile(
                  leading: const Icon(
                    Icons.create_new_folder_outlined,
                    color: Color(0xFF8B6914),
                  ),
                  title: const Text('Nouveau dossier'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _showCreateFolderDialog();
                  },
                ),
                ListTile(
                  leading: const Icon(
                    Icons.upload_file_outlined,
                    color: Color(0xFF8B6914),
                  ),
                  title: const Text('Ajouter un document'),
                  subtitle: const Text('Depuis vos fichiers ou la camera'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _pickAndUploadDocument();
                  },
                ),
                const SizedBox(height: 8),
              ],
            ),
          ),
    );
  }

  void _showCreateFolderDialog() {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder:
          (ctx) => AlertDialog(
            title: const Text('Nouveau dossier'),
            content: TextField(
              controller: controller,
              autofocus: true,
              decoration: const InputDecoration(
                hintText: 'Nom du dossier',
                prefixIcon: Icon(Icons.folder_outlined),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Annuler'),
              ),
              FilledButton(
                onPressed: () async {
                  final name = controller.text.trim();
                  if (name.isEmpty) return;
                  Navigator.pop(ctx);
                  final repo = ref.read(cabinetRepositoryProvider);
                  await repo.createFolder(
                    name: name,
                    parentId: widget.folderId,
                  );
                  ref.invalidate(cabinetFoldersProvider(widget.folderId));
                },
                child: const Text('Creer'),
              ),
            ],
          ),
    );
  }

  Future<void> _pickAndUploadDocument() async {
    final picked = await _picker.pickImage(source: ImageSource.gallery);
    if (picked == null) return;

    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('Envoi en cours...')));

    final repo = ref.read(cabinetRepositoryProvider);
    await repo.uploadDocument(
      filePath: picked.path,
      fileName: picked.name,
      folderId: widget.folderId,
    );

    ref.invalidate(cabinetDocumentsProvider(widget.folderId));

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Document ajoute avec succes')),
    );
  }

  void _showShareSheet(CabinetDocument doc) {
    final emailController = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder:
          (ctx) => Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              20 + MediaQuery.of(ctx).viewInsets.bottom,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Partager "${doc.name}"', style: AppTypography.subtitle),
                const SizedBox(height: 16),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.link, color: Color(0xFF8B6914)),
                  title: const Text('Creer un lien de partage'),
                  onTap: () async {
                    Navigator.pop(ctx);
                    final repo = ref.read(cabinetRepositoryProvider);
                    final result = await repo.shareViaLink(
                      shareableType: 'document',
                      shareableId: doc.id,
                    );
                    if (!mounted) return;
                    final url = result['share_url'] ?? '';
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('Lien copie : $url')),
                    );
                  },
                ),
                const Divider(),
                const Text('Partager par email'),
                const SizedBox(height: 8),
                TextField(
                  controller: emailController,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    hintText: 'Email du destinataire',
                    prefixIcon: Icon(Icons.email_outlined),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    icon: const Icon(Icons.send),
                    label: const Text('Envoyer'),
                    onPressed: () async {
                      final email = emailController.text.trim();
                      if (email.isEmpty) return;
                      Navigator.pop(ctx);
                      final repo = ref.read(cabinetRepositoryProvider);
                      await repo.shareViaEmail(
                        shareableType: 'document',
                        shareableId: doc.id,
                        email: email,
                      );
                      if (!mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Partage envoye a $email')),
                      );
                    },
                  ),
                ),
                const SizedBox(height: 8),
              ],
            ),
          ),
    );
  }

  void _confirmDeleteDocument(CabinetDocument doc) {
    showDialog(
      context: context,
      builder:
          (ctx) => AlertDialog(
            title: const Text('Supprimer le document ?'),
            content: Text(
              'Le document "${doc.name}" sera supprime definitivement.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Annuler'),
              ),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: Colors.red),
                onPressed: () async {
                  Navigator.pop(ctx);
                  final repo = ref.read(cabinetRepositoryProvider);
                  await repo.deleteDocument(doc.id);
                  ref.invalidate(cabinetDocumentsProvider(widget.folderId));
                },
                child: const Text('Supprimer'),
              ),
            ],
          ),
    );
  }
}

// ── Folder tile ───────────────────────────────────────────────────────────────

class _FolderTile extends StatelessWidget {
  final CabinetFolder folder;

  const _FolderTile({required this.folder});

  @override
  Widget build(BuildContext context) {
    final surface = AppColors.surfaceFor(context);
    final border = AppColors.borderFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap:
              () => context.push(
                '/cabinet/folder/${folder.id}',
                extra: folder.name,
              ),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: border),
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFF8B6914).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.folder,
                    color: Color(0xFF8B6914),
                    size: 24,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        folder.name,
                        style: AppTypography.body.copyWith(
                          color: text,
                          fontWeight: FontWeight.w600,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${folder.documentsCount} doc${folder.documentsCount != 1 ? 's' : ''}',
                        style: AppTypography.caption.copyWith(color: muted),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: muted, size: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ── Document tile ─────────────────────────────────────────────────────────────

class _DocumentTile extends StatelessWidget {
  final CabinetDocument document;
  final VoidCallback onShare;
  final VoidCallback onDelete;

  const _DocumentTile({
    required this.document,
    required this.onShare,
    required this.onDelete,
  });

  IconData get _icon {
    if (document.isPdf) return Icons.picture_as_pdf;
    if (document.isImage) return Icons.image_outlined;
    if (document.mimeType.contains('word')) return Icons.description_outlined;
    return Icons.insert_drive_file_outlined;
  }

  Color get _iconColor {
    if (document.isPdf) return Colors.red.shade700;
    if (document.isImage) return Colors.blue.shade700;
    return Colors.grey.shade700;
  }

  @override
  Widget build(BuildContext context) {
    final surface = AppColors.surfaceFor(context);
    final border = AppColors.borderFor(context);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: border),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: _iconColor.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(_icon, color: _iconColor, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    document.name,
                    style: AppTypography.body.copyWith(
                      color: text,
                      fontWeight: FontWeight.w500,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    document.sizeFormatted,
                    style: AppTypography.caption.copyWith(color: muted),
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.share_outlined, size: 20),
              color: const Color(0xFF8B6914),
              tooltip: 'Partager',
              onPressed: onShare,
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, size: 20),
              color: Colors.red.shade400,
              tooltip: 'Supprimer',
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}
