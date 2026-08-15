import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Indirection to break the top-level provider dependency cycle
/// `apiClientProvider ↔ authProvider ↔ authRepositoryProvider`
/// (Dart analyzer `top_level_cycle`, issue #3153).
///
/// `ApiClient` needs a callback when a 401 occurs (session revoked → log the
/// user out, issue #2737), and `AuthNotifier` is the one that can do it.
/// Instead of `apiClientProvider` referencing `authProvider` in its
/// initializer (which creates a static cycle), the notifier *registers* its
/// handler on this holder at runtime; `ApiClient` only knows the holder.
class SessionExpiredHandler {
  void Function()? _callback;

  /// Invoked by [ApiClient] when an API call returns 401.
  void call() => _callback?.call();

  /// The [AuthNotifier] instance registers its `handleSessionExpired` here.
  set callback(void Function()? cb) => _callback = cb;
}

final sessionExpiredHandlerProvider = Provider<SessionExpiredHandler>(
  (ref) => SessionExpiredHandler(),
);
