/// **Moteur de synchronisation** de la file hors ligne — logique partagee.
///
/// La file elle-meme (Hive) reste dans chaque app : elle persiste des payloads
/// qui lui sont propres. Ce que les deux apps doivent partager, c'est la
/// **decision** : que faire d'une operation qui echoue ? La rejouer, attendre,
/// ou la declarer en conflit et la remonter a l'operateur ?
///
/// Sans cette regle commune, une caisse rejoue indefiniment un remboursement
/// refuse par le serveur (et masque le vrai probleme), pendant que l'autre
/// abandonne au premier hoquet reseau (et perd la vente).
library;

import 'pos_ticket.dart';

/// Etat d'une operation dans le moteur.
enum PosSyncOpState {
  /// Pas encore tentee.
  idle,

  /// Rejeu en cours.
  syncing,

  /// Reussie : a retirer de la file.
  done,

  /// Echec transitoire : a retenter apres un delai.
  retry,

  /// Rejet definitif : a conserver pour l'operateur, ne plus rejouer.
  conflict,
}

/// Une operation telle que le moteur la voit.
class PosSyncOperation {
  const PosSyncOperation({
    required this.idempotencyKey,
    required this.attempts,
    this.lastAttemptAt,
    this.lastStatusCode,
    this.lastError,
  });

  final String idempotencyKey;
  final int attempts;
  final DateTime? lastAttemptAt;
  final int? lastStatusCode;
  final String? lastError;

  PosSyncOperation afterAttempt({
    required int? statusCode,
    String? error,
    required DateTime at,
  }) =>
      PosSyncOperation(
        idempotencyKey: idempotencyKey,
        attempts: attempts + 1,
        lastAttemptAt: at,
        lastStatusCode: statusCode,
        lastError: error,
      );

  /// Cette operation est-elle due maintenant ?
  ///
  /// Le delai suit un backoff exponentiel borne : une coupure de 10 minutes ne
  /// doit pas marteler le serveur, et une operation ne doit pas rester bloquee
  /// une heure apres le retour du reseau.
  bool isDue(DateTime now, {Duration base = const Duration(seconds: 2)}) {
    if (attempts == 0 || lastAttemptAt == null) {
      return true;
    }

    final delay = posRetryDelay(attempts, base: base);

    return now.difference(lastAttemptAt!) >= delay;
  }

  /// Etat deduit de la derniere tentative.
  PosSyncOpState get state {
    if (lastAttemptAt == null) {
      return PosSyncOpState.idle;
    }

    if (lastStatusCode != null && lastStatusCode! >= 200 && lastStatusCode! < 300) {
      return PosSyncOpState.done;
    }

    return posShouldRetry(statusCode: lastStatusCode, attempts: attempts)
        ? PosSyncOpState.retry
        : PosSyncOpState.conflict;
  }
}

/// Resultat d'un cycle de synchronisation.
class PosSyncRunResult {
  const PosSyncRunResult({
    required this.operations,
    required this.progress,
    this.conflicts = const [],
  });

  /// Etat mis a jour de chaque operation (a reecrire dans la file).
  final List<PosSyncOperation> operations;

  /// Progression a afficher.
  final PosSyncProgress progress;

  /// Operations en conflit, avec leur motif — a remonter a l'operateur.
  final List<PosSyncOperation> conflicts;

  /// Cles a retirer de la file (reussies).
  List<String> get completedKeys => [
        for (final operation in operations)
          if (operation.state == PosSyncOpState.done) operation.idempotencyKey,
      ];

  /// Cles a conserver (retry ou conflit).
  List<String> get retainedKeys => [
        for (final operation in operations)
          if (operation.state != PosSyncOpState.done) operation.idempotencyKey,
      ];
}

/// Moteur pur : il ne fait aucun I/O, il decide.
///
/// L'app lui passe l'etat courant des operations et le resultat de chaque
/// tentative ; il renvoie l'etat suivant et la progression a afficher. C'est ce
/// qui rend la regle testable sans reseau ni Hive.
class PosSyncEngine {
  const PosSyncEngine({this.maxAttempts = 8, this.baseDelay = const Duration(seconds: 2)});

  final int maxAttempts;
  final Duration baseDelay;

  /// Quelles operations sont dues a cet instant ?
  List<PosSyncOperation> dueOperations(List<PosSyncOperation> operations, DateTime now) => [
        for (final operation in operations)
          if (operation.state != PosSyncOpState.done &&
              operation.state != PosSyncOpState.conflict &&
              operation.isDue(now, base: baseDelay))
            operation,
      ];

  /// Applique le resultat d'une tentative a une operation.
  PosSyncOperation applyOutcome(
    PosSyncOperation operation, {
    required int? statusCode,
    String? error,
    required DateTime at,
  }) {
    final updated = operation.afterAttempt(statusCode: statusCode, error: error, at: at);

    // Au-dela du plafond de tentatives, on ne rejoue plus : l'operation passe
    // en conflit pour que l'operateur la voie, plutot que de tourner en boucle
    // silencieusement. On lui donne toujours un motif lisible : un conflit
    // muet n'est pas actionnable.
    if (updated.state == PosSyncOpState.conflict && updated.lastError == null) {
      return PosSyncOperation(
        idempotencyKey: updated.idempotencyKey,
        attempts: updated.attempts,
        lastAttemptAt: updated.lastAttemptAt,
        lastStatusCode: updated.lastStatusCode,
        lastError: updated.attempts >= maxAttempts
            ? 'Nombre maximal de tentatives atteint.'
            : 'Refus du serveur.',
      );
    }

    return updated;
  }

  /// Construit le resultat d'un cycle complet.
  PosSyncRunResult summarize(List<PosSyncOperation> operations, {bool running = false}) {
    var synced = 0;
    var failed = 0;
    var conflicts = 0;
    final conflictList = <PosSyncOperation>[];

    for (final operation in operations) {
      switch (operation.state) {
        case PosSyncOpState.done:
          synced++;
        case PosSyncOpState.retry:
          failed++;
        case PosSyncOpState.conflict:
          conflicts++;
          conflictList.add(operation);
        case PosSyncOpState.idle:
        case PosSyncOpState.syncing:
          break;
      }
    }

    return PosSyncRunResult(
      operations: operations,
      conflicts: conflictList,
      progress: PosSyncProgress(
        total: operations.length,
        synced: synced,
        failed: failed,
        conflicts: conflicts,
        running: running,
        lastError: conflictList.isEmpty ? null : conflictList.first.lastError,
      ),
    );
  }
}
