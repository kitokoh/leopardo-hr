import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/pos/pos_sync.dart';

/// Tests du **moteur de synchronisation** partage.
///
/// La regle qui compte : un rejet metier ne se rejoue pas indefiniment (il
/// masquerait le vrai probleme), un echec reseau se rejoue avec un delai
/// croissant (une coupure ne doit pas marteler le serveur).
void main() {
  PosSyncOperation op(String key,
          {int attempts = 0, DateTime? at, int? status}) =>
      PosSyncOperation(
        idempotencyKey: key,
        attempts: attempts,
        lastAttemptAt: at,
        lastStatusCode: status,
      );

  group('PosSyncOperation', () {
    test('une operation jamais tentee est due', () {
      expect(op('a').isDue(DateTime(2026, 11, 9)), isTrue);
    });

    test('une operation recente n est pas due avant le delai', () {
      final at = DateTime(2026, 11, 9, 10);
      final operation = op('a', attempts: 1, at: at);

      expect(operation.isDue(at.add(const Duration(seconds: 1))), isFalse);
      expect(operation.isDue(at.add(const Duration(seconds: 3))), isTrue);
    });

    test('le delai croit avec le nombre de tentatives', () {
      final at = DateTime(2026, 11, 9, 10);
      final operation = op('a', attempts: 3, at: at);

      // 3 tentatives -> 8 s.
      expect(operation.isDue(at.add(const Duration(seconds: 7))), isFalse);
      expect(operation.isDue(at.add(const Duration(seconds: 9))), isTrue);
    });

    test('deduit son etat du dernier statut', () {
      expect(op('a', attempts: 1, at: DateTime(2026, 11, 9), status: 201).state,
          PosSyncOpState.done);
      expect(op('a', attempts: 1, at: DateTime(2026, 11, 9), status: 409).state,
          PosSyncOpState.conflict);
      expect(op('a', attempts: 1, at: DateTime(2026, 11, 9), status: 503).state,
          PosSyncOpState.retry);
      expect(op('a').state, PosSyncOpState.idle);
    });

    test('afterAttempt incremente et horodate', () {
      final updated = op('a').afterAttempt(
        statusCode: 500,
        error: 'boom',
        at: DateTime(2026, 11, 9, 10),
      );

      expect(updated.attempts, 1);
      expect(updated.lastStatusCode, 500);
      expect(updated.lastError, 'boom');
    });
  });

  group('PosSyncEngine', () {
    const engine = PosSyncEngine();

    test('ne retient que les operations dues', () {
      final now = DateTime(2026, 11, 9, 10);
      final due = engine.dueOperations([
        op('fresh'),
        op('waiting',
            attempts: 1, at: now.subtract(const Duration(seconds: 1))),
        op('ready', attempts: 1, at: now.subtract(const Duration(seconds: 5))),
        op('done', attempts: 1, at: now, status: 201),
        op('conflict', attempts: 1, at: now, status: 422),
      ], now);

      expect(
          due.map((operation) => operation.idempotencyKey), ['fresh', 'ready']);
    });

    test('un 2xx marque l operation comme terminee', () {
      final updated = engine.applyOutcome(
        op('a'),
        statusCode: 200,
        at: DateTime(2026, 11, 9),
      );

      expect(updated.state, PosSyncOpState.done);
    });

    test('un 4xx marque l operation en conflit', () {
      final updated = engine.applyOutcome(
        op('a'),
        statusCode: 409,
        error: 'deja remboursee',
        at: DateTime(2026, 11, 9),
      );

      expect(updated.state, PosSyncOpState.conflict);
    });

    test('un echec reseau reste en reprise', () {
      final updated = engine.applyOutcome(op('a'),
          statusCode: null, at: DateTime(2026, 11, 9));

      expect(updated.state, PosSyncOpState.retry);
    });

    test('abandonne au plafond de tentatives', () {
      final updated = engine.applyOutcome(
        op('a', attempts: 7),
        statusCode: null,
        at: DateTime(2026, 11, 9),
      );

      // 8 tentatives atteintes : plus de rejeu, l operateur doit voir.
      expect(updated.state, PosSyncOpState.conflict);
      expect(updated.lastError, isNotNull);
    });

    test('resume un cycle complet', () {
      final result = engine.summarize([
        op('a', attempts: 1, at: DateTime(2026, 11, 9), status: 201),
        op('b', attempts: 1, at: DateTime(2026, 11, 9), status: 503),
        op('c', attempts: 1, at: DateTime(2026, 11, 9), status: 422),
        op('d'),
      ]);

      expect(result.progress.total, 4);
      expect(result.progress.synced, 1);
      expect(result.progress.failed, 1);
      expect(result.progress.conflicts, 1);
      expect(result.completedKeys, ['a']);
      expect(result.retainedKeys, ['b', 'c', 'd']);
      expect(result.conflicts.single.idempotencyKey, 'c');
    });

    test('un cycle sans operation est complet a 100 %', () {
      final result = engine.summarize([]);

      expect(result.progress.percent, 100);
      expect(result.completedKeys, isEmpty);
    });
  });
}
