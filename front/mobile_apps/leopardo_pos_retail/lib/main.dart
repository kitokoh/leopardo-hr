import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/retail_api_client.dart';
import 'core/offline/retail_offline_queue.dart';
import 'core/storage/retail_local_store.dart';
import 'core/theme/retail_theme.dart';
import 'features/pos/open_session_screen.dart';

/// Point d'entree de l'application tablette POS Retail (#9215).
///
/// Le bootstrap ouvre le stockage local (Hive) et la file hors ligne AVANT
/// d'afficher la caisse : une caisse qui demarre sans son cache local
/// afficherait un catalogue vide et ferait croire a une panne.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final store = await RetailLocalStore.open();
  final queue = await RetailOfflineQueue.open();

  runApp(
    ProviderScope(
      overrides: [
        retailLocalStoreProvider.overrideWithValue(store),
        retailOfflineQueueProvider.overrideWithValue(queue),
      ],
      child: const RetailPosApp(),
    ),
  );
}

/// Application tablette POS Retail.
class RetailPosApp extends StatelessWidget {
  const RetailPosApp({super.key, this.locationId = 1});

  /// Emplacement (boutique) servi par cette tablette (injecte au deploiement).
  final int locationId;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Leopardo POS Retail',
      debugShowCheckedModeBanner: false,
      theme: RetailTheme.light(),
      darkTheme: RetailTheme.dark(),
      home: OpenSessionScreen(locationId: locationId),
    );
  }
}
