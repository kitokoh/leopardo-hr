import 'dart:async';

import 'package:flutter/material.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_theme.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';

typedef StartupInitializer = Future<void> Function();

class StartupGate extends StatefulWidget {
  const StartupGate({
    required this.initializer,
    required this.child,
    required this.appName,
    /// [criticalInitializer] : ops sans timeout (Hive, locales).
    /// Conservé pour compatibilité : si [criticalInitializer] est null,
    /// [initializer] est utilisé sans timeout.
    this.criticalInitializer,
    /// [optionalInitializer] : ops optionnelles (Google Sign-In, etc.).
    /// Lancées en parallèle, silenciées après [optionalTimeout].
    this.optionalInitializer,
    this.optionalTimeout = const Duration(seconds: 8),
    super.key,
  });

  final StartupInitializer initializer;
  final StartupInitializer? criticalInitializer;
  final StartupInitializer? optionalInitializer;
  final Widget child;
  final String appName;
  final Duration optionalTimeout;

  @override
  State<StartupGate> createState() => _StartupGateState();
}

class _StartupGateState extends State<StartupGate> {
  late Future<void> _startupFuture;

  @override
  void initState() {
    super.initState();
    _startupFuture = _runStartup();
  }

  void _retry() {
    setState(() {
      _startupFuture = _runStartup();
    });
  }

  Future<void> _runStartup() async {
    // Lance les ops optionnelles en parallèle sans bloquer le chemin critique.
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

    // Exécute les ops critiques SANS timeout.
    // Si [criticalInitializer] est fourni, on l'utilise ; sinon fallback
    // sur [initializer] (comportement compatible avec l'ancienne API).
    final critical = widget.criticalInitializer ?? widget.initializer;
    await critical();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _startupFuture,
      builder: (context, snapshot) {
        // Afficher l'app dès que les initialisations critiques sont terminées.
        if (snapshot.connectionState == ConnectionState.done &&
            snapshot.error == null) {
          return widget.child;
        }

        // En cas d'erreur critique, afficher un panneau de récupération.
        if (snapshot.hasError) {
          return MaterialApp(
            title: widget.appName,
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            themeMode: ThemeMode.dark,
            home: Scaffold(
              backgroundColor: MobileSurface.background,
              body: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: _StartupError(
                        appName: widget.appName,
                        error: snapshot.error!,
                        onRetry: _retry,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        }

        // Pendant le chargement : écran minimal, fond seul, sans texte superflu.
        // Sur un device normal le bootstrap dure < 300ms et cet écran est
        // presque invisible.
        return MaterialApp(
          title: widget.appName,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.lightTheme,
          darkTheme: AppTheme.darkTheme,
          themeMode: ThemeMode.dark,
          home: const Scaffold(
            backgroundColor: MobileSurface.background,
          ),
        );
      },
    );
  }
}

class _StartupError extends StatelessWidget {
  const _StartupError({
    required this.appName,
    required this.error,
    required this.onRetry,
  });

  final String appName;
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: MobileSurface.cardDecoration(),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.warning_amber_rounded, color: AppColors.warning),
          const SizedBox(height: 12),
          Text(
            '$appName ne peut pas demarrer',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: MobileSurface.text,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Les donnees locales ont peut-etre besoin d etre reinitialisees. Reessayez, puis contactez le support si le probleme continue.',
            textAlign: TextAlign.center,
            style: TextStyle(color: MobileSurface.secondary, fontSize: 13),
          ),
          const SizedBox(height: 10),
          Text(
            error.toString(),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(color: MobileSurface.disabled, fontSize: 11),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Reessayer'),
          ),
        ],
      ),
    );
  }
}
