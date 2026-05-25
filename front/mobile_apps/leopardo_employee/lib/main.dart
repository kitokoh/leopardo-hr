import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'app.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await GoogleSignIn.instance.initialize();
  await Hive.initFlutter();
  await Hive.openBox('offlineCache');
  await initializeDateFormatting('fr_FR', null);
  await initializeDateFormatting('fr_CA', null);
  await initializeDateFormatting('fr_BE', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('ar_SA', null);
  await initializeDateFormatting('ar_MA', null);
  await initializeDateFormatting('tr', null);
  await initializeDateFormatting('tr_TR', null);
  await initializeDateFormatting('en', null);
  await initializeDateFormatting('en_US', null);
  await initializeDateFormatting('en_GB', null);
  runApp(const ProviderScope(child: LeopardoApp()));
}
