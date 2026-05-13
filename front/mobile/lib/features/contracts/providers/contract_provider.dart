import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/contract.dart';

final contractsProvider = FutureProvider<List<Contract>>((ref) async {
  final repo = ref.watch(contractRepositoryProvider);
  return await repo.getMyContracts();
});
