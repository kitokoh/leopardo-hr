import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

/// Une opération d'encaissement mise en file faute de réseau.
///
/// La **clé d'idempotence est stable** : elle est dérivée de la commande, du
/// montant et du moyen de paiement. Un rejeu après reconnexion ne crée donc
/// pas de double encaissement — c'est le même contrat que le noyau web
/// (`front/web/src/lib/offline/pos-offline.ts`) et que le backend
/// (`idempotency_key` unique par tenant).
class QueuedOperation {
  const QueuedOperation({
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

  QueuedOperation withFailure(String error) => QueuedOperation(
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

  factory QueuedOperation.fromJson(Map<String, dynamic> json) => QueuedOperation(
        idempotencyKey: json['idempotency_key'] as String,
        endpoint: json['endpoint'] as String,
        payload: (json['payload'] as Map<String, dynamic>?) ?? const {},
        createdAt: DateTime.parse(json['created_at'] as String),
        attempts: (json['attempts'] as num?)?.toInt() ?? 0,
        lastError: json['last_error'] as String?,
      );
}

/// File d'attente hors ligne du POS Restaurant tablette (#9214).
///
/// Persistée dans Hive : une opération mise en file survit à la fermeture de
/// l'application. C'est la raison d'être du natif — un encaissement ne doit
/// jamais être perdu parce que le réseau est tombé.
class PosOfflineQueue {
  PosOfflineQueue._(this._box);

  static const _boxName = 'pos_offline_queue';

  final Box<dynamic> _box;

  static Future<PosOfflineQueue> open() async {
    await Hive.initFlutter();

    return PosOfflineQueue._(await Hive.openBox<dynamic>(_boxName));
  }

  /// Construit une clé d'idempotence **stable** pour un encaissement.
  ///
  /// Deux appels avec les mêmes arguments produisent la même clé : c'est ce
  /// qui rend le rejeu sûr.
  static String paymentKey({
    required int orderId,
    required int amountMinor,
    required String method,
  }) =>
      'pos-pay-$orderId-$amountMinor-$method';

  Future<void> enqueue(QueuedOperation operation) async {
    await _box.put(operation.idempotencyKey, jsonEncode(operation.toJson()));
  }

  List<QueuedOperation> pending() {
    final result = <QueuedOperation>[];

    for (final raw in _box.values) {
      if (raw is! String) {
        continue;
      }

      try {
        result.add(QueuedOperation.fromJson(jsonDecode(raw) as Map<String, dynamic>));
      } catch (_) {
        // Entrée corrompue : ignorée, la file reste utilisable.
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
    final existing = pending().where((op) => op.idempotencyKey == idempotencyKey).firstOrNull;

    if (existing == null) {
      return;
    }

    await _box.put(idempotencyKey, jsonEncode(existing.withFailure(error).toJson()));
  }

  Future<void> clear() async {
    await _box.clear();
  }
}
