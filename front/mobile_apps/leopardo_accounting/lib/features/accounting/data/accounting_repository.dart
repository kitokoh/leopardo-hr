import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

import '../models/accounting_document.dart';

/// Accès aux endpoints Comptabilité (issue #5236).
///
/// RBAC : les routes `/accounting/*` exigent un manager `comptable` ou
/// `principal` — l'app est destinée au responsable comptabilité.
/// L'isolation tenant est portée par le token (`company_id` de la requête).
class AccountingRepository {
  AccountingRepository(this._apiClient);

  final ApiClient _apiClient;

  static const int _pageSize = 100;

  /// Liste des documents avec filtres optionnels (type/statut).
  Future<List<AccountingDocument>> listDocuments({
    String? type,
    String? status,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/accounting/documents',
      queryParameters: {
        'per_page': _pageSize,
        if (type != null && type.isNotEmpty) 'type': type,
        if (status != null && status.isNotEmpty) 'status': status,
      },
    );

    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((item) =>
            AccountingDocument.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Documents en attente de paiement (envoyés + en retard).
  Future<List<AccountingDocument>> listUnpaid() async {
    final documents = await listDocuments();
    return documents.where((doc) => doc.isUnpaid).toList()
      ..sort((a, b) {
        final aDate = a.dueDate ?? '';
        final bDate = b.dueDate ?? '';
        return aDate.compareTo(bDate);
      });
  }

  /// Crée un document de type facture (POST /accounting/documents).
  Future<AccountingDocument> createInvoice({
    String? contactId,
    DateTime? issueDate,
    DateTime? dueDate,
    double? tvaRate,
    String? notes,
    required List<AccountingDocumentLine> lines,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/accounting/documents',
      method: 'POST',
      data: {
        'type': 'invoice',
        if (contactId != null && contactId.isNotEmpty)
          'contact_id': int.tryParse(contactId),
        if (issueDate != null)
          'issue_date': _formatDate(issueDate),
        if (dueDate != null) 'due_date': _formatDate(dueDate),
        if (tvaRate != null) 'tva_rate': tvaRate,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        'lines': lines.map((line) => line.toPayload()).toList(),
      },
    );

    return AccountingDocument.fromJson(extractDataMap(response.data));
  }

  static String _formatDate(DateTime date) {
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }
}
