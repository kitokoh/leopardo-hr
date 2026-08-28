import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/crm/data/crm_repository.dart';
import 'package:leopardo_manager/features/crm/providers/crm_providers.dart';

/// Détail d'un compte CRM : contacts, activités (timeline), tâches —
/// issue #5730. Même API/Policies que le web.
class CrmAccountDetailScreen extends ConsumerWidget {
  const CrmAccountDetailScreen({super.key, required this.accountId});

  final int accountId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final detail = ref.watch(crmAccountDetailProvider(accountId));

    return MobilePage(
      title: l10n.crmAccountDetail,
      showBackButton: true,
      children: [
        detail.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (error, _) => MobilePanel(
            child: Text(
              l10n.crmLoadError,
              style: const TextStyle(color: MobileSurface.muted),
            ),
          ),
          data: (data) => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _SectionTitle(l10n.crmContacts),
              if (data.contacts.isEmpty)
                _MutedLine(l10n.crmEmptyContacts)
              else
                for (final contact in data.contacts)
                  MobilePanel(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        contact.isPrimary
                            ? Icons.star_rounded
                            : Icons.person_outline,
                        color: contact.isPrimary
                            ? Colors.amber
                            : MobileSurface.secondary,
                      ),
                      title: Text(
                        contact.fullName,
                        style: const TextStyle(color: MobileSurface.text),
                      ),
                      subtitle: contact.email != null
                          ? Text(
                              contact.email!,
                              style: const TextStyle(
                                color: MobileSurface.muted,
                                fontSize: 12,
                              ),
                            )
                          : null,
                    ),
                  ),
              const SizedBox(height: 12),
              _SectionTitle(l10n.crmTasks),
              if (data.tasks.isEmpty)
                _MutedLine(l10n.crmEmptyTasks)
              else
                for (final task in data.tasks)
                  MobilePanel(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        task.status == 'done'
                            ? Icons.check_circle
                            : Icons.radio_button_unchecked,
                        color: task.status == 'done'
                            ? Colors.greenAccent
                            : MobileSurface.secondary,
                      ),
                      title: Text(
                        task.subject,
                        style: const TextStyle(color: MobileSurface.text),
                      ),
                      subtitle: Text(
                        '${l10n.crmStatus}: ${task.status}',
                        style: const TextStyle(
                          color: MobileSurface.muted,
                          fontSize: 12,
                        ),
                      ),
                      onTap: task.status == 'done'
                          ? null
                          : () => _completeTask(context, ref, task.id),
                    ),
                  ),
              const SizedBox(height: 12),
              _SectionTitle(l10n.crmActivities),
              if (data.activities.isEmpty)
                _MutedLine(l10n.crmEmptyActivities)
              else
                for (final activity in data.activities)
                  MobilePanel(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(
                        Icons.history,
                        color: MobileSurface.secondary,
                      ),
                      title: Text(
                        activity.subject,
                        style: const TextStyle(color: MobileSurface.text),
                      ),
                      subtitle: Text(
                        activity.happenedAt,
                        style: const TextStyle(
                          color: MobileSurface.muted,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _completeTask(
    BuildContext context,
    WidgetRef ref,
    int taskId,
  ) async {
    final messenger = ScaffoldMessenger.of(context);
    final l10n = context.l10n;

    try {
      await ref.read(crmRepositoryProvider).completeTask(taskId);
      ref.invalidate(crmAccountDetailProvider(accountId));
      messenger.showSnackBar(
        SnackBar(content: Text(l10n.crmTaskCompleted)),
      );
    } catch (_) {
      messenger.showSnackBar(
        SnackBar(content: Text(l10n.crmLoadError)),
      );
    }
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8, top: 4),
      child: Text(
        title,
        style: const TextStyle(
          color: MobileSurface.text,
          fontWeight: FontWeight.w700,
          fontSize: 15,
        ),
      ),
    );
  }
}

class _MutedLine extends StatelessWidget {
  const _MutedLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        text,
        style: const TextStyle(color: MobileSurface.muted, fontSize: 13),
      ),
    );
  }
}
