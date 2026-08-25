/// Modèle document comptable (issue #5236) — miroir du payload
/// `GET /api/v1/accounting/documents` (AccountingDocumentController::payload).
class AccountingDocument {
  const AccountingDocument({
    required this.id,
    required this.type,
    required this.number,
    required this.status,
    required this.totalTtc,
    this.contactId,
    this.contactName,
    this.issueDate,
    this.dueDate,
    this.currency,
    this.tvaRate,
    this.subtotalHt = 0,
    this.taxAmount = 0,
    this.paidAmount = 0,
    this.notes,
    this.lines = const [],
  });

  final int id;
  final String type;
  final String number;
  final String status;
  final int? contactId;
  final String? contactName;
  final String? issueDate;
  final String? dueDate;
  final String? currency;
  final double? tvaRate;
  final double subtotalHt;
  final double taxAmount;
  final double totalTtc;
  final double paidAmount;
  final String? notes;
  final List<AccountingDocumentLine> lines;

  double get remaining => (totalTtc - paidAmount).clamp(0, totalTtc);

  bool get isUnpaid => status == 'sent' || status == 'overdue';

  factory AccountingDocument.fromJson(Map<String, dynamic> json) {
    final contact = json['contact'];
    final contactName = contact is Map ? contact['name'] : null;

    return AccountingDocument(
      id: (json['id'] as num).toInt(),
      type: json['type'] as String? ?? '',
      number: json['number'] as String? ?? '',
      status: json['status'] as String? ?? 'draft',
      contactId: json['contact_id'] is num
          ? (json['contact_id'] as num).toInt()
          : null,
      contactName: contactName is String ? contactName : null,
      issueDate: json['issue_date'] as String?,
      dueDate: json['due_date'] as String?,
      currency: json['currency'] as String?,
      tvaRate: json['tva_rate'] is num
          ? (json['tva_rate'] as num).toDouble()
          : null,
      subtotalHt: _asDouble(json['subtotal_ht']),
      taxAmount: _asDouble(json['tax_amount']),
      totalTtc: _asDouble(json['total_ttc']),
      paidAmount: _asDouble(json['paid_amount']),
      notes: json['notes'] as String?,
      lines: _parseLines(json['lines']),
    );
  }

  static List<AccountingDocumentLine> _parseLines(dynamic raw) {
    if (raw is! List) {
      return const [];
    }
    return raw
        .whereType<Map>()
        .map((item) =>
            AccountingDocumentLine.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  static double _asDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }
    if (value is String) {
      return double.tryParse(value) ?? 0;
    }
    return 0;
  }
}

/// Ligne d'un document comptable (payload `lines`).
class AccountingDocumentLine {
  const AccountingDocumentLine({
    required this.description,
    this.quantity = 1,
    this.unitPrice = 0,
    this.discount = 0,
  });

  final String description;
  final double quantity;
  final double unitPrice;
  final double discount;

  factory AccountingDocumentLine.fromJson(Map<String, dynamic> json) {
    return AccountingDocumentLine(
      description: json['description'] as String? ?? '',
      quantity: _asDouble(json['quantity'], fallback: 1),
      unitPrice: _asDouble(json['unit_price']),
      discount: _asDouble(json['discount']),
    );
  }

  Map<String, dynamic> toPayload() {
    return {
      'description': description,
      'quantity': quantity,
      'unit_price': unitPrice,
      'discount': discount,
    };
  }

  static double _asDouble(dynamic value, {double fallback = 0}) {
    if (value is num) {
      return value.toDouble();
    }
    return fallback;
  }
}
