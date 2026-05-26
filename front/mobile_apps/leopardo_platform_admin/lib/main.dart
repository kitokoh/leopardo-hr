import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'src/platform_admin_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();
  await Hive.openBox('offlineCache');
  await initializeDateFormatting('fr_FR', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('tr_TR', null);
  await initializeDateFormatting('en_US', null);

  runApp(const ProviderScope(child: PlatformAdminApp()));
}
