import 'dart:async';

import 'package:flutter/material.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  
  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };

  runApp(
    StartupGate(
      appName: 'Leopardo Marketing',
      initializer: _bootstrap,
      criticalInitializer: () async {},
      optionalInitializer: () async {},
      child: const LeopardoMarketingApp(),
    ),
  );
}

Future<void> _bootstrap() async {
  // Initialization logic for marketing app
}

class LeopardoMarketingApp extends StatelessWidget {
  const LeopardoMarketingApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Leopardo Marketing',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
        useMaterial3: true,
      ),
      home: const Scaffold(
        body: Center(
          child: Text('Leopardo Marketing Scaffold'),
        ),
      ),
    );
  }
}
