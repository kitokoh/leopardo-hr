/// Implementation **Bluetooth LE** de l'impression thermique (#9264).
///
/// Les imprimantes de caisse bon marche exposent presque toutes le meme
/// service serie : `FFE0` avec la caracteristique d'ecriture `FFE1`. Certaines
/// utilisent `18F0`/`2AF1` ou `FF00`/`FF02` ; on essaie donc les paires
/// connues dans l'ordre, puis on retombe sur la premiere caracteristique
/// « writable » annoncee par l'appareil.
///
/// Le module est isole du reste du noyau : les ecrans ne connaissent que
/// [PosPrinterService]. Si le Bluetooth est indisponible (emulateur, tablette
/// sans BLE), l'implementation remonte un message clair au lieu de planter.
library;

import 'dart:async';
import 'dart:io';

import 'package:flutter_blue_plus/flutter_blue_plus.dart';

import 'pos_esc_pos.dart';
import 'pos_printer.dart';

/// Services/caracteristiques d'ecriture connus des imprimantes ESC/POS BLE.
const List<({String service, String characteristic})> _knownPrinterChannels = [
  (service: 'ffe0', characteristic: 'ffe1'),
  (service: 'ff00', characteristic: 'ff02'),
  (service: '18f0', characteristic: '2af1'),
  (
    service: 'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    characteristic: 'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
  ),
];

/// Taille maximale d'un paquet d'ecriture BLE.
///
/// Les imprimantes ont un buffer minuscule : ecrire 4 Ko d'un coup fait
/// perdre des octets au milieu du ticket. 180 octets par paquet, avec une
/// petite pause, est la valeur qui fonctionne sur la majorite du parc.
const int _chunkSize = 180;

/// Implementation BLE de [PosPrinterService].
class BlePosPrinterService implements PosPrinterService {
  BlePosPrinterService({this.deviceId});

  /// Identifiant de l'imprimante retenue (persiste par l'app).
  String? deviceId;

  PosPrinterStatus _status = PosPrinterStatus.disconnected;
  PosPrinterDevice? _selected;
  BluetoothDevice? _device;
  BluetoothCharacteristic? _writeCharacteristic;
  final _controller = StreamController<PosPrinterStatus>.broadcast();

  @override
  PosPrinterStatus get status => _status;

  @override
  PosPrinterDevice? get selectedPrinter => _selected;

  @override
  Stream<PosPrinterStatus> get statusStream => _controller.stream;

  void _setStatus(PosPrinterStatus next) {
    _status = next;

    if (!_controller.isClosed) {
      _controller.add(next);
    }
  }

  /// Le Bluetooth est-il disponible sur cette tablette ?
  Future<bool> isSupported() async {
    if (!Platform.isAndroid && !Platform.isIOS) {
      return false;
    }

    try {
      return await FlutterBluePlus.isSupported;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<List<PosPrinterDevice>> knownPrinters() async {
    try {
      final bonded = await FlutterBluePlus.bondedDevices;

      return [
        for (final device in bonded)
          PosPrinterDevice(
            id: device.remoteId.str,
            name: device.platformName.isEmpty
                ? device.remoteId.str
                : device.platformName,
          ),
      ];
    } catch (_) {
      return const [];
    }
  }

  @override
  Future<List<PosPrinterDevice>> discover({
    Duration timeout = const Duration(seconds: 8),
  }) async {
    if (!await isSupported()) {
      return const [];
    }

    final found = <String, PosPrinterDevice>{};

    try {
      await FlutterBluePlus.startScan(timeout: timeout);

      final subscription = FlutterBluePlus.scanResults.listen((results) {
        for (final result in results) {
          final name = result.device.platformName;

          // On ne garde que les peripheriques qui s'annoncent : une
          // imprimante sans nom est impossible a choisir dans une liste.
          if (name.trim().isEmpty) {
            continue;
          }

          found[result.device.remoteId.str] = PosPrinterDevice(
            id: result.device.remoteId.str,
            name: name,
          );
        }
      });

      await FlutterBluePlus.stopScan();
      await subscription.cancel();
    } catch (_) {
      // Scan impossible (permission refusee, adaptateur eteint) : la liste
      // reste vide et l'ecran propose de reessayer.
    }

    return found.values.toList();
  }

  @override
  Future<void> select(PosPrinterDevice device) async {
    _selected = device;
    deviceId = device.id;
  }

  @override
  Future<PosPrintOutcome> connect() async {
    final target = _selected;

    if (target == null) {
      _setStatus(PosPrinterStatus.error);

      return const PosPrintOutcome.failure('Aucune imprimante selectionnee.');
    }

    if (!await isSupported()) {
      _setStatus(PosPrinterStatus.error);

      return const PosPrintOutcome.failure(
        'Bluetooth indisponible sur cet appareil.',
      );
    }

    _setStatus(PosPrinterStatus.connecting);

    try {
      final device = BluetoothDevice.fromId(target.id);
      await device.connect(timeout: const Duration(seconds: 12));
      _device = device;

      final services = await device.discoverServices();
      _writeCharacteristic = _findWriteCharacteristic(services);

      if (_writeCharacteristic == null) {
        await device.disconnect();
        _device = null;
        _setStatus(PosPrinterStatus.error);

        return const PosPrintOutcome.failure(
          'Cet appareil ne ressemble pas a une imprimante compatible.',
        );
      }

      _setStatus(PosPrinterStatus.connected);

      return const PosPrintOutcome.success();
    } catch (error) {
      _setStatus(PosPrinterStatus.error);

      return PosPrintOutcome.failure(
        'Connexion impossible : ${_humanize(error)}',
      );
    }
  }

  /// Cherche la caracteristique d'ecriture, en essayant les canaux connus
  /// puis en prenant la premiere caracteristique « writable ».
  BluetoothCharacteristic? _findWriteCharacteristic(
    List<BluetoothService> services,
  ) {
    for (final channel in _knownPrinterChannels) {
      for (final service in services) {
        if (service.uuid.str.toLowerCase() != channel.service.toLowerCase()) {
          continue;
        }

        for (final characteristic in service.characteristics) {
          if (characteristic.uuid.str.toLowerCase() ==
              channel.characteristic.toLowerCase()) {
            return characteristic;
          }
        }
      }
    }

    for (final service in services) {
      for (final characteristic in service.characteristics) {
        if (characteristic.properties.write ||
            characteristic.properties.writeWithoutResponse) {
          return characteristic;
        }
      }
    }

    return null;
  }

  @override
  Future<PosPrintOutcome> print(List<int> bytes) async {
    final characteristic = _writeCharacteristic;

    if (characteristic == null || _device == null) {
      _setStatus(PosPrinterStatus.error);

      return const PosPrintOutcome.failure(
        'Imprimante non connectee — reconnectez-la depuis les reglages.',
      );
    }

    _setStatus(PosPrinterStatus.printing);

    try {
      for (var offset = 0; offset < bytes.length; offset += _chunkSize) {
        final end = (offset + _chunkSize).clamp(0, bytes.length);
        final chunk = bytes.sublist(offset, end);

        await characteristic.write(
          chunk,
          withoutResponse: characteristic.properties.writeWithoutResponse,
        );

        // Laisse le buffer de l'imprimante se vider : sans cette pause, les
        // gros tickets arrivent tronques.
        await Future<void>.delayed(const Duration(milliseconds: 20));
      }

      _setStatus(PosPrinterStatus.connected);

      return const PosPrintOutcome.success();
    } catch (error) {
      _setStatus(PosPrinterStatus.error);

      return PosPrintOutcome.failure(
        'Impression interrompue : ${_humanize(error)}',
      );
    }
  }

  @override
  Future<void> disconnect() async {
    try {
      await _device?.disconnect();
    } catch (_) {
      // Deja deconnectee : rien a faire.
    }

    _device = null;
    _writeCharacteristic = null;
    _setStatus(PosPrinterStatus.disconnected);
  }

  @override
  Future<void> dispose() async {
    await disconnect();

    if (!_controller.isClosed) {
      await _controller.close();
    }
  }

  /// Traduit une erreur technique en message comprehensible en caisse.
  String _humanize(Object error) {
    final text = error.toString().toLowerCase();

    if (text.contains('permission') || text.contains('denied')) {
      return 'autorisation Bluetooth refusee.';
    }

    if (text.contains('timeout') || text.contains('timed out')) {
      return 'l\u2019imprimante ne repond pas.';
    }

    if (text.contains('not found') || text.contains('not connected')) {
      return 'imprimante introuvable.';
    }

    if (text.contains('paper')) {
      return 'papier manquant.';
    }

    return 'verifiez l\u2019imprimante et reessayez.';
  }
}
