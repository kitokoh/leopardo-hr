/// **Actions de commande** partagees par les deux caisses POS.
///
/// Trois notions qu'une caisse reelle utilise toute la journee et qui
/// manquaient aux deux apps :
///  - **mise en attente** (park) : un client n'a pas fini de choisir, on met la
///    commande de cote et on encaisse le suivant ;
///  - **annulation de ligne motivee** (void) : une erreur de saisie, une casse,
///    un offert — la ligne sort du total mais reste tracee ;
///  - **apercu du ticket** : montrer l'addition au client avant de l'encaisser.
///
/// Pur (aucune dependance Flutter) et partage : la regle de calcul du total
/// apres annulation ne doit exister qu'une fois, sinon les deux caisses
/// finissent par diverger de quelques centimes.
library;

/// Motif d'annulation d'une ligne.
///
/// Liste **fermee** : un motif libre n'est pas exploitable en comptabilite, et
/// un champ vide ne justifie rien. `wireValue` est le contrat backend.
enum PosVoidReason {
  entryError('entry_error', 'Erreur de saisie'),
  breakage('breakage', 'Casse / perte'),
  offered('offered', 'Offert'),
  customerChanged('customer_changed', 'Client a change d\'avis'),
  outOfStock('out_of_stock', 'Rupture de stock'),
  other('other', 'Autre');

  const PosVoidReason(this.wireValue, this.label);

  final String wireValue;
  final String label;

  /// Un motif `other` exige un commentaire : sans lui, la trace ne dit rien.
  bool get requiresComment => this == PosVoidReason.other;

  static PosVoidReason fromWire(String? value) => PosVoidReason.values.firstWhere(
        (reason) => reason.wireValue == value,
        orElse: () => PosVoidReason.other,
      );
}

/// Une ligne annulee, conservee pour la trace.
class PosVoidedLine {
  const PosVoidedLine({
    required this.itemId,
    required this.itemName,
    required this.quantity,
    required this.amountMinor,
    required this.reason,
    this.comment,
    required this.voidedAt,
  });

  final int itemId;
  final String itemName;
  final int quantity;

  /// Montant annule (positif), en unites mineures.
  final int amountMinor;

  final PosVoidReason reason;
  final String? comment;
  final DateTime voidedAt;

  /// Un motif `other` sans commentaire n'est pas une trace valable.
  bool get isValid =>
      amountMinor > 0 &&
      quantity > 0 &&
      (!reason.requiresComment || (comment ?? '').trim().isNotEmpty);

  Map<String, dynamic> toJson() => {
        'item_id': itemId,
        'item_name': itemName,
        'quantity': quantity,
        'amount_minor': amountMinor,
        'reason': reason.wireValue,
        'comment': comment,
        'voided_at': voidedAt.toIso8601String(),
      };

  factory PosVoidedLine.fromJson(Map<String, dynamic> json) => PosVoidedLine(
        itemId: (json['item_id'] as num).toInt(),
        itemName: (json['item_name'] as String?) ?? '',
        quantity: (json['quantity'] as num?)?.toInt() ?? 0,
        amountMinor: (json['amount_minor'] as num?)?.toInt() ?? 0,
        reason: PosVoidReason.fromWire(json['reason'] as String?),
        comment: json['comment'] as String?,
        voidedAt: DateTime.tryParse(json['voided_at'] as String? ?? '') ?? DateTime.now(),
      );
}

/// Une commande mise en attente.
///
/// Le `payload` est le panier serialise par l'app : le noyau partage ne
/// connait pas les modeles de chaque app, il transporte donc un JSON opaque.
/// C'est ce qui permet de partager la mise en attente sans partager les
/// modeles metier.
class PosParkedTicket {
  const PosParkedTicket({
    required this.id,
    required this.label,
    required this.payload,
    required this.parkedAt,
    this.itemCount = 0,
    this.totalMinor = 0,
    this.tableLabel,
  });

  final String id;

  /// Libelle lisible : « Table 4 », « Client au comptoir », « M. Diallo ».
  final String label;

  /// Panier serialise (opaque pour le noyau).
  final Map<String, dynamic> payload;

  final DateTime parkedAt;
  final int itemCount;
  final int totalMinor;
  final String? tableLabel;

  /// Age du ticket : sert a signaler une commande oubliee depuis longtemps.
  Duration ageAt(DateTime now) => now.difference(parkedAt);

  /// Un ticket en attente depuis plus de 2 h est probablement un oubli.
  bool isStaleAt(DateTime now, {Duration threshold = const Duration(hours: 2)}) =>
      ageAt(now) > threshold;

  Map<String, dynamic> toJson() => {
        'id': id,
        'label': label,
        'payload': payload,
        'parked_at': parkedAt.toIso8601String(),
        'item_count': itemCount,
        'total_minor': totalMinor,
        'table_label': tableLabel,
      };

  factory PosParkedTicket.fromJson(Map<String, dynamic> json) => PosParkedTicket(
        id: json['id'] as String,
        label: (json['label'] as String?) ?? 'Commande',
        payload: (json['payload'] as Map<String, dynamic>?) ?? const {},
        parkedAt: DateTime.tryParse(json['parked_at'] as String? ?? '') ?? DateTime.now(),
        itemCount: (json['item_count'] as num?)?.toInt() ?? 0,
        totalMinor: (json['total_minor'] as num?)?.toInt() ?? 0,
        tableLabel: json['table_label'] as String?,
      );
}

/// File des commandes en attente.
///
/// Immuable : chaque operation renvoie une nouvelle file. La liste est triee
/// du plus recent au plus ancien, parce que le ticket qu'on rappelle est
/// presque toujours celui qu'on vient de mettre de cote.
class PosParkedTickets {
  const PosParkedTickets({this.tickets = const []});

  final List<PosParkedTicket> tickets;

  /// Plafond : au-dela, on refuse une nouvelle mise en attente plutot que de
  /// laisser la liste devenir illisible.
  static const int maxTickets = 30;

  bool get isFull => tickets.length >= maxTickets;

  bool get isEmpty => tickets.isEmpty;

  int get length => tickets.length;

  PosParkedTickets park(PosParkedTicket ticket) {
    if (isFull) {
      return this;
    }

    final updated = [ticket, ...tickets.where((existing) => existing.id != ticket.id)]
      ..sort((a, b) => b.parkedAt.compareTo(a.parkedAt));

    return PosParkedTickets(tickets: updated);
  }

  /// Retire et renvoie le ticket demande (null s'il n'existe plus).
  ({PosParkedTickets remaining, PosParkedTicket? ticket}) recall(String id) {
    PosParkedTicket? found;

    for (final ticket in tickets) {
      if (ticket.id == id) {
        found = ticket;
        break;
      }
    }

    if (found == null) {
      return (remaining: this, ticket: null);
    }

    return (
      remaining: PosParkedTickets(
        tickets: [for (final ticket in tickets) if (ticket.id != id) ticket],
      ),
      ticket: found,
    );
  }

  PosParkedTickets remove(String id) => PosParkedTickets(
        tickets: [for (final ticket in tickets) if (ticket.id != id) ticket],
      );

  /// Tickets oublies depuis plus de [threshold] : a signaler a l'operateur.
  List<PosParkedTicket> staleAt(
    DateTime now, {
    Duration threshold = const Duration(hours: 2),
  }) =>
      [for (final ticket in tickets) if (ticket.isStaleAt(now, threshold: threshold)) ticket];

  Map<String, dynamic> toJson() => {
        'tickets': [for (final ticket in tickets) ticket.toJson()],
      };

  factory PosParkedTickets.fromJson(Map<String, dynamic> json) {
    final raw = (json['tickets'] as List<dynamic>?) ?? const [];
    final parsed = <PosParkedTicket>[];

    for (final entry in raw) {
      if (entry is Map<String, dynamic>) {
        try {
          parsed.add(PosParkedTicket.fromJson(entry));
        } catch (_) {
          // Entree corrompue : ignoree, la file reste utilisable.
        }
      }
    }

    parsed.sort((a, b) => b.parkedAt.compareTo(a.parkedAt));

    return PosParkedTickets(tickets: parsed);
  }
}

/// Ligne d'un ticket affiche (apercu avant encaissement).
class PosReceiptLine {
  const PosReceiptLine({
    required this.name,
    required this.quantity,
    required this.unitPriceMinor,
    this.options = const [],
  });

  final String name;
  final int quantity;

  /// Prix unitaire **options comprises** : c'est ce que le client paie par
  /// unite, et ce qui doit apparaitre sur le ticket.
  final int unitPriceMinor;

  /// Options choisies, deja resolues en libelles lisibles
  /// (ex. « Cuisson : Saignant », « + Fromage »).
  ///
  /// Le ticket ne connait pas les identifiants du menu : il recoit du texte
  /// pret a imprimer, ce qui evite de faire dependre le noyau du modele
  /// metier de chaque app.
  final List<String> options;

  int get totalMinor => unitPriceMinor * quantity;

  bool get hasOptions => options.isNotEmpty;
}

/// Un ticket pret a etre montre au client.
///
/// Le calcul est centralise ici : c'est exactement le meme total que celui
/// encaisse, ce qui evite le classique « le ticket dit 12,00 et la caisse
/// demande 12,50 ».
class PosReceipt {
  const PosReceipt({
    required this.lines,
    this.discountMinor = 0,
    this.tipMinor = 0,
    this.voidedLines = const [],
    this.tableLabel,
    this.covers,
    this.orderTypeLabel,
    this.note,
  });

  final List<PosReceiptLine> lines;
  final int discountMinor;
  final int tipMinor;
  final List<PosVoidedLine> voidedLines;
  final String? tableLabel;
  final int? covers;
  final String? orderTypeLabel;
  final String? note;

  int get subtotalMinor => lines.fold(0, (sum, line) => sum + line.totalMinor);

  /// Total net : jamais negatif, meme si la remise depasse le sous-total.
  int get netMinor {
    final net = subtotalMinor - discountMinor;

    return net < 0 ? 0 : net;
  }

  /// Total a payer : net + pourboire (le pourboire n'est pas une remise).
  int get totalMinor => netMinor + tipMinor;

  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);

  /// Montant total annule (positif), pour la trace.
  int get voidedMinor => voidedLines.fold(0, (sum, line) => sum + line.amountMinor);

  bool get isEmpty => lines.isEmpty;

  /// Le ticket est-il coherent ? Une remise superieure au sous-total est
  /// refusee, et une ligne annulee sans motif valable aussi.
  bool get isValid =>
      lines.isNotEmpty &&
      discountMinor <= subtotalMinor &&
      voidedLines.every((line) => line.isValid);
}

/// Etat d'une operation de la file hors ligne, vu par l'operateur.
enum PosSyncStatus {
  /// En attente de rejeu.
  pending('En attente'),

  /// Rejeu en cours.
  syncing('Synchronisation...'),

  /// Rejouee avec succes.
  synced('Synchronisee'),

  /// Echec reseau : sera retentee avec un delai croissant.
  retrying('Nouvel essai prevu'),

  /// Rejet metier (4xx) : ne sera plus rejouee, l'operateur doit agir.
  conflict('Conflit — action requise');

  const PosSyncStatus(this.label);

  final String label;

  bool get isTerminal => this == PosSyncStatus.synced || this == PosSyncStatus.conflict;
}

/// Progression de la synchronisation, exposee a l'interface.
///
/// Une file muette est le pire des cas : l'operateur ne sait pas si ses ventes
/// sont parties. Ce modele donne un compteur, un pourcentage et le detail des
/// operations en echec.
class PosSyncProgress {
  const PosSyncProgress({
    this.total = 0,
    this.synced = 0,
    this.failed = 0,
    this.conflicts = 0,
    this.running = false,
    this.lastError,
  });

  final int total;
  final int synced;
  final int failed;
  final int conflicts;
  final bool running;
  final String? lastError;

  int get remaining => total - synced - conflicts;

  /// Pourcentage traite (0-100), jamais hors bornes.
  int get percent {
    if (total <= 0) {
      return 100;
    }

    final done = synced + conflicts;
    final value = (done * 100) ~/ total;

    return value.clamp(0, 100);
  }

  bool get isComplete => total > 0 && remaining <= 0;

  bool get hasConflicts => conflicts > 0;

  /// Message lisible pour le bandeau de caisse.
  String get message {
    if (total == 0) {
      return 'Tout est synchronise.';
    }

    if (running) {
      return 'Synchronisation $synced/$total...';
    }

    if (hasConflicts) {
      return '$conflicts operation(s) refusee(s) par le serveur — verifiez la caisse.';
    }

    if (failed > 0) {
      return '$failed operation(s) en echec, nouvel essai automatique.';
    }

    if (isComplete) {
      return '$synced operation(s) synchronisee(s).';
    }

    return '$remaining operation(s) en attente de reseau.';
  }

  PosSyncProgress copyWith({
    int? total,
    int? synced,
    int? failed,
    int? conflicts,
    bool? running,
    String? lastError,
    bool clearError = false,
  }) =>
      PosSyncProgress(
        total: total ?? this.total,
        synced: synced ?? this.synced,
        failed: failed ?? this.failed,
        conflicts: conflicts ?? this.conflicts,
        running: running ?? this.running,
        lastError: clearError ? null : (lastError ?? this.lastError),
      );
}

/// Delai de reprise exponentiel, borne.
///
/// Une caisse hors ligne pendant une coupure de 10 minutes ne doit pas marteler
/// le serveur : 2 s, 4 s, 8 s... plafonne a 60 s. Le plafond evite aussi qu'une
/// operation reste bloquee une heure apres le retour du reseau.
Duration posRetryDelay(int attempts, {Duration base = const Duration(seconds: 2)}) {
  if (attempts <= 0) {
    return Duration.zero;
  }

  const maxSeconds = 60;
  final seconds = base.inSeconds * (1 << (attempts - 1).clamp(0, 6));

  return Duration(seconds: seconds.clamp(0, maxSeconds));
}

/// Une operation est-elle encore rejouable ?
///
/// Un rejet metier (4xx hors 408/429) ne se rejoue pas : reessayer un
/// remboursement refuse par le serveur ne fera que le refuser encore, et
/// masquerait le vrai probleme a l'operateur.
bool posShouldRetry({required int? statusCode, required int attempts, int maxAttempts = 8}) {
  if (attempts >= maxAttempts) {
    return false;
  }

  if (statusCode == null) {
    // Pas de statut = echec reseau : on reessaie.
    return true;
  }

  if (statusCode == 408 || statusCode == 429) {
    return true;
  }

  if (statusCode >= 500) {
    return true;
  }

  return false;
}

/// Classe un code de statut HTTP en statut de synchronisation.
PosSyncStatus posSyncStatusFor({required int? statusCode, required int attempts}) {
  if (statusCode != null && statusCode >= 200 && statusCode < 300) {
    return PosSyncStatus.synced;
  }

  if (posShouldRetry(statusCode: statusCode, attempts: attempts)) {
    return PosSyncStatus.retrying;
  }

  return PosSyncStatus.conflict;
}
