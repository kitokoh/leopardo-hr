import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/retail_theme.dart';
import 'retail_pos_controller.dart';
import 'retail_pos_screen.dart';

/// Ecran d'ouverture de caisse (#9215).
///
/// Le vendeur saisit le fonds de caisse du jour. L'ouverture exige le reseau
/// (la session porte l'attendu de cloture cote serveur) : hors ligne, l'ecran
/// le dit clairement au lieu d'echouer en silence.
class OpenSessionScreen extends ConsumerStatefulWidget {
  const OpenSessionScreen({super.key, required this.locationId});

  final int locationId;

  @override
  ConsumerState<OpenSessionScreen> createState() => _OpenSessionScreenState();
}

class _OpenSessionScreenState extends ConsumerState<OpenSessionScreen> {
  final _controller = TextEditingController(text: '0');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(retailPosControllerProvider.notifier).bootstrap(locationId: widget.locationId);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(retailPosControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Ouverture de caisse')),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Fonds de caisse',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Text(
                  'Montant en especes present dans le tiroir a l\'ouverture.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: _controller,
                  keyboardType: TextInputType.number,
                  style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
                  decoration: const InputDecoration(
                    labelText: 'Fonds de caisse (unites mineures)',
                    prefixIcon: Icon(Icons.payments_outlined),
                  ),
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: state.loading ? null : _open,
                  icon: const Icon(Icons.lock_open),
                  label: Text(state.loading ? 'Ouverture...' : 'Ouvrir la caisse'),
                ),
                if (state.error != null) ...[
                  const SizedBox(height: 16),
                  _ErrorBanner(message: state.error!),
                ],
                if (state.offline) ...[
                  const SizedBox(height: 16),
                  const _OfflineBanner(),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _open() async {
    final amount = int.tryParse(_controller.text.trim()) ?? 0;

    final opened = await ref
        .read(retailPosControllerProvider.notifier)
        .openSession(openingCashMinor: amount < 0 ? 0 : amount);

    if (!opened || !mounted) {
      return;
    }

    await Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => RetailPosScreen(locationId: widget.locationId),
      ),
    );
  }
}

/// Bandeau d'erreur lisible a distance (tablette posee sur le comptoir).
class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: scheme.errorContainer,
        borderRadius: BorderRadius.circular(RetailTheme.cardRadius),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline, color: scheme.onErrorContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: scheme.onErrorContainer, fontSize: 16),
            ),
          ),
        ],
      ),
    );
  }
}

/// Bandeau « hors ligne » : l'interface ne ment jamais sur l'etat du reseau.
class _OfflineBanner extends StatelessWidget {
  const _OfflineBanner();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: scheme.tertiaryContainer,
        borderRadius: BorderRadius.circular(RetailTheme.cardRadius),
      ),
      child: Row(
        children: [
          Icon(Icons.cloud_off, color: scheme.onTertiaryContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Hors ligne : catalogue en cache. L\'ouverture de caisse exige le reseau.',
              style: TextStyle(color: scheme.onTertiaryContainer, fontSize: 16),
            ),
          ),
        ],
      ),
    );
  }
}
