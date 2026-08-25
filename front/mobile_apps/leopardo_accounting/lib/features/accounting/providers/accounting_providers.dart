import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_accounting/core/providers/core_providers.dart';
import 'package:leopardo_accounting/features/accounting/models/accounting_document.dart';

/// Providers d'état des données Comptabilité (issue #5236).
///
/// `FutureProvider` rejoués à la demande (invalidate) après chaque mutation.

final documentsProvider = FutureProvider<List<AccountingDocument>>((ref) {
  return ref.watch(accountingRepositoryProvider).listDocuments();
});

final unpaidProvider = FutureProvider<List<AccountingDocument>>((ref) {
  return ref.watch(accountingRepositoryProvider).listUnpaid();
});

/// Action de création de facture : rejoue la liste des documents après
/// succès (le nouvel état est visible immédiatement).
final createInvoiceProvider =
    FutureProvider.family<AccountingDocument, Map<String, dynamic>>((
      ref,
      payload,
    ) async {
      final repository = ref.watch(accountingRepositoryProvider);
      final document = await repository.createInvoice(
        contactId: payload['contact_id'] as String?,
        issueDate: payload['issue_date'] as DateTime?,
        dueDate: payload['due_date'] as DateTime?,
        tvaRate: payload['tva_rate'] as double?,
        notes: payload['notes'] as String?,
        lines: payload['lines'] as List<AccountingDocumentLine>,
      );
      ref.invalidate(documentsProvider);
      ref.invalidate(unpaidProvider);
      return document;
    });
