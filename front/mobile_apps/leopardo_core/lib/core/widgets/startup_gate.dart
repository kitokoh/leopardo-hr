import 'dart:async';

import 'package:flutter/material.dart';

typedef StartupInitializer = Future<void> Function();

class StartupGate extends StatefulWidget {
  const StartupGate({
    required this.initializer,
    required this.child,
    required this.appName,
    this.criticalInitializer,
    this.optionalInitializer,
    this.criticalTimeout = const Duration(seconds: 6),
    this.optionalTimeout = const Duration(seconds: 8),
    super.key,
  });

  final StartupInitializer initializer;
  final StartupInitializer? criticalInitializer;
  final StartupInitializer? optionalInitializer;
  final Widget child;
  final String appName;
  final Duration criticalTimeout;
  final Duration optionalTimeout;

  @override
  State<StartupGate> createState() => _StartupGateState();
}

class _StartupGateState extends State<StartupGate> {
  @override
  void initState() {
    super.initState();
    unawaited(_runStartup());
  }

  Future<void> _runStartup() async {
    if (widget.optionalInitializer != null) {
      unawaited(
        widget.optionalInitializer!().timeout(widget.optionalTimeout).catchError(
          (Object error, StackTrace stackTrace) {
            debugPrint(
              '${widget.appName} optional startup timed out or failed: $error',
            );
            debugPrintStack(stackTrace: stackTrace);
          },
        ),
      );
    }

    final critical = widget.criticalInitializer ?? widget.initializer;
    try {
      await critical().timeout(widget.criticalTimeout);
    } on TimeoutException catch (error, stackTrace) {
      debugPrint('${widget.appName} critical startup timed out: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  @override
  Widget build(BuildContext context) {
    return widget.child;
  }
}
