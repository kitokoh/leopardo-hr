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
    super.key,
  });

  final StartupInitializer initializer;
  final Widget child;
  final String appName;

  @override
  State<StartupGate> createState() => _StartupGateState();
}

class _StartupGateState extends State<StartupGate> {
  late Future<void> _startupFuture;

  @override
  void initState() {
    super.initState();
    _startupFuture = widget.initializer();
  }

  void _retry() {
    setState(() {
      _startupFuture = widget.initializer();
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _startupFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.done &&
            snapshot.error == null) {
          return widget.child;
        }

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
                    child:
                        snapshot.error == null
                            ? _StartupLoading(appName: widget.appName)
                            : _StartupError(
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
      },
    );
  }
}

class _StartupLoading extends StatelessWidget {
  const _StartupLoading({required this.appName});

  final String appName;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: AppColors.rh.withValues(alpha: 0.14),
            borderRadius: BorderRadius.circular(20),
          ),
          child: const Icon(
            Icons.business_center_outlined,
            color: AppColors.rh,
            size: 34,
          ),
        ),
        const SizedBox(height: 18),
        Text(
          appName,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: MobileSurface.text,
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Preparation de votre espace...',
          textAlign: TextAlign.center,
          style: TextStyle(color: MobileSurface.secondary, fontSize: 13),
        ),
        const SizedBox(height: 18),
        const LinearProgressIndicator(minHeight: 3),
      ],
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
