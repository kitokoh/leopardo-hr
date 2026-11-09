import 'package:flutter/material.dart';

import 'pos_ticket.dart';

/// **Widgets d'interface** partages par les deux caisses POS.
///
/// Ils portent les gestes et les etats qui doivent etre identiques dans les
/// deux apps : balayage d'une ligne, menu d'actions de ligne, apercu du ticket,
/// commandes en attente, bandeau de synchronisation. Les couleurs viennent
/// toutes du `ColorScheme` de l'app appelante — aucun litteral hex, ce qui
/// respecte le garde CI `validate-mobile-color-tokens.ps1`.

/// Cible tactile minimale (guides d'accessibilite).
const double posSharedMinTouchTarget = 48;

/// Action choisie dans le menu d'une ligne de panier.
enum PosLineAction {
  increase,
  decrease,
  setQuantity,
  remove,
  toggleFavourite,
}

/// Ligne de panier **balayable** et **appuyable longuement**.
///
/// Le balayage retire la ligne (geste naturel pour « enlever ca »), l'appui
/// long ouvre les actions. Les deux sont des raccourcis : les boutons restent
/// presents pour qui ne connait pas le geste.
class PosSwipeableLine extends StatelessWidget {
  const PosSwipeableLine({
    super.key,
    required this.child,
    required this.onRemove,
    required this.onAction,
    this.isFavourite = false,
    this.removeLabel = 'Retirer',
  });

  final Widget child;
  final VoidCallback onRemove;
  final ValueChanged<PosLineAction> onAction;
  final bool isFavourite;
  final String removeLabel;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Dismissible(
      key: ValueKey<int>(identityHashCode(child)),
      direction: DismissDirection.endToStart,
      background: Container(
        color: scheme.errorContainer,
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.delete_outline, color: scheme.onErrorContainer),
            const SizedBox(width: 8),
            Text(
              removeLabel,
              style: TextStyle(
                color: scheme.onErrorContainer,
                fontWeight: FontWeight.w700,
                fontSize: 16,
              ),
            ),
          ],
        ),
      ),
      onDismissed: (_) => onRemove(),
      child: GestureDetector(
        onLongPress: () => _showActions(context),
        child: child,
      ),
    );
  }

  Future<void> _showActions(BuildContext context) async {
    final action = await showModalBottomSheet<PosLineAction>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.add_circle_outline),
              title: const Text('Augmenter la quantite'),
              onTap: () =>
                  Navigator.of(sheetContext).pop(PosLineAction.increase),
            ),
            ListTile(
              leading: const Icon(Icons.remove_circle_outline),
              title: const Text('Diminuer la quantite'),
              onTap: () =>
                  Navigator.of(sheetContext).pop(PosLineAction.decrease),
            ),
            ListTile(
              leading: const Icon(Icons.dialpad),
              title: const Text('Saisir la quantite'),
              onTap: () =>
                  Navigator.of(sheetContext).pop(PosLineAction.setQuantity),
            ),
            ListTile(
              leading: Icon(isFavourite ? Icons.star : Icons.star_border),
              title: Text(
                  isFavourite ? 'Retirer des favoris' : 'Epingler en favori'),
              onTap: () =>
                  Navigator.of(sheetContext).pop(PosLineAction.toggleFavourite),
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline),
              title: const Text('Retirer la ligne'),
              onTap: () => Navigator.of(sheetContext).pop(PosLineAction.remove),
            ),
          ],
        ),
      ),
    );

    if (action != null) {
      onAction(action);
    }
  }
}

/// Feuille de choix du **motif d'annulation** d'une ligne.
///
/// Un motif est obligatoire : une ligne qui disparait sans trace rend la
/// cloture de caisse inexplicable. `other` exige en plus un commentaire.
class PosVoidReasonSheet extends StatefulWidget {
  const PosVoidReasonSheet({super.key, required this.itemName});

  final String itemName;

  @override
  State<PosVoidReasonSheet> createState() => _PosVoidReasonSheetState();
}

class _PosVoidReasonSheetState extends State<PosVoidReasonSheet> {
  PosVoidReason _reason = PosVoidReason.entryError;
  final _comment = TextEditingController();

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  bool get _canSubmit =>
      !_reason.requiresComment || _comment.text.trim().isNotEmpty;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Annuler la ligne',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(widget.itemName,
                style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 16),
            RadioGroup<PosVoidReason>(
              groupValue: _reason,
              onChanged: (value) => setState(() => _reason = value ?? _reason),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (final reason in PosVoidReason.values)
                    RadioListTile<PosVoidReason>(
                      value: reason,
                      title: Text(reason.label),
                      contentPadding: EdgeInsets.zero,
                    ),
                ],
              ),
            ),
            if (_reason.requiresComment) ...[
              const SizedBox(height: 8),
              TextField(
                controller: _comment,
                onChanged: (_) => setState(() {}),
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Precisez le motif',
                  hintText: 'Ex. : plat renvoye en cuisine',
                ),
              ),
            ],
            const SizedBox(height: 16),
            if (!_canSubmit)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Un motif « Autre » exige une precision.',
                  style: TextStyle(color: scheme.error, fontSize: 15),
                ),
              ),
            FilledButton.icon(
              onPressed: _canSubmit
                  ? () => Navigator.of(context).pop(
                        (reason: _reason, comment: _comment.text.trim()),
                      )
                  : null,
              icon: const Icon(Icons.block),
              label: const Text('Annuler la ligne'),
            ),
          ],
        ),
      ),
    );
  }
}

/// **Apercu du ticket** avant encaissement.
///
/// Le client voit exactement ce qu'il va payer : lignes, remise, pourboire,
/// total, table et couverts. Le total affiche est celui qui sera encaisse —
/// pas un recalcul approximatif.
class PosReceiptPreview extends StatelessWidget {
  const PosReceiptPreview({
    super.key,
    required this.receipt,
    this.currencySuffix = '',
    this.onConfirm,
    this.confirmLabel = 'Encaisser',
    this.onPrint,
  });

  final PosReceipt receipt;
  final String currencySuffix;
  final VoidCallback? onConfirm;
  final String confirmLabel;

  /// Imprime le ticket (#9264). Null = pas d'imprimante configuree, le
  /// bouton n'apparait pas plutot que d'echouer au tap.
  final VoidCallback? onPrint;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final textTheme = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.receipt_long, color: scheme.primary),
              const SizedBox(width: 10),
              Text('Ticket', style: textTheme.titleLarge),
            ],
          ),
          if (receipt.orderTypeLabel != null || receipt.tableLabel != null) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (receipt.orderTypeLabel != null) receipt.orderTypeLabel!,
                if (receipt.tableLabel != null) receipt.tableLabel!,
                if (receipt.covers != null) '${receipt.covers} couvert(s)',
              ].join(' · '),
              style: textTheme.bodyMedium,
            ),
          ],
          const Divider(height: 24),
          Flexible(
            child: SingleChildScrollView(
              child: Column(
                children: [
                  for (final line in receipt.lines)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '${line.quantity} × ${line.name}',
                                  style: const TextStyle(fontSize: 16),
                                ),
                              ),
                              Text(
                                formatPosMinor(line.totalMinor,
                                    suffix: currencySuffix),
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                          // Options du plat : le client doit retrouver sur son
                          // ticket exactement ce qu'il a commande.
                          for (final option in line.options)
                            Padding(
                              padding: const EdgeInsets.only(left: 16, top: 2),
                              child: Text(
                                '+ $option',
                                style: TextStyle(
                                  fontSize: 14,
                                  color: scheme.onSurfaceVariant,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  if (receipt.voidedLines.isNotEmpty) ...[
                    const Divider(height: 24),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'Lignes annulees',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ),
                    for (final voided in receipt.voidedLines)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 3),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${voided.quantity} × ${voided.itemName} — ${voided.reason.label}',
                                style: TextStyle(
                                  fontSize: 15,
                                  color: scheme.onSurfaceVariant,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ),
                            Text(
                              formatPosMinor(-voided.amountMinor,
                                  suffix: currencySuffix),
                              style: TextStyle(
                                fontSize: 15,
                                color: scheme.onSurfaceVariant,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ],
              ),
            ),
          ),
          const Divider(height: 24),
          _ReceiptTotalRow(
            label: 'Sous-total',
            value:
                formatPosMinor(receipt.subtotalMinor, suffix: currencySuffix),
          ),
          if (receipt.discountMinor > 0)
            _ReceiptTotalRow(
              label: 'Remise',
              value: formatPosMinor(-receipt.discountMinor,
                  suffix: currencySuffix),
            ),
          if (receipt.tipMinor > 0)
            _ReceiptTotalRow(
              label: 'Pourboire',
              value: formatPosMinor(receipt.tipMinor, suffix: currencySuffix),
            ),
          const SizedBox(height: 6),
          _ReceiptTotalRow(
            label: 'Total',
            value: formatPosMinor(receipt.totalMinor, suffix: currencySuffix),
            emphasized: true,
          ),
          if (receipt.note != null && receipt.note!.trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'Note : ${receipt.note}',
              style: textTheme.bodyMedium,
            ),
          ],
          if (onPrint != null) ...[
            const SizedBox(height: 12),
            OutlinedButton.icon(
              key: const Key('pos-receipt-print'),
              onPressed: onPrint,
              icon: const Icon(Icons.print_outlined),
              label: const Text('Imprimer le ticket'),
            ),
          ],
          if (onConfirm != null) ...[
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: onConfirm,
              icon: const Icon(Icons.check_circle_outline),
              label: Text(confirmLabel),
            ),
          ],
        ],
      ),
    );
  }
}

class _ReceiptTotalRow extends StatelessWidget {
  const _ReceiptTotalRow({
    required this.label,
    required this.value,
    this.emphasized = false,
  });

  final String label;
  final String value;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: emphasized ? 20 : 16,
              fontWeight: emphasized ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: emphasized ? 26 : 16,
              fontWeight: emphasized ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// Feuille de **mise en attente** : demande un libelle lisible.
class PosParkTicketSheet extends StatefulWidget {
  const PosParkTicketSheet({
    super.key,
    this.suggestions = const [
      'Client au comptoir',
      'Table occupee',
      'Client fidele'
    ],
  });

  final List<String> suggestions;

  @override
  State<PosParkTicketSheet> createState() => _PosParkTicketSheetState();
}

class _PosParkTicketSheetState extends State<PosParkTicketSheet> {
  final _label = TextEditingController();

  @override
  void dispose() {
    _label.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Mettre la commande en attente',
              style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          const Text(
            'La commande est mise de cote et le panier se vide. '
            'Vous pourrez la rappeler a tout moment.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _label,
            autofocus: true,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Libelle de la commande',
              hintText: 'Ex. : Table 4, M. Diallo',
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final suggestion in widget.suggestions)
                ActionChip(
                  label: Text(suggestion),
                  onPressed: () => setState(() => _label.text = suggestion),
                ),
            ],
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: _label.text.trim().isEmpty
                ? null
                : () => Navigator.of(context).pop(_label.text.trim()),
            icon: const Icon(Icons.pause_circle_outline),
            label: const Text('Mettre en attente'),
          ),
        ],
      ),
    );
  }
}

/// Feuille de **rappel** d'une commande en attente.
class PosRecallTicketSheet extends StatelessWidget {
  const PosRecallTicketSheet({
    super.key,
    required this.tickets,
    this.currencySuffix = '',
    this.now,
  });

  final PosParkedTickets tickets;
  final String currencySuffix;
  final DateTime? now;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final reference = now ?? DateTime.now();

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Commandes en attente',
              style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text('${tickets.length} commande(s) mise(s) de cote.'),
          const SizedBox(height: 12),
          if (tickets.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: Column(
                children: [
                  Icon(Icons.inbox_outlined, size: 48, color: scheme.outline),
                  const SizedBox(height: 12),
                  const Text(
                    'Aucune commande en attente.',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            )
          else
            Flexible(
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: tickets.tickets.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  final ticket = tickets.tickets[index];
                  final stale = ticket.isStaleAt(reference);

                  return ListTile(
                    leading: Icon(
                      stale
                          ? Icons.warning_amber_rounded
                          : Icons.pause_circle_outline,
                      color: stale ? scheme.error : scheme.primary,
                    ),
                    title: Text(
                      ticket.label,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    subtitle: Text(
                      '${ticket.itemCount} article(s) · '
                      '${formatPosMinor(ticket.totalMinor, suffix: currencySuffix)}'
                      '${stale ? ' · en attente depuis longtemps' : ''}',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.of(context).pop(ticket.id),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

/// Bandeau de **synchronisation** : progression, echecs, action manuelle.
///
/// Une file muette est le pire des cas : l'operateur ne sait pas si ses ventes
/// sont parties. Ce bandeau dit toujours ou l'on en est.
class PosSyncBanner extends StatelessWidget {
  const PosSyncBanner({
    super.key,
    required this.progress,
    this.onSyncNow,
    this.onDismissConflicts,
  });

  final PosSyncProgress progress;
  final VoidCallback? onSyncNow;
  final VoidCallback? onDismissConflicts;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final hasConflict = progress.hasConflicts;
    final background =
        hasConflict ? scheme.errorContainer : scheme.tertiaryContainer;
    final foreground =
        hasConflict ? scheme.onErrorContainer : scheme.onTertiaryContainer;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: background,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                hasConflict
                    ? Icons.error_outline
                    : (progress.running ? Icons.sync : Icons.cloud_off),
                color: foreground,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  progress.message,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: foreground,
                  ),
                ),
              ),
              if (onSyncNow != null && !progress.running)
                TextButton.icon(
                  onPressed: onSyncNow,
                  icon: const Icon(Icons.sync),
                  label: const Text('Synchroniser'),
                ),
              if (hasConflict && onDismissConflicts != null)
                IconButton(
                  tooltip: 'Masquer',
                  onPressed: onDismissConflicts,
                  icon: Icon(Icons.close, color: foreground),
                ),
            ],
          ),
          if (progress.total > 0) ...[
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress.percent / 100,
                minHeight: 6,
                backgroundColor: foreground.withValues(alpha: 0.2),
                color: foreground,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${progress.synced}/${progress.total} synchronisee(s)'
              '${progress.failed > 0 ? ' · ${progress.failed} en echec' : ''}',
              style: TextStyle(fontSize: 14, color: foreground),
            ),
          ],
        ],
      ),
    );
  }
}

/// Formate un montant en unites mineures (entiers) en affichage lisible.
///
/// La caisse manipule des entiers (jamais de flottant) : la conversion en
/// affichage se fait ici, une seule fois, et gere le signe (une remise
/// s'affiche `-1,50`).
String formatPosMinor(int minor, {String suffix = ''}) {
  final negative = minor < 0;
  final absolute = minor.abs();
  final units = absolute ~/ 100;
  final cents = (absolute % 100).toString().padLeft(2, '0');

  return '${negative ? '-' : ''}$units,$cents$suffix';
}
