import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/pos/pos_ble_printer.dart';
import 'package:leopardo_core/core/pos/pos_config.dart';
import 'package:leopardo_core/core/pos/pos_esc_pos.dart';
import 'package:leopardo_core/core/pos/pos_printer.dart';

import '../../core/api/pos_api_client.dart';

/// Providers de la **configuration runtime** et de l'**imprimante** du POS
/// Restaurant (#9263, #9264).
///
/// La configuration est lue une fois au demarrage depuis Hive : une tablette
/// redemarree retrouve son serveur sans repasser par les reglages.

/// Configuration courante de la tablette (URL API + succursale).
final posConfigProvider = Provider<PosAppConfig?>((ref) {
  final store = ref.watch(posLocalStoreProvider);

  return store.readConfig();
});

/// Empreinte du PIN de l'ecran de reglages.
final posPinHashProvider = Provider<String?>((ref) {
  final store = ref.watch(posLocalStoreProvider);

  return store.pinHash;
});

/// Service d'impression de la tablette.
final posPrinterProvider = Provider<PosPrinterService>((ref) {
  final store = ref.watch(posLocalStoreProvider);
  final saved = store.printer;

  final service = BlePosPrinterService(deviceId: saved?.id);

  if (saved != null) {
    service.select(PosPrinterDevice(id: saved.id, name: saved.name));
  }

  ref.onDispose(service.dispose);

  return service;
});
