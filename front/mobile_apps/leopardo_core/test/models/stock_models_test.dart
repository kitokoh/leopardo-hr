import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/stock_models.dart';

/// Modeles de stock partages (#9272).
///
/// Le contrat verifie ici est la TOLERANCE : un payload partiel ne doit jamais
/// lever. C'est la classe de bug qui casse un ecran en production quand le
/// serveur omet un champ optionnel — le test la verrouille.
void main() {
  group('StockLevel', () {
    test('lit un payload complet', () {
      final level = StockLevel.fromJson({
        'id': 12,
        'location_id': 3,
        'product_id': 44,
        'product_name': 'Jus de bissap',
        'quantity': '18.500',
        'unit': 'L',
        'avg_cost_minor': 1200,
        'alert_threshold': 20,
        'is_below_threshold': true,
      });

      expect(level.id, 12);
      expect(level.locationId, 3);
      expect(level.productId, 44);
      expect(level.productName, 'Jus de bissap');
      expect(level.quantity, 18.5);
      expect(level.avgCostMinor, 1200);
      expect(level.alertThreshold, 20);
      expect(level.isBelowThreshold, isTrue);
    });

    test('tolere un payload partiel sans lever', () {
      final level = StockLevel.fromJson({'id': 7});

      expect(level.id, 7);
      expect(level.quantity, 0);
      expect(level.productName, isNull);
      expect(level.avgCostMinor, isNull);
      expect(level.isBelowThreshold, isFalse);
    });

    test('tolere un payload vide', () {
      final level = StockLevel.fromJson(const {});

      expect(level.id, 0);
      expect(level.quantity, 0);
    });

    test('accepte les cles de la verticale restaurant', () {
      final level = StockLevel.fromJson({
        'id': 1,
        'branch_id': 9,
        'ingredient_id': 5,
        'ingredient': 'Tomate',
        'quantity': 2,
      });

      expect(level.locationId, 9);
      expect(level.productId, 5);
      expect(level.productName, 'Tomate');
    });

    test('recalcule le seuil seulement quand il est connu', () {
      final below = StockLevel.fromJson({'id': 1, 'quantity': 3, 'alert_threshold': 5});
      final unknown = StockLevel.fromJson({'id': 2, 'quantity': 3});

      expect(below.isBelowThreshold, isTrue);
      expect(unknown.isBelowThreshold, isFalse);
    });
  });

  group('StockMovementReason', () {
    test('normalise les codes des deux verticales', () {
      expect(StockMovementReason.fromWire('purchase'), StockMovementReason.receiving);
      expect(StockMovementReason.fromWire('receiving'), StockMovementReason.receiving);
      expect(StockMovementReason.fromWire('loss'), StockMovementReason.waste);
      expect(StockMovementReason.fromWire('waste'), StockMovementReason.waste);
      expect(StockMovementReason.fromWire('transfer_in'), StockMovementReason.transferIn);
      expect(StockMovementReason.fromWire('n_importe_quoi'), StockMovementReason.unknown);
      expect(StockMovementReason.fromWire(null), StockMovementReason.unknown);
    });

    test('n expose en ecriture que les gestes saisissables', () {
      expect(StockMovementReason.receiving.wireCode, 'purchase');
      expect(StockMovementReason.adjustment.wireCode, 'adjustment');
      expect(StockMovementReason.waste.wireCode, 'loss');
      // Une vente ou un transfert ne se declarent pas a la main.
      expect(StockMovementReason.sale.wireCode, isNull);
      expect(StockMovementReason.transferIn.wireCode, isNull);
    });

    test('distingue les entrees des sorties', () {
      expect(StockMovementReason.receiving.isInbound, isTrue);
      expect(StockMovementReason.transferIn.isInbound, isTrue);
      expect(StockMovementReason.waste.isInbound, isFalse);
      expect(StockMovementReason.sale.isInbound, isFalse);
    });
  });

  group('StockMovement', () {
    test('lit un mouvement complet', () {
      final movement = StockMovement.fromJson({
        'id': 3,
        'location_id': 1,
        'product_id': 2,
        'quantity_delta': '-4.000',
        'unit_cost_minor': 900,
        'reason_code': 'loss',
        'note': 'Casse en reserve',
        'created_at': '2026-11-09T10:00:00+00:00',
      });

      expect(movement.quantityDelta, -4);
      expect(movement.reason, StockMovementReason.waste);
      expect(movement.isInbound, isFalse);
      expect(movement.unitCostMinor, 900);
      expect(movement.createdAt, isNotNull);
    });

    test('tolere un payload partiel', () {
      final movement = StockMovement.fromJson({'id': 1});

      expect(movement.quantityDelta, 0);
      expect(movement.reason, StockMovementReason.unknown);
      expect(movement.createdAt, isNull);
    });
  });

  group('StockAlert', () {
    test('lit une alerte et tolere un payload vide', () {
      final alert = StockAlert.fromJson({
        'id': 4,
        'product_name': 'Farine',
        'quantity': 1,
        'alert_threshold': 10,
      });

      expect(alert.productName, 'Farine');
      expect(alert.alertThreshold, 10);

      final empty = StockAlert.fromJson(const {});
      expect(empty.id, 0);
      expect(empty.quantity, 0);
    });
  });
}
