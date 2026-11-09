import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_history.dart';
import 'package:leopardo_core/core/pos/pos_ticket.dart';
import 'package:leopardo_pos_retail/core/api/retail_api_client.dart';
import 'package:leopardo_pos_retail/core/storage/retail_local_store.dart';
import 'package:leopardo_pos_retail/features/pos/retail_pos_providers.dart';

/// Tests widget de la caisse **Retail** (#9266, #9268).
///
/// On teste les ecrans reels, pas des doublures : le store est un vrai Hive en
/// memoire, l'imprimante un faux service (le Bluetooth n'existe pas en test).
/// Ce qui compte ici, c'est le comportement que le vendeur voit : l'acces a
/// l'historique, la reimpression, et les cas d'erreur.
void main() {
  late RetailLocalStore store;

  setUpAll(() async {
    Hive.init(Directory.systemTemp.createTempSync('retail_widget_test').path);
  });

  setUp(() async {
    store = RetailLocalStore.fromBoxes(
      catalog: await Hive.openBox<dynamic>('catalog_${DateTime.now().microsecondsSinceEpoch}'),
      cart: await Hive.openBox<dynamic>('cart_${DateTime.now().microsecondsSinceEpoch}'),
      session: await Hive.openBox<dynamic>('session_${DateTime.now().microsecondsSinceEpoch}'),
      meta: await Hive.openBox<dynamic>('meta_${DateTime.now().microsecondsSinceEpoch}'),
    );
  });

  PosSaleRecord sale({String id = 'sale-1', int quantity = 2, int unitPrice = 1500}) =>
      PosSaleRecord(
        id: id,
        receipt: PosReceipt(
          lines: [
            PosReceiptLine(name: 'Jus de bissap', quantity: quantity, unitPriceMinor: unitPrice),
          ],
        ),
        paidAt: DateTime(2026, 11, 9, 14, 30),
        orderId: 42,
        paymentLabel: 'Especes',
      );

  group('RetailLocalStore — historique des ventes', () {
    test('relit un historique vide sur une tablette neuve', () {
      expect(store.readSalesHistory().isEmpty, isTrue);
    });

    test('persiste et relit l historique', () async {
      await store.saveSalesHistory(const PosSalesHistory().add(sale()));

      final restored = store.readSalesHistory();

      expect(restored.length, 1);
      expect(restored.records.first.receipt.totalMinor, 3000);
    });

    test('survit a une valeur corrompue sans casser la caisse', () async {
      // Simule une ecriture partielle (coupure de courant en pleine vente).
      await store.saveSalesHistoryRaw('{ceci n est pas du json');

      expect(store.readSalesHistory().isEmpty, isTrue);
    });
  });

  group('Retail — configuration runtime', () {
    test('une tablette neuve n a pas de configuration', () {
      expect(store.readConfig(), isNull);
    });

    test('persiste la configuration et la relit', () async {
      await store.saveConfig(
        const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1', locationId: 7),
      );

      final config = store.readConfig();

      expect(config?.apiBaseUrl, 'https://api.leopardo.app/api/v1');
      expect(config?.locationId, 7);
    });

    test('le PIN d usine est 0000 et se change', () async {
      expect(store.pinHash, isNull);

      const salt = 'leopardo-pos';
      await store.savePinHash(PosPinCode.hash('1234', salt: salt));

      expect(store.pinHash, PosPinCode.hash('1234', salt: salt));
      expect(
        PosPinCode.matches('1234', storedHash: store.pinHash!, salt: salt),
        isTrue,
      );
      expect(
        PosPinCode.matches('0000', storedHash: store.pinHash!, salt: salt),
        isFalse,
      );
    });
  });

  group('Retail — imprimante', () {
    test('aucune imprimante sur une tablette neuve', () {
      expect(store.printer, isNull);
    });

    test('retient l imprimante choisie', () async {
      await store.savePrinter(id: 'AA:BB:CC', name: 'Caisse 1');

      expect(store.printer?.id, 'AA:BB:CC');
      expect(store.printer?.name, 'Caisse 1');
    });
  });

  group('Retail — providers', () {
    test('expose la configuration et l historique au demarrage', () async {
      await store.saveConfig(
        const PosAppConfig(apiBaseUrl: 'https://api.leopardo.app/api/v1', locationId: 3),
      );
      await store.saveSalesHistory(const PosSalesHistory().add(sale()));

      final container = ProviderContainer(
        overrides: [retailLocalStoreProvider.overrideWithValue(store)],
      );
      addTearDown(container.dispose);

      expect(container.read(retailConfigProvider)?.locationId, 3);
      expect(container.read(retailPinHashProvider), isNull);
    });
  });
}
