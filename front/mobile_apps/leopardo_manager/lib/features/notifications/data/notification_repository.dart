import 'package:leopardo_core/features/notifications/data/notification_repository.dart';

export 'package:leopardo_core/features/notifications/data/notification_repository.dart'
    show NotificationRepository;

/// Leopardo manager — repository notifications partagé (leopardo_core, #5279).
/// Contrat API : /notifications, /notifications/read-all, /notifications/{id}/read
/// (DELETE pour la suppression, requestWithRetry pour le rejeu).
