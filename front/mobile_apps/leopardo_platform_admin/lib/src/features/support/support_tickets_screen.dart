import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/leopardo_badge.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

import '../../core/platform_providers.dart';
import '../platform/platform_models.dart';

class _StatusFilter extends Notifier<String?> {
  @override
  String? build() => null;

  void set(String? value) => state = value;
}

final _statusFilterProvider = NotifierProvider<_StatusFilter, String?>(_StatusFilter.new);

final platformSupportTicketsProvider =
    FutureProvider.family<List<PlatformSupportTicket>, String?>(
        (ref, status) async {
  return ref
      .watch(platformRepositoryProvider)
      .supportTickets(status: status);
});

class SupportTicketsScreen extends ConsumerWidget {
  const SupportTicketsScreen({super.key});

  static const _statuses = [null, 'open', 'in_progress', 'resolved', 'closed'];
  static const _statusLabels = {
    null: 'Tous',
    'open': 'Ouverts',
    'in_progress': 'En cours',
    'resolved': 'Résolus',
    'closed': 'Fermés',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(_statusFilterProvider);
    final tickets = ref.watch(platformSupportTicketsProvider(status));

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Support client',
        subtitle: 'Tickets tenant',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        // Filter chips
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: _statuses.map((s) {
              final selected = status == s;
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: FilterChip(
                  label: Text(_statusLabels[s] ?? s ?? 'Tous'),
                  selected: selected,
                  selectedColor: AppColors.rh.withValues(alpha: 0.25),
                  labelStyle: TextStyle(
                    color: selected ? AppColors.rh : MobileSurface.secondary,
                    fontWeight:
                        selected ? FontWeight.w700 : FontWeight.normal,
                  ),
                  onSelected: (_) =>
                      ref.read(_statusFilterProvider.notifier).set(s),
                ),
              );
            }).toList(),
          ),
        ),
        const SizedBox(height: 12),
        tickets.when(
          data: (items) {
            if (items.isEmpty) {
              return const MobilePanel(
                child: Text(
                  'Aucun ticket pour ce filtre.',
                  style: TextStyle(color: MobileSurface.secondary),
                ),
              );
            }
            return Column(
              children: items
                  .map((t) => _TicketCard(ticket: t))
                  .toList(),
            );
          },
          loading: () => const MobileEmptyLoading(label: 'Chargement tickets'),
          error: (e, _) => MobileErrorPanel(
            message: e.toString(),
            onRetry: () => ref.invalidate(platformSupportTicketsProvider(status)),
          ),
        ),
      ],
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket});

  final PlatformSupportTicket ticket;

  Color get _priorityColor => switch (ticket.priority) {
        'urgent' => AppColors.danger,
        'high' => AppColors.warning,
        _ => AppColors.info,
      };

  Color get _statusColor => switch (ticket.status) {
        'open' => AppColors.danger,
        'in_progress' => AppColors.warning,
        'resolved' => AppColors.success,
        _ => MobileSurface.disabled,
      };

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: () => context.push('/platform/support-tickets/${ticket.id}'),
        child: MobilePanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      ticket.subject,
                      style: const TextStyle(
                        color: MobileSurface.text,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  LeopardoBadge(
                    label: ticket.priority,
                    color: _priorityColor,
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  const Icon(
                    Icons.business_rounded,
                    size: 13,
                    color: MobileSurface.secondary,
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      ticket.companyName,
                      style: const TextStyle(
                        color: MobileSurface.secondary,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  LeopardoBadge(label: ticket.status, color: _statusColor),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(
                    Icons.chat_bubble_outline_rounded,
                    size: 13,
                    color: MobileSurface.secondary,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    '${ticket.messagesCount} message(s)',
                    style: const TextStyle(
                      color: MobileSurface.secondary,
                      fontSize: 12,
                    ),
                  ),
                  if (ticket.assignedTo != null) ...[
                    const SizedBox(width: 10),
                    const Icon(
                      Icons.person_rounded,
                      size: 13,
                      color: MobileSurface.secondary,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      ticket.assignedTo!,
                      style: const TextStyle(
                        color: MobileSurface.secondary,
                        fontSize: 12,
                      ),
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
}

// ---------------------------------------------------------------------------
// Ticket Detail Screen
// ---------------------------------------------------------------------------

final platformTicketDetailProvider =
    FutureProvider.family<PlatformSupportTicketDetail, int>(
        (ref, ticketId) async {
  return ref.watch(platformRepositoryProvider).supportTicketDetail(ticketId);
});

class SupportTicketDetailScreen extends ConsumerStatefulWidget {
  const SupportTicketDetailScreen({super.key, required this.ticketId});

  final int ticketId;

  @override
  ConsumerState<SupportTicketDetailScreen> createState() =>
      _SupportTicketDetailScreenState();
}

class _SupportTicketDetailScreenState
    extends ConsumerState<SupportTicketDetailScreen> {
  final _replyController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _replyController.dispose();
    super.dispose();
  }

  Future<void> _sendReply() async {
    final message = _replyController.text.trim();
    if (message.isEmpty) return;

    setState(() => _submitting = true);
    try {
      await ref.read(platformRepositoryProvider).replySupportTicket(
            ticketId: widget.ticketId,
            message: message,
          );
      _replyController.clear();
      ref.invalidate(platformTicketDetailProvider(widget.ticketId));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Réponse envoyée.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _triage(String status) async {
    try {
      await ref.read(platformRepositoryProvider).triageSupportTicket(
            ticketId: widget.ticketId,
            status: status,
          );
      ref.invalidate(platformTicketDetailProvider(widget.ticketId));
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('Ticket → $status')));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(platformTicketDetailProvider(widget.ticketId));

    return MobilePage(
      appBar: MobileTopBar(
        title: 'Ticket #${widget.ticketId}',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      children: [
        detail.when(
          data: (data) => _TicketDetailContent(
            detail: data,
            replyController: _replyController,
            submitting: _submitting,
            onReply: _sendReply,
            onTriage: _triage,
          ),
          loading: () => const MobileEmptyLoading(label: 'Chargement ticket'),
          error: (e, _) => MobileErrorPanel(
            message: e.toString(),
            onRetry: () =>
                ref.invalidate(platformTicketDetailProvider(widget.ticketId)),
          ),
        ),
      ],
    );
  }
}

class _TicketDetailContent extends StatelessWidget {
  const _TicketDetailContent({
    required this.detail,
    required this.replyController,
    required this.submitting,
    required this.onReply,
    required this.onTriage,
  });

  final PlatformSupportTicketDetail detail;
  final TextEditingController replyController;
  final bool submitting;
  final VoidCallback onReply;
  final void Function(String status) onTriage;

  @override
  Widget build(BuildContext context) {
    final ticket = detail.ticket;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Header
        MobilePanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                ticket.subject,
                style: const TextStyle(
                  color: MobileSurface.text,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  LeopardoBadge(label: ticket.status, color: AppColors.info),
                  const SizedBox(width: 8),
                  LeopardoBadge(
                      label: ticket.priority, color: AppColors.warning),
                  const Spacer(),
                  Text(
                    ticket.companyName,
                    style: const TextStyle(
                      color: MobileSurface.secondary,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        // Quick triage actions
        if (ticket.status == 'open' || ticket.status == 'in_progress') ...[
          Row(
            children: [
              if (ticket.status == 'open')
                Expanded(
                  child: MobilePrimaryAction(
                    icon: Icons.play_arrow_rounded,
                    label: 'En cours',
                    onPressed: () => onTriage('in_progress'),
                  ),
                ),
              if (ticket.status == 'open') const SizedBox(width: 10),
              Expanded(
                child: MobilePrimaryAction(
                  icon: Icons.check_circle_rounded,
                  label: 'Résoudre',
                  onPressed: () => onTriage('resolved'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
        ],
        // Messages
        const MobileSectionLabel('Conversation'),
        ...detail.messages.map((msg) => _MessageBubble(message: msg)),
        const SizedBox(height: 12),
        // Reply input
        if (ticket.status != 'closed') ...[
          const MobileSectionLabel('Répondre'),
          MobilePanel(
            child: Column(
              children: [
                TextField(
                  controller: replyController,
                  maxLines: 4,
                  style: const TextStyle(color: MobileSurface.text),
                  decoration: const InputDecoration(
                    hintText: 'Votre réponse...',
                    hintStyle: TextStyle(color: MobileSurface.secondary),
                    border: InputBorder.none,
                  ),
                ),
                const SizedBox(height: 8),
                MobilePrimaryAction(
                  icon: Icons.send_rounded,
                  label: submitting ? 'Envoi...' : 'Envoyer',
                  onPressed: submitting ? null : onReply,
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message});

  final PlatformTicketMessage message;

  @override
  Widget build(BuildContext context) {
    final isSuperAdmin = message.isSuperAdmin;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment:
            isSuperAdmin ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: [
          if (!isSuperAdmin) ...[
            CircleAvatar(
              radius: 16,
              backgroundColor: AppColors.rh.withValues(alpha: 0.2),
              child: const Icon(
                Icons.person_rounded,
                size: 16,
                color: AppColors.rh,
              ),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: isSuperAdmin
                    ? AppColors.rh.withValues(alpha: 0.15)
                    : MobileSurface.card,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    message.authorName,
                    style: TextStyle(
                      color: isSuperAdmin ? AppColors.rh : MobileSurface.secondary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    message.body,
                    style: const TextStyle(
                      color: MobileSurface.text,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isSuperAdmin) ...[
            const SizedBox(width: 8),
            CircleAvatar(
              radius: 16,
              backgroundColor: AppColors.rh.withValues(alpha: 0.2),
              child: const Icon(
                Icons.shield_rounded,
                size: 16,
                color: AppColors.rh,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
