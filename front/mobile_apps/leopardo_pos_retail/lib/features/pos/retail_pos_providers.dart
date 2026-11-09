import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_ble_printer.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_printer.dart';

import '../../core/api/retail_api_client.dart';

/// Providers de la **configuration runtime** et de l'**imprimante** du POS
/// Retail (#9263, #9264).
///
/// La configuration est lue une fois au demarrage depuis Hive : une tablette
/// redemarree retrouve son serveur sans repasser par les reglages.

/// Configuration courante de la tablette.
///
/// `null` = premiere mise en service : l'app affiche l'ecran de reglages
/// avant d'ouvrir la caisse, plutot que de partir sur une URL par defaut qui
/// pointerait vers le mauvais serveur.
final retailConfigProvider = Provider<PosAppConfig?>((ref) {
  final store = ref.watch(retailLocalStoreProvider);

  return store.readConfig();
});

/// Empreinte du PIN de l'ecran de reglages.
final retailPinHashProvider = Provider<String?>((ref) {
  final store = ref.watch(retailLocalStoreProvider);

  return store.pinHash;
});

/// Service d'impression de la tablette.
///
/// Surchargeable en test par un [FakePosPrinterService] : les ecrans ne
/// dependent que de l'interface, jamais du Bluetooth.
final retailPrinterProvider = Provider<PosPrinterService>((ref) {
  final store = ref.watch(retailLocalStoreProvider);
  final saved = store.printer;

  final service = BlePosPrinterService(deviceId: saved?.id);

  if (saved != null) {
    // Re-selectionne l'imprimante retenue : la caisse se reconnecte seule au
    // demarrage suivant, sans repasser par l'ecran de decouverte.
    service.select(PosPrinterDevice(id: saved.id, name: saved.name));
  }

  ref.onDispose(service.dispose);

  return service;
});
