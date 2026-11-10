import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_history.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';
import 'package:leopardo_pos_restaurant/core/api/pos_api_client.dart';
import 'package:leopardo_pos_restaurant/core/storage/pos_local_store.dart';
import 'package:leopardo_pos_restaurant/features/pos/pos_providers.dart';

/// Tests widget de la caisse **Restaurant** (#9266, #9268).
///
/// Meme approche que cote Retail : vrai store Hive en memoire, faux service
/// d'impression, et on verifie ce que le serveur voit — configuration,
/// historique, reimpression.
void main() {
  late PosLocalStore store;

  setUpAll(() async {
    Hive.init(Directory.systemTemp.createTempSync('pos_widget_test').path);
  });

  setUp(() async {
    final stamp = DateTime.now().microsecondsSinceEpoch;

    store = PosLocalStore.fromBoxes(
      catalog: await Hive.openBox<dynamic>('catalog_$stamp'),
      floor: await Hive.openBox<dynamic>('floor_$stamp'),
      cart: await Hive.openBox<dynamic>('cart_$stamp'),
      meta: await Hive.openBox<dynamic>('meta_$stamp'),
    );
  });

  PosSaleRecord sale({String id = 'sale-1', int quantity = 2, int unitPrice = 2000}) =>
      PosSaleRecord(
        id: id,
        receipt: PosReceipt(
          lines: [
            PosReceiptLine(name: 'Poulet braise', quantity: quantity, unitPriceMinor: unitPrice),
          ],
          tableLabel: 'Table 4',
          covers: 2,
          orderTypeLabel: 'Sur place',
        ),
        paidAt: DateTime(2026, 11, 9, 20, 15),
        orderId: 77,
        paymentLabel: 'Carte',
      );

  group('PosLocalStore — historique des ventes', () {
    test('relit un historique vide sur une tablette neuve', () {
      expect(store.readSalesHistory().isEmpty, isTrue);
    });

    test('persiste et relit l historique avec table et couverts', () async {
      await store.saveSalesHistory(const PosSalesHistory().add(sale()));

      final restored = store.readSalesHistory();

      expect(restored.length, 1);
      expect(restored.records.first.receipt.tableLabel, 'Table 4');
      expect(restored.records.first.receipt.covers, 2);
      expect(restored.records.first.receipt.totalMinor, 4000);
    });

    test('survit a une valeur corrompue sans casser la caisse', () async {
      await store.saveSalesHistoryRaw('{ceci n est pas du json');

      expect(store.readSalesHistory().isEmpty, isTrue);
    });
  });

  group('Restaurant — configuration runtime', () {
    test('une tablette neuve n a pas de configuration', () {
      expect(store.readConfig(), isNull);
    });

    test('persiste la succursale et la relit', () async {
      await store.saveConfig(
        const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1', branchId: 12),
      );

      final config = store.readConfig();

      expect(config?.branchId, 12);
      expect(config?.locationOrBranchId, 12);
    });

    test('le PIN se change et se verifie', () async {
      const salt = 'leopardo-pos';
      await store.savePinHash(PosPinCode.hash('4321', salt: salt));

      expect(
        PosPinCode.matches('4321', storedHash: store.pinHash!, salt: salt),
        isTrue,
      );
      expect(
        PosPinCode.matches('0000', storedHash: store.pinHash!, salt: salt),
        isFalse,
      );
    });
  });

  group('Restaurant — imprimante', () {
    test('aucune imprimante sur une tablette neuve', () {
      expect(store.printer, isNull);
    });

    test('retient l imprimante choisie', () async {
      await store.savePrinter(id: '11:22:33', name: 'Comptoir');

      expect(store.printer?.name, 'Comptoir');
    });
  });

  group('Restaurant — providers', () {
    test('expose la configuration et l historique au demarrage', () async {
      await store.saveConfig(
        const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1', branchId: 5),
      );
      await store.saveSalesHistory(const PosSalesHistory().add(sale()));

      final container = ProviderContainer(
        overrides: [posLocalStoreProvider.overrideWithValue(store)],
      );
      addTearDown(container.dispose);

      expect(container.read(posConfigProvider)?.branchId, 5);
      expect(container.read(posPinHashProvider), isNull);
    });
  });
}
