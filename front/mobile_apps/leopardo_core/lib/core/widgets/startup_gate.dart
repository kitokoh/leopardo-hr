import 'dart:async';

import 'package:flutter/material.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';

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
  static const Duration _degradedAutoContinueDelay = Duration(
    milliseconds: 1200,
  );

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
    String? warning;

    try {
      await critical().timeout(widget.criticalTimeout);
    } on TimeoutException catch (error, stackTrace) {
      debugPrint('${widget.appName} critical startup timed out: $error');
      debugPrintStack(stackTrace: stackTrace);
      warning = 'Demarrage en mode securise';
    } catch (error, stackTrace) {
      debugPrint('${widget.appName} critical startup failed: $error');
      debugPrintStack(stackTrace: stackTrace);
      warning = 'Initialisation partielle';
    }

    if (mounted) {
      if (warning == null) {
        setState(() {
          _showStartupGuard = false;
          _startupWarning = null;
        });
      } else {
        setState(() {
          _startupWarning = warning;
        });
        unawaited(_autoContinueAfterWarning());
      }
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

  Future<void> _autoContinueAfterWarning() async {
    await Future<void>.delayed(_degradedAutoContinueDelay);
    if (!mounted) return;
    setState(() {
      _showStartupGuard = false;
    });
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
                color: const Color(0xFF0B1326), // Stitch-bg
                child: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Color(0xFF0B1326),
                        Color(0xFF131B2E), // surface_container_low
                      ],
                    ),
                  ),
                  child: SafeArea(
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Glowing Logo
                            Container(
                              width: 80,
                              height: 80,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: const Color(
                                  0xFF10B981,
                                ).withValues(alpha: 0.1),
                                border: Border.all(
                                  color: const Color(
                                    0xFF10B981,
                                  ).withValues(alpha: 0.3),
                                  width: 1.5,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(
                                      0xFF10B981,
                                    ).withValues(alpha: 0.3),
                                    blurRadius: 30,
                                    spreadRadius: 2,
                                  ),
                                ],
                              ),
                              alignment: Alignment.center,
                              child: const Text(
                                'L',
                                style: TextStyle(
                                  color: Color(0xFF4EDEA3),
                                  fontSize: 40,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            const SizedBox(height: 32),
                            Text(
                              widget.appName,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: Color(0xFFDAE2FD), // on-surface
                                fontSize: 22,
                                fontWeight: FontWeight.w700,
                                letterSpacing: -0.5,
                              ),
                            ),
                            const SizedBox(height: 12),
                            if (_startupWarning == null)
                              const SizedBox(
                                width: 24,
                                height: 24,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                    Color(0xFF10B981),
                                  ),
                                ),
                              )
                            else
                              Text(
                                _startupWarning!,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: Color(
                                    0xFFBBCABF,
                                  ), // on-surface-variant
                                  fontSize: 14,
                                  height: 1.4,
                                ),
                              ),
                            if (_startupWarning != null) ...[
                              const SizedBox(height: 24),
                              OutlinedButton(
                                onPressed:
                                    () => setState(() {
                                      _showStartupGuard = false;
                                    }),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFFDAE2FD),
                                  side: BorderSide(
                                    color: const Color(
                                      0xFFDAE2FD,
                                    ).withValues(alpha: 0.2),
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 24,
                                    vertical: 12,
                                  ),
                                ),
                                child: const Text(
                                  'Continuer',
                                  style: TextStyle(fontWeight: FontWeight.w600),
                                ),
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
          ),
      ],
    );
  }
}
