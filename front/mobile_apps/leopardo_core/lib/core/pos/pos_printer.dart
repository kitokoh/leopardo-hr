/// Impression sur imprimante thermique **Bluetooth** (#9264).
///
/// Ce module est la seule partie du noyau qui touche au materiel : il
/// decouvre les peripheriques BLE, se connecte a l'imprimante choisie et lui
/// ecrit les octets ESC/POS produits par `pos_esc_pos.dart`.
///
/// Il est concu pour etre **remplacable** : l'interface [PosPrinterService]
/// permet aux tests (et a une future imprimante USB/reseau) de fournir une
/// implementation sans Bluetooth. Les ecrans ne dependent que de l'interface.
library;

import 'dart:async';

import 'pos_esc_pos.dart';

/// Interface d'une imprimante de caisse.
///
/// Toute implementation doit etre **sans effet de bord a la construction** :
/// la decouverte ne demarre qu'a l'appel de [discover].
abstract class PosPrinterService {
  /// Etat courant de l'imprimante.
  PosPrinterStatus get status;

  /// Imprimante actuellement selectionnee (null si aucune).
  PosPrinterDevice? get selectedPrinter;

  /// Flux d'etat, pour que l'interface se mette a jour sans scrutation.
  Stream<PosPrinterStatus> get statusStream;

  /// Peripheriques deja appaires connus du systeme.
  Future<List<PosPrinterDevice>> knownPrinters();

  /// Decouvre les imprimantes a portee pendant [timeout].
  Future<List<PosPrinterDevice>> discover({
    Duration timeout = const Duration(seconds: 8),
  });

  /// Selectionne (et retient) une imprimante.
  Future<void> select(PosPrinterDevice device);

  /// Se connecte a l'imprimante selectionnee.
  Future<PosPrintOutcome> connect();

  /// Deconnecte proprement.
  Future<void> disconnect();

  /// Ecrit les octets ESC/POS sur l'imprimante.
  ///
  /// Doit echouer avec un message **actionnable** : imprimante absente,
  /// deconnexion en cours d'impression, ou papier manquant (l'imprimante
  /// repond alors une erreur de statut).
  Future<PosPrintOutcome> print(List<int> bytes);

  /// Libere les ressources (appele au dispose de l'ecran).
  Future<void> dispose();
}

/// Implementation **factice** pour les tests et les environnements sans
/// Bluetooth (emulateur, CI).
///
/// Elle enregistre les octets imprimes, ce qui permet de verifier dans un
/// test widget qu'un ticket a bien ete envoye, sans materiel.
class FakePosPrinterService implements PosPrinterService {
  FakePosPrinterService({
    this.printers = const [],
    this.failOnPrint = false,
    this.failMessage = 'Imprimante non connectee.',
  });

  final List<PosPrinterDevice> printers;
  final bool failOnPrint;
  final String failMessage;

  /// Tickets imprimes, dans l'ordre (octets bruts).
  final List<List<int>> printedTickets = [];

  PosPrinterStatus _status = PosPrinterStatus.disconnected;
  PosPrinterDevice? _selected;
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

  @override
  Future<List<PosPrinterDevice>> knownPrinters() async => printers;

  @override
  Future<List<PosPrinterDevice>> discover({
    Duration timeout = const Duration(seconds: 8),
  }) async =>
      printers;

  @override
  Future<void> select(PosPrinterDevice device) async {
    _selected = device;
  }

  @override
  Future<PosPrintOutcome> connect() async {
    if (_selected == null) {
      _setStatus(PosPrinterStatus.error);

      return const PosPrintOutcome.failure('Aucune imprimante selectionnee.');
    }

    _setStatus(PosPrinterStatus.connected);

    return const PosPrintOutcome.success();
  }

  @override
  Future<void> disconnect() async {
    _setStatus(PosPrinterStatus.disconnected);
  }

  @override
  Future<PosPrintOutcome> print(List<int> bytes) async {
    if (failOnPrint) {
      _setStatus(PosPrinterStatus.error);

      return PosPrintOutcome.failure(failMessage);
    }

    if (_status != PosPrinterStatus.connected &&
        _status != PosPrinterStatus.printing) {
      _setStatus(PosPrinterStatus.error);

      return const PosPrintOutcome.failure('Imprimante non connectee.');
    }

    _setStatus(PosPrinterStatus.printing);
    printedTickets.add(List<int>.from(bytes));
    _setStatus(PosPrinterStatus.connected);

    return const PosPrintOutcome.success();
  }

  @override
  Future<void> dispose() async {
    await _controller.close();
  }
}
