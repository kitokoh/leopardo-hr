import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/cabinet_document.dart';
import 'package:leopardo_core/models/cabinet_folder.dart';

final cabinetFoldersProvider = FutureProvider.family<List<CabinetFolder>, int?>(
  (ref, parentId) async {
    final repo = ref.watch(cabinetRepositoryProvider);
    return await repo.getFolders(parentId: parentId);
  },
);

final cabinetDocumentsProvider =
    FutureProvider.family<List<CabinetDocument>, int?>((ref, folderId) async {
      final repo = ref.watch(cabinetRepositoryProvider);
      return await repo.getDocuments(folderId: folderId);
    });

final cabinetStatsProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final repo = ref.watch(cabinetRepositoryProvider);
  return await repo.getStats();
});
