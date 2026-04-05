import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'app.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();
  await Hive.openBox('offlineCache');
  await initializeDateFormatting('fr_FR', null);
  runApp(
    const ProviderScope(
      child: LeopardoApp(),
    ),
  );
}
