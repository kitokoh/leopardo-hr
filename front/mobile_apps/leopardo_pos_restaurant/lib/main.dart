import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/pos_api_client.dart';
import 'core/offline/pos_offline_queue.dart';
import 'core/storage/pos_local_store.dart';
import 'core/theme/pos_theme.dart';
import 'features/pos/open_session_screen.dart';

/// Point d'entrée de l'application tablette POS Restaurant (#9214).
///
/// Le bootstrap ouvre le stockage local (Hive) et la file hors ligne AVANT
/// d'afficher la caisse : une caisse qui démarre sans son cache local
/// afficherait un menu vide et ferait croire à une panne.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final store = await PosLocalStore.open();
  final queue = await PosOfflineQueue.open();

  runApp(
    ProviderScope(
      overrides: [
        posLocalStoreProvider.overrideWithValue(store),
        posOfflineQueueProvider.overrideWithValue(queue),
      ],
      child: const PosRestaurantApp(),
    ),
  );
}

/// Application tablette POS Restaurant.
class PosRestaurantApp extends StatelessWidget {
  const PosRestaurantApp({super.key, this.branchId = 1});

  /// Succursale servie par cette tablette (injectée au déploiement).
  final int branchId;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Leopardo POS Restaurant',
      debugShowCheckedModeBanner: false,
      theme: PosTheme.light(),
      darkTheme: PosTheme.dark(),
      home: PosShell(branchId: branchId),
    );
  }
}
