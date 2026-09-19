// Leopardo employee — passerelle vers l'implémentation partagée de
// leopardo_core (dé-duplication core<->apps, issue #7652). Le fichier local
// était byte-identique à la copie core ; l'app ré-exporte le package partagé
// — aucune duplication locale (pattern #5279, garde
// dev-hub/tools/check-mobile-core-duplication.py).
export 'package:leopardo_core/features/notifications/data/notification_repository.dart';
