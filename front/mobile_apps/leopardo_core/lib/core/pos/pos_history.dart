/// **Historique des ventes** et **reimpression** de ticket, partages.
///
/// Deux besoins concrets de caisse que les deux apps avaient en commun et
/// qu'aucune n'avait :
///
///  - le client revient cinq minutes plus tard : « le ticket est illisible,
///    vous pouvez le refaire ? » — sans historique, le vendeur n'a aucun
///    moyen de retrouver la vente ;
///  - le gerant veut verifier ce qui est passe dans la journee sans ouvrir le
///    back-office.
///
/// L'historique est **local** (Hive, cote app) et volontairement borne : il
/// ne remplace pas le back-office, il couvre la journee de service. Il est
/// ecrit au moment de l'encaissement, y compris quand la vente part en file
/// hors ligne — c'est justement le cas ou l'on a le plus besoin de retrouver
/// la trace.
library;

import 'package:flutter/material.dart';

import 'pos_esc_pos.dart';
import 'pos_printer.dart';
import 'pos_states.dart';
import 'pos_ticket.dart';
import 'pos_widgets.dart';

/// Une vente passee, telle qu'on la retrouve dans l'historique.
class PosSaleRecord {
  const PosSaleRecord({
    required this.id,
    required this.receipt,
    required this.paidAt,
    this.orderId,
    this.paymentLabel,
    this.queued = false,
  });

  /// Identifiant local, unique sur la tablette.
  final String id;

  /// Le ticket exact tel qu'il a ete remis au client.
  final PosReceipt receipt;

  /// Date et heure de l'encaissement.
  final DateTime paidAt;

  /// Identifiant de la commande cote serveur (null si la vente est encore en
  /// file hors ligne : elle n'a pas encore de numero).
  final int? orderId;

  /// Moyen de paiement lisible (« Especes », « Carte », « Mixte »).
  final String? paymentLabel;

  /// Vrai si la vente a ete encaissee hors ligne et attend le reseau.
  final bool queued;

  int get totalMinor => receipt.totalMinor;

  int get itemCount => receipt.itemCount;

  /// Libelle court pour la liste : « 3 articles — 45,00 ».
  String get summary => '$itemCount article${itemCount > 1 ? 's' : ''}';

  Map<String, dynamic> toJson() => {
        'id': id,
        'receipt': receipt.toJson(),
        'paidAt': paidAt.toIso8601String(),
        'orderId': orderId,
        'paymentLabel': paymentLabel,
        'queued': queued,
      };

  factory PosSaleRecord.fromJson(Map<String, dynamic> json) => PosSaleRecord(
        id: json['id'] as String? ?? '',
        receipt: PosReceipt.fromJson(
          (json['receipt'] as Map<String, dynamic>?) ?? const {},
        ),
        paidAt: DateTime.tryParse(json['paidAt'] as String? ?? '') ??
            DateTime.fromMillisecondsSinceEpoch(0),
        orderId: (json['orderId'] as num?)?.toInt(),
        paymentLabel: json['paymentLabel'] as String?,
        queued: json['queued'] as bool? ?? false,
      );
}

/// Historique borne des ventes de la tablette.
///
/// Le plafond n'est pas une limite technique mais une decision produit : au
/// dela de [maxRecords] ventes, la tablette n'est plus l'outil pour chercher
/// (c'est le back-office), et garder tout ferait grossir la base locale sans
/// fin sur un appareil a 32 Go.
class PosSalesHistory {
  const PosSalesHistory({this.records = const []});

  /// Nombre de ventes conservees localement.
  static const int maxRecords = 200;

  /// Les ventes, **de la plus recente a la plus ancienne**.
  final List<PosSaleRecord> records;

  bool get isEmpty => records.isEmpty;

  int get length => records.length;

  /// Ajoute une vente en tete et tronque au plafond.
  PosSalesHistory add(PosSaleRecord record) {
    final updated = [record, ...records.where((existing) => existing.id != record.id)];

    return PosSalesHistory(
      records: updated.length > maxRecords
          ? updated.sublist(0, maxRecords)
          : updated,
    );
  }

  /// Retrouve une vente par son identifiant local.
  PosSaleRecord? byId(String id) {
    for (final record in records) {
      if (record.id == id) {
        return record;
      }
    }

    return null;
  }

  /// Ventes dont le total correspond a un montant cherche.
  ///
  /// C'est la recherche la plus utile au comptoir : le client se souvient du
  /// montant (« c'etait 45 euros »), rarement de l'heure exacte.
  List<PosSaleRecord> byTotal(int totalMinor) =>
      [for (final record in records) if (record.totalMinor == totalMinor) record];

  /// Ventes d'une journee donnee (comparaison sur la date locale).
  List<PosSaleRecord> onDay(DateTime day) => [
        for (final record in records)
          if (record.paidAt.year == day.year &&
              record.paidAt.month == day.month &&
              record.paidAt.day == day.day)
            record,
      ];

  /// Total encaisse sur une journee.
  int totalOnDay(DateTime day) =>
      onDay(day).fold(0, (sum, record) => sum + record.totalMinor);

  Map<String, dynamic> toJson() => {
        'records': [for (final record in records) record.toJson()],
      };

  factory PosSalesHistory.fromJson(Map<String, dynamic> json) {
    final raw = (json['records'] as List<dynamic>?) ?? const [];
    final parsed = <PosSaleRecord>[];

    for (final entry in raw) {
      try {
        parsed.add(PosSaleRecord.fromJson(entry as Map<String, dynamic>));
      } on Object {
        // Une entree corrompue ne doit pas emporter tout l'historique : on
        // saute la ligne et on garde le reste.
        continue;
      }
    }

    return PosSalesHistory(records: parsed);
  }
}

/// Ecran **historique des ventes**, avec reimpression d'un ticket.
class PosSalesHistoryScreen extends StatefulWidget {
  const PosSalesHistoryScreen({
    super.key,
    required this.history,
    this.printer,
    this.shopName = 'Leopardo',
    this.onReprint,
  });

  /// Ventes a afficher.
  final PosSalesHistory history;

  /// Service d'impression (null = reimpression indisponible, le bouton est
  /// desactive avec une explication plutot que de disparaitre).
  final PosPrinterService? printer;

  /// Nom imprime en en-tete du ticket.
  final String shopName;

  /// Appele apres une reimpression reussie (comptage, journal...).
  final void Function(PosSaleRecord record)? onReprint;

  @override
  State<PosSalesHistoryScreen> createState() => _PosSalesHistoryScreenState();
}

class _PosSalesHistoryScreenState extends State<PosSalesHistoryScreen> {
  String _query = '';
  String? _reprintingId;

  /// Filtre les ventes : montant exact, ou libelle de moyen de paiement.
  List<PosSaleRecord> get _filtered {
    final needle = _query.trim().toLowerCase();

    if (needle.isEmpty) {
      return widget.history.records;
    }

    final asMinor = int.tryParse(needle.replaceAll(RegExp(r'[^0-9]'), ''));

    return [
      for (final record in widget.history.records)
        if ((asMinor != null && record.totalMinor == asMinor) ||
            (record.paymentLabel ?? '').toLowerCase().contains(needle) ||
            (record.orderId?.toString() ?? '').contains(needle))
          record,
    ];
  }

  Future<void> _reprint(PosSaleRecord record) async {
    final printer = widget.printer;

    if (printer == null) {
      return;
    }

    setState(() => _reprintingId = record.id);

    var outcome = await printer.print(
      const PosEscPosGenerator().render(
        receipt: record.receipt,
        shopName: widget.shopName,
        reprint: true,
      ),
    );

    // Meme reprise automatique que le bouton d'impression : une imprimante en
    // veille est le cas le plus frequent, et renvoyer le vendeur aux reglages
    // pour ca serait absurde.
    if (!outcome.success && printer.selectedPrinter != null) {
      final reconnect = await printer.connect();

      if (reconnect.success) {
        outcome = await printer.print(
          const PosEscPosGenerator().render(
            receipt: record.receipt,
            shopName: widget.shopName,
            reprint: true,
          ),
        );
      }
    }

    if (!mounted) {
      return;
    }

    setState(() => _reprintingId = null);

    if (outcome.success) {
      widget.onReprint?.call(record);
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(outcome.message),
        backgroundColor: outcome.success ? null : Theme.of(context).colorScheme.error,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final records = _filtered;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Historique des ventes'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: TextField(
              key: const Key('pos-history-search'),
              style: const TextStyle(fontSize: 17),
              decoration: const InputDecoration(
                hintText: 'Montant, moyen de paiement ou numero',
                prefixIcon: Icon(Icons.search),
                border: OutlineInputBorder(),
                isDense: true,
              ),
              onChanged: (value) => setState(() => _query = value),
            ),
          ),
        ),
      ),
      body: SafeArea(
        child: records.isEmpty
            ? PosEmptyState(
                icon: Icons.receipt_long_outlined,
                title: widget.history.isEmpty
                    ? 'Aucune vente aujourd\u2019hui'
                    : 'Aucun resultat',
                message: widget.history.isEmpty
                    ? 'Les ventes encaissees sur cette tablette apparaitront ici, '
                        'et vous pourrez reimprimer leur ticket.'
                    : 'Aucune vente ne correspond a \u00ab $_query \u00bb.',
              )
            : ListView.separated(
                padding: const EdgeInsets.all(12),
                itemCount: records.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final record = records[index];
                  final busy = _reprintingId == record.id;

                  return Card(
                    key: Key('pos-history-${record.id}'),
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      leading: CircleAvatar(
                        backgroundColor: record.queued
                            ? scheme.tertiaryContainer
                            : scheme.primaryContainer,
                        child: Icon(
                          record.queued ? Icons.cloud_upload_outlined : Icons.check,
                          color: record.queued
                              ? scheme.onTertiaryContainer
                              : scheme.onPrimaryContainer,
                        ),
                      ),
                      title: Text(
                        formatPosMinor(record.totalMinor),
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      subtitle: Text(
                        '${_formatTime(record.paidAt)} \u00b7 ${record.summary}'
                        '${record.paymentLabel != null ? ' \u00b7 ${record.paymentLabel}' : ''}'
                        '${record.queued ? ' \u00b7 en attente de reseau' : ''}',
                        style: const TextStyle(fontSize: 15),
                      ),
                      trailing: IconButton(
                        key: Key('pos-history-reprint-${record.id}'),
                        tooltip: widget.printer == null
                            ? 'Aucune imprimante configuree'
                            : 'Reimprimer le ticket',
                        onPressed: widget.printer == null || busy
                            ? null
                            : () => _reprint(record),
                        icon: busy
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.print_outlined),
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }

  String _formatTime(DateTime value) {
    final hour = value.hour.toString().padLeft(2, '0');
    final minute = value.minute.toString().padLeft(2, '0');

    return '$hour:$minute';
  }
}
