import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/cabinet_document.dart';
import 'package:leopardo_rh/models/cabinet_folder.dart';

class CabinetRepository {
  final ApiClient apiClient;

  CabinetRepository(this.apiClient);

  // ── Folders ─────────────────────────────────────────────────────────────

  Future<List<CabinetFolder>> getFolders({int? parentId}) async {
    final params = <String, dynamic>{};
    if (parentId != null) params['parent_id'] = parentId;
    final response =
        await apiClient.dio.get('/cabinet/folders', queryParameters: params);
    final items = response.data['data'] as List;
    return items
        .map((e) => CabinetFolder.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CabinetFolder> createFolder({
    required String name,
    int? parentId,
    String? color,
    String? icon,
  }) async {
    final response = await apiClient.dio.post('/cabinet/folders', data: {
      'name': name,
      if (parentId != null) 'parent_id': parentId,
      if (color != null) 'color': color,
      if (icon != null) 'icon': icon,
    });
    return CabinetFolder.fromJson(response.data['data']);
  }

  Future<CabinetFolder> updateFolder(int id, {String? name, int? parentId}) async {
    final data = <String, dynamic>{};
    if (name != null) data['name'] = name;
    if (parentId != null) data['parent_id'] = parentId;
    final response =
        await apiClient.dio.put('/cabinet/folders/$id', data: data);
    return CabinetFolder.fromJson(response.data['data']);
  }

  Future<void> deleteFolder(int id) async {
    await apiClient.dio.delete('/cabinet/folders/$id');
  }

  // ── Documents ───────────────────────────────────────────────────────────

  Future<List<CabinetDocument>> getDocuments({int? folderId}) async {
    final params = <String, dynamic>{};
    if (folderId != null) params['folder_id'] = folderId;
    final response =
        await apiClient.dio.get('/cabinet/documents', queryParameters: params);
    final items = response.data['data'] as List;
    return items
        .map((e) => CabinetDocument.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CabinetDocument> uploadDocument({
    required String filePath,
    required String fileName,
    int? folderId,
    String? name,
    String? notes,
  }) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: fileName),
      if (folderId != null) 'folder_id': folderId,
      if (name != null) 'name': name,
      if (notes != null) 'notes': notes,
    });
    final response =
        await apiClient.dio.post('/cabinet/documents', data: formData);
    return CabinetDocument.fromJson(response.data['data']);
  }

  Future<void> deleteDocument(int id) async {
    await apiClient.dio.delete('/cabinet/documents/$id');
  }

  // ── Sharing ─────────────────────────────────────────────────────────────

  Future<void> shareViaEmail({
    required String shareableType,
    required int shareableId,
    required String email,
    String? expiresAt,
  }) async {
    await apiClient.dio.post('/cabinet/shares', data: {
      'shareable_type': shareableType,
      'shareable_id': shareableId,
      'shared_via': 'email',
      'shared_with_email': email,
      if (expiresAt != null) 'expires_at': expiresAt,
    });
  }

  Future<Map<String, dynamic>> shareViaLink({
    required String shareableType,
    required int shareableId,
    String? expiresAt,
  }) async {
    final response = await apiClient.dio.post('/cabinet/shares', data: {
      'shareable_type': shareableType,
      'shareable_id': shareableId,
      'shared_via': 'link',
      if (expiresAt != null) 'expires_at': expiresAt,
    });
    return response.data['data'] as Map<String, dynamic>;
  }

  // ── Stats ───────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getStats() async {
    final response = await apiClient.dio.get('/cabinet/stats');
    return response.data['data'] as Map<String, dynamic>;
  }
}
