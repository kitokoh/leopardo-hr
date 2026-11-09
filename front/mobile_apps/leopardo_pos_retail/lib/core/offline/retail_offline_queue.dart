import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

/// Une operation de caisse mise en file faute de reseau.
///
/// La **cle d'idempotence est stable** : derivee de la commande, du montant et
/// du moyen de paiement. Un rejeu apres reconnexion ne cree donc pas de double
/// encaissement — meme contrat que le backend (`idempotency_key` unique par
/// tenant) et que le noyau web (`front/web/src/lib/offline/pos-offline.ts`).
class QueuedRetailOperation {
  const QueuedRetailOperation({
    required this.idempotencyKey,
    required this.endpoint,
    required this.payload,
    required this.createdAt,
    this.attempts = 0,
    this.lastError,
  });

  final String idempotencyKey;
  final String endpoint;
  final Map<String, dynamic> payload;
  final DateTime createdAt;
  final int attempts;
  final String? lastError;

  QueuedRetailOperation withFailure(String error) => QueuedRetailOperation(
        idempotencyKey: idempotencyKey,
        endpoint: endpoint,
        payload: payload,
        createdAt: createdAt,
        attempts: attempts + 1,
        lastError: error,
      );

  Map<String, dynamic> toJson() => {
        'idempotency_key': idempotencyKey,
        'endpoint': endpoint,
        'payload': payload,
        'created_at': createdAt.toIso8601String(),
        'attempts': attempts,
        'last_error': lastError,
      };

  factory QueuedRetailOperation.fromJson(Map<String, dynamic> json) =>
      QueuedRetailOperation(
        idempotencyKey: json['idempotency_key'] as String,
        endpoint: json['endpoint'] as String,
        payload: (json['payload'] as Map<String, dynamic>?) ?? const {},
        createdAt: DateTime.parse(json['created_at'] as String),
        attempts: (json['attempts'] as num?)?.toInt() ?? 0,
        lastError: json['last_error'] as String?,
      );
}

/// File d'attente hors ligne du POS Retail tablette (#9215).
///
/// Persistee dans Hive : une operation mise en file survit a la fermeture de
/// l'application. C'est la raison d'etre du natif — un encaissement ne doit
/// jamais etre perdu parce que le reseau est tombe.
class RetailOfflineQueue {
  RetailOfflineQueue._(this._box);

  static const _boxName = 'retail_pos_offline_queue';

  final Box<dynamic> _box;

  static Future<RetailOfflineQueue> open() async {
    await Hive.initFlutter();

    return RetailOfflineQueue._(await Hive.openBox<dynamic>(_boxName));
  }

  /// Cle d'idempotence **stable** pour un encaissement.
  ///
  /// Deux appels avec les memes arguments produisent la meme cle : c'est ce
  /// qui rend le rejeu sur.
  static String paymentKey({
    required int orderId,
    required int amountMinor,
    required String method,
  }) =>
      'retail-pay-$orderId-$amountMinor-$method';

  /// Cle d'idempotence stable pour un remboursement.
  static String refundKey({
    required int orderId,
    required int amountMinor,
    required String method,
  }) =>
      'retail-refund-$orderId-$amountMinor-$method';

  Future<void> enqueue(QueuedRetailOperation operation) async {
    await _box.put(operation.idempotencyKey, jsonEncode(operation.toJson()));
  }

  List<QueuedRetailOperation> pending() {
    final result = <QueuedRetailOperation>[];

    for (final raw in _box.values) {
      if (raw is! String) {
        continue;
      }

      try {
        result.add(
          QueuedRetailOperation.fromJson(jsonDecode(raw) as Map<String, dynamic>),
        );
      } catch (_) {
        // Entree corrompue : ignoree, la file reste utilisable.
      }
    }

    result.sort((a, b) => a.createdAt.compareTo(b.createdAt));

    return result;
  }

  int get length => pending().length;

  bool get isEmpty => length == 0;

  Future<void> remove(String idempotencyKey) async {
    await _box.delete(idempotencyKey);
  }

  Future<void> recordFailure(String idempotencyKey, String error) async {
    QueuedRetailOperation? existing;

    for (final operation in pending()) {
      if (operation.idempotencyKey == idempotencyKey) {
        existing = operation;
        break;
      }
    }

    if (existing == null) {
      return;
    }

    await _box.put(idempotencyKey, jsonEncode(existing.withFailure(error).toJson()));
  }

  Future<void> clear() async {
    await _box.clear();
  }
}
