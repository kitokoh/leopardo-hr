import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:leopardo_core/core/api/api_client.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await _ensureFirebaseInitialized();
  } catch (e) {
    debugPrint('Firebase background init skipped: $e');
  }
}

class PushNotificationService {
  static final PushNotificationService _instance =
      PushNotificationService._internal();
  factory PushNotificationService() => _instance;
  PushNotificationService._internal();

  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  static const Duration _firebaseStartupTimeout = Duration(seconds: 6);
  static const Duration _fcmOperationTimeout = Duration(seconds: 8);

  FirebaseMessaging? _messaging;
  String? _deviceToken;
  ApiClient? _apiClient;
  StreamSubscription<String>? _tokenRefreshSubscription;
  bool _initialized = false;

  String? get deviceToken => _deviceToken;

  Function(RemoteMessage)? onMessageReceived;
  Function(RemoteMessage)? onMessageOpenedApp;

  Future<void> initialize({ApiClient? apiClient}) async {
    _apiClient = apiClient ?? _apiClient;

    if (_initialized) {
      await _syncTokenWithBackend(_deviceToken);
      return;
    }

    try {
      await _ensureFirebaseInitialized().timeout(_firebaseStartupTimeout);
    } catch (e) {
      debugPrint('Firebase init skipped: $e');
      return;
    }

    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    try {
      final fcm = _messaging ??= FirebaseMessaging.instance;
      final settings = await fcm
          .requestPermission(
            alert: true,
            badge: true,
            sound: true,
            provisional: false,
          )
          .timeout(_fcmOperationTimeout);

      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('Push notifications permission denied');
        return;
      }

      await _initLocalNotifications().timeout(_fcmOperationTimeout);
      await _getToken(fcm);
      _listenToMessages();
      _initialized = true;
    } catch (e) {
      debugPrint('Push notification init skipped: $e');
      return;
    }
  }

  Future<void> _initLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@drawable/ic_notification');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    const initSettings = InitializationSettings(
      android: androidInit,
      iOS: iosInit,
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (response) {
        debugPrint('Local notification tapped: ${response.payload}');
      },
    );

    if (Platform.isAndroid) {
      await _localNotifications
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >()
          ?.createNotificationChannel(
            const AndroidNotificationChannel(
              'leopardo_hr_default',
              'Notifications',
              description: 'Notifications Leopardo HR',
              importance: Importance.high,
            ),
          );
    }
  }

  Future<void> _getToken(FirebaseMessaging fcm) async {
    try {
      _deviceToken = await fcm.getToken().timeout(_fcmOperationTimeout);
      debugPrint('FCM Token: $_deviceToken');
      await _syncTokenWithBackend(_deviceToken);

      await _tokenRefreshSubscription?.cancel();
      _tokenRefreshSubscription = fcm.onTokenRefresh.listen((newToken) {
        _deviceToken = newToken;
        debugPrint('FCM Token refreshed: $newToken');
        unawaited(_syncTokenWithBackend(newToken));
      });
    } catch (e) {
      debugPrint('Failed to get FCM token: $e');
    }
  }

  Future<void> _syncTokenWithBackend(String? token) async {
    final apiClient = _apiClient;
    if (token == null || apiClient == null) return;

    try {
      final platform =
          Platform.isIOS ? 'ios' : (Platform.isAndroid ? 'android' : 'web');
      await apiClient.requestWithRetry(
        '/device-tokens',
        method: 'POST',
        data: {
          'token': token,
          'platform': platform,
          'device_name': Platform.operatingSystemVersion,
        },
      );
      debugPrint('FCM Token synced with backend');
    } catch (e) {
      debugPrint('Failed to sync FCM token with backend: $e');
    }
  }

  Future<void> unregisterCurrentToken({ApiClient? apiClient}) async {
    _apiClient = apiClient ?? _apiClient;
    final client = _apiClient;
    final token = _deviceToken;

    if (client == null || token == null || token.isEmpty) return;

    try {
      await client.requestWithRetry(
        '/device-tokens',
        method: 'DELETE',
        data: {'token': token},
      );
      debugPrint('FCM Token unregistered from backend');
    } catch (e) {
      debugPrint('Failed to unregister FCM token: $e');
    }
  }

  void _listenToMessages() {
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      _showLocalNotification(message);
      onMessageReceived?.call(message);
    });

    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      onMessageOpenedApp?.call(message);
    });
  }

  Future<void> _showLocalNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    const androidDetails = AndroidNotificationDetails(
      'leopardo_hr_default',
      'Notifications',
      channelDescription: 'Notifications Leopardo HR',
      importance: Importance.high,
      priority: Priority.high,
      showWhen: true,
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    const details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      notification.hashCode,
      notification.title ?? 'Leopardo HR',
      notification.body ?? '',
      details,
      payload: message.data.toString(),
    );
  }

  Future<void> subscribeToTopic(String topic) async {
    try {
      await _ensureFirebaseInitialized().timeout(_firebaseStartupTimeout);
      final fcm = _messaging ??= FirebaseMessaging.instance;
      await fcm.subscribeToTopic(topic).timeout(_fcmOperationTimeout);
    } catch (e) {
      debugPrint('Subscribe to FCM topic skipped ($topic): $e');
    }
  }

  Future<void> unsubscribeFromTopic(String topic) async {
    try {
      await _ensureFirebaseInitialized().timeout(_firebaseStartupTimeout);
      final fcm = _messaging ??= FirebaseMessaging.instance;
      await fcm.unsubscribeFromTopic(topic).timeout(_fcmOperationTimeout);
    } catch (e) {
      debugPrint('Unsubscribe from FCM topic skipped ($topic): $e');
    }
  }
}

Future<void> _ensureFirebaseInitialized() async {
  if (Firebase.apps.isNotEmpty) return;
  await Firebase.initializeApp();
}
