/// Ecran de **selection d'imprimante** thermique (#9264), partage.
///
/// Le vendeur decouvre les imprimantes a portee, en choisit une, la teste.
/// Le choix est persiste par l'app (identifiant du peripherique) pour que la
/// caisse se reconnecte seule au demarrage suivant.
library;

import 'package:flutter/material.dart';

import 'pos_esc_pos.dart';
import 'pos_printer.dart';

/// Ecran de decouverte et de selection d'une imprimante.
class PosPrinterPickerScreen extends StatefulWidget {
  const PosPrinterPickerScreen({
    super.key,
    required this.service,
    this.onSelected,
  });

  /// Service d'impression (BLE en production, factice en test).
  final PosPrinterService service;

  /// Appele apres une selection + connexion reussie.
  final Future<void> Function(PosPrinterDevice device)? onSelected;

  @override
  State<PosPrinterPickerScreen> createState() => _PosPrinterPickerScreenState();
}

class _PosPrinterPickerScreenState extends State<PosPrinterPickerScreen> {
  List<PosPrinterDevice> _devices = const [];
  bool _scanning = false;
  String? _error;
  String? _connectingId;

  @override
  void initState() {
    super.initState();
    _scan();
  }

  Future<void> _scan() async {
    setState(() {
      _scanning = true;
      _error = null;
    });

    final known = await widget.service.knownPrinters();
    final discovered = await widget.service.discover();

    if (!mounted) {
      return;
    }

    // Fusionne les appareils appaires et ceux decouverts, sans doublon.
    final merged = <String, PosPrinterDevice>{
      for (final device in known) device.id: device,
      for (final device in discovered) device.id: device,
    };

    setState(() {
      _scanning = false;
      _devices = merged.values.toList();
      _error = _devices.isEmpty
          ? 'Aucune imprimante detectee. Verifiez qu\u2019elle est allumee et a portee.'
          : null;
    });
  }

  Future<void> _select(PosPrinterDevice device) async {
    setState(() {
      _connectingId = device.id;
      _error = null;
    });

    await widget.service.select(device);
    final outcome = await widget.service.connect();

    if (!mounted) {
      return;
    }

    setState(() => _connectingId = null);

    if (!outcome.success) {
      setState(() => _error = outcome.message);

      return;
    }

    await widget.onSelected?.call(device);

    if (!mounted) {
      return;
    }

    Navigator.of(context).pop(device);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Imprimante'),
        actions: [
          IconButton(
            key: const Key('pos-printer-rescan'),
            icon: const Icon(Icons.refresh),
            tooltip: 'Rechercher a nouveau',
            onPressed: _scanning ? null : _scan,
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (_scanning)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Column(
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: 12),
                    Text('Recherche des imprimantes...',
                        style: TextStyle(fontSize: 16)),
                  ],
                ),
              ),
            if (_error != null)
              Container(
                key: const Key('pos-printer-error'),
                width: double.infinity,
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: scheme.errorContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Icon(Icons.error_outline, color: scheme.onErrorContainer),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        _error!,
                        style: TextStyle(
                            fontSize: 16, color: scheme.onErrorContainer),
                      ),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: _devices.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final device = _devices[index];
                  final connecting = _connectingId == device.id;

                  return Card(
                    child: ListTile(
                      key: Key('pos-printer-${device.id}'),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      leading:
                          Icon(Icons.print_outlined, color: scheme.primary),
                      title: Text(
                        device.name,
                        style: const TextStyle(
                            fontSize: 17, fontWeight: FontWeight.w600),
                      ),
                      subtitle:
                          Text(device.id, style: const TextStyle(fontSize: 13)),
                      trailing: connecting
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.chevron_right),
                      onTap: connecting ? null : () => _select(device),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Bouton d'impression reutilisable, avec etat et message d'erreur.
///
/// Il encapsule les trois cas que l'operateur rencontre : pas d'imprimante
/// selectionnee, imprimante deconnectee, et impression reussie. Le message
/// est affiche dans un SnackBar — pas de dialogue bloquant au milieu d'un
/// service.
class PosPrintButton extends StatefulWidget {
  const PosPrintButton({
    super.key,
    required this.service,
    required this.buildBytes,
    this.label = 'Imprimer le ticket',
    this.icon = Icons.print,
    this.compact = false,
  });

  final PosPrinterService service;

  /// Construit les octets ESC/POS au moment de l'impression (le ticket doit
  /// refleter l'etat courant, pas celui de l'ouverture de l'ecran).
  final List<int> Function() buildBytes;

  final String label;
  final IconData icon;
  final bool compact;

  @override
  State<PosPrintButton> createState() => _PosPrintButtonState();
}

class _PosPrintButtonState extends State<PosPrintButton> {
  bool _busy = false;

  Future<void> _print() async {
    setState(() => _busy = true);

    var outcome = await widget.service.print(widget.buildBytes());

    // Une imprimante qui a perdu la connexion (veille, sortie de portee) est
    // reprise une fois automatiquement : c'est le cas le plus frequent en
    // caisse, et demander au vendeur de rouvrir les reglages serait absurde.
    if (!outcome.success && widget.service.selectedPrinter != null) {
      final reconnect = await widget.service.connect();

      if (reconnect.success) {
        outcome = await widget.service.print(widget.buildBytes());
      }
    }

    if (!mounted) {
      return;
    }

    setState(() => _busy = false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(outcome.message),
        backgroundColor:
            outcome.success ? null : Theme.of(context).colorScheme.error,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final icon = _busy
        ? const SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : Icon(widget.icon);

    if (widget.compact) {
      return IconButton(
        key: const Key('pos-print-button'),
        icon: icon,
        tooltip: widget.label,
        onPressed: _busy ? null : _print,
      );
    }

    return SizedBox(
      height: 56,
      child: OutlinedButton.icon(
        key: const Key('pos-print-button'),
        onPressed: _busy ? null : _print,
        icon: icon,
        label: Text(widget.label, style: const TextStyle(fontSize: 16)),
      ),
    );
  }
}
