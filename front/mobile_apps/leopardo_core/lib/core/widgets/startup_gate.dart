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
  bool _showStartupGuard = true;
  String? _startupWarning;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      unawaited(_runStartup());
    });
  }

  Future<void> _runStartup() async {
    final critical = widget.criticalInitializer ?? widget.initializer;
    try {
      await critical().timeout(widget.criticalTimeout);
    } on TimeoutException catch (error, stackTrace) {
      debugPrint('${widget.appName} critical startup timed out: $error');
      debugPrintStack(stackTrace: stackTrace);
      if (mounted) {
        setState(() {
          _startupWarning = 'Demarrage en mode securise';
        });
      }
      return;
    } catch (error, stackTrace) {
      debugPrint('${widget.appName} critical startup failed: $error');
      debugPrintStack(stackTrace: stackTrace);
      if (mounted) {
        setState(() {
          _startupWarning = 'Initialisation partielle';
        });
      }
      return;
    }

    if (mounted) {
      setState(() {
        _showStartupGuard = false;
        _startupWarning = null;
      });
    }

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
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      textDirection: TextDirection.ltr,
      children: [
        widget.child,
        if (_showStartupGuard)
          Positioned.fill(
            child: Directionality(
              textDirection: TextDirection.ltr,
              child: Material(
                color: const Color(0xFF0B1120),
                child: SafeArea(
                  child: Center(
                    child: Padding(
                      padding: const EdgeInsets.all(28),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: const BoxDecoration(
                              color: Color(0xFF10B981),
                              shape: BoxShape.circle,
                            ),
                            alignment: Alignment.center,
                            child: const Text(
                              'L',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 28,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),
                          Text(
                            widget.appName,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Color(0xFFE2EAF6),
                              fontSize: 18,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _startupWarning ?? 'Ouverture de votre espace...',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Color(0xFF8EA9C8),
                              fontSize: 13,
                              height: 1.35,
                            ),
                          ),
                          if (_startupWarning != null) ...[
                            const SizedBox(height: 18),
                            OutlinedButton(
                              onPressed:
                                  () => setState(() {
                                    _showStartupGuard = false;
                                  }),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: const Color(0xFFE2EAF6),
                                side: const BorderSide(
                                  color: Color(0xFF1A2B44),
                                ),
                              ),
                              child: const Text('Continuer'),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}
