import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/pos_theme.dart';
import 'pos_controller.dart';
import 'pos_screen.dart';

/// Écran d'ouverture de caisse (#9214).
///
/// Le restaurateur (ou le serveur de service) déclare le **fonds de caisse**
/// avant la première vente. C'est ce montant qui sert de base à l'attendu de
/// clôture — sans lui, l'écart de caisse est incalculable.
///
/// L'ouverture exige le réseau : une session porte un identifiant serveur.
/// Hors ligne, l'écran le dit explicitement au lieu d'échouer en silence.
class OpenSessionScreen extends ConsumerStatefulWidget {
  const OpenSessionScreen({super.key, required this.branchId});

  final int branchId;

  @override
  ConsumerState<OpenSessionScreen> createState() => _OpenSessionScreenState();
}

class _OpenSessionScreenState extends ConsumerState<OpenSessionScreen> {
  final _controller = TextEditingController(text: '0');

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(posControllerProvider);
    final controller = ref.read(posControllerProvider.notifier);

    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.point_of_sale, size: 56),
                  const SizedBox(height: 16),
                  const Text(
                    'Ouvrir la caisse',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Déclarez le fonds de caisse présent dans le tiroir. '
                    'Il servira de base au calcul de l\'écart de clôture.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 24),
                  TextField(
                    controller: _controller,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
                    decoration: const InputDecoration(
                      labelText: 'Fonds de caisse (unités mineures)',
                      helperText: 'Exemple : 5000 pour 50,00',
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: state.loading
                        ? null
                        : () async {
                            final amount = int.tryParse(_controller.text.trim()) ?? 0;
                            final ok = await controller.openSession(openingCashMinor: amount);

                            if (!context.mounted) {
                              return;
                            }

                            if (!ok) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(state.error ?? 'Ouverture impossible.')),
                              );
                            }
                          },
                    child: state.loading
                        ? const SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Ouvrir la caisse'),
                  ),
                  if (state.error != null) ...[
                    const SizedBox(height: 16),
                    Text(
                      state.error!,
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ],
                  const SizedBox(height: 12),
                  const Text(
                    'L\'ouverture de caisse nécessite une connexion réseau.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13, color: Colors.grey),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Plan de salle : état des tables (consultatif en v1).
class FloorPlanScreen extends ConsumerWidget {
  const FloorPlanScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(posControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Plan de salle')),
      body: state.tables.isEmpty
          ? const Center(child: Text('Aucune table configurée.'))
          : GridView.builder(
              padding: const EdgeInsets.all(20),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                maxCrossAxisExtent: 200,
                mainAxisSpacing: 16,
                crossAxisSpacing: 16,
                childAspectRatio: 1.1,
              ),
              itemCount: state.tables.length,
              itemBuilder: (context, index) {
                final table = state.tables[index];
                final scheme = Theme.of(context).colorScheme;

                return Card(
                  color: table.occupied ? scheme.errorContainer : null,
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          table.label,
                          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 6),
                        Text('${table.seats} places'),
                        const SizedBox(height: 6),
                        Text(
                          table.occupied ? 'Occupée' : 'Libre',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: table.occupied ? scheme.error : scheme.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}

/// Coquille de navigation tablette (paysage, deux destinations).
class PosShell extends ConsumerStatefulWidget {
  const PosShell({super.key, required this.branchId});

  final int branchId;

  @override
  ConsumerState<PosShell> createState() => _PosShellState();
}

class _PosShellState extends ConsumerState<PosShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(posControllerProvider.notifier).bootstrap(branchId: widget.branchId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(posControllerProvider);

    if (!state.sessionOpen) {
      return OpenSessionScreen(branchId: widget.branchId);
    }

    final pages = [
      const PosScreen(),
      const FloorPlanScreen(),
    ];

    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            selectedIndex: _index,
            onDestinationSelected: (index) => setState(() => _index = index),
            labelType: NavigationRailLabelType.all,
            minWidth: 88,
            destinations: const [
              NavigationRailDestination(
                icon: Icon(Icons.point_of_sale),
                label: Text('Caisse'),
              ),
              NavigationRailDestination(
                icon: Icon(Icons.table_restaurant),
                label: Text('Salle'),
              ),
            ],
          ),
          const VerticalDivider(width: 1),
          Expanded(child: pages[_index]),
        ],
      ),
    );
  }
}

/// Utilitaire de thème exposé pour les tests widget.
ThemeData posLightTheme() => PosTheme.light();
