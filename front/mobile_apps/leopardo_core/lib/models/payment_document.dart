class PaymentDocument {
  const PaymentDocument({
    required this.id,
    required this.documentType,
    required this.status,
    required this.isDownloadable,
    this.companyId,
    this.employeeId,
    this.payrollRunId,
    this.paySlipId,
    this.salaryAdvanceId,
    this.filename,
    this.mimeType,
    this.sizeBytes,
    this.errorMessage,
    this.generatedAt,
    this.createdAt,
  });

  final int id;
  final String? companyId;
  final int? employeeId;
  final int? payrollRunId;
  final int? paySlipId;
  final int? salaryAdvanceId;
  final String documentType;
  final String status;
  final String? filename;
  final String? mimeType;
  final int? sizeBytes;
  final bool isDownloadable;
  final String? errorMessage;
  final DateTime? generatedAt;
  final DateTime? createdAt;

  factory PaymentDocument.fromJson(Map<String, dynamic> json) {
    return PaymentDocument(
      id: _asInt(json['id']),
      companyId: json['company_id']?.toString(),
      employeeId: _nullableInt(json['employee_id']),
      payrollRunId: _nullableInt(json['payroll_run_id']),
      paySlipId: _nullableInt(json['pay_slip_id']),
      salaryAdvanceId: _nullableInt(json['salary_advance_id']),
      documentType: (json['document_type'] ?? 'payment_receipt').toString(),
      status: (json['status'] ?? 'pending').toString(),
      filename: json['filename']?.toString(),
      mimeType: json['mime_type']?.toString(),
      sizeBytes: _nullableInt(json['size_bytes']),
      isDownloadable: json['is_downloadable'] == true,
      errorMessage: json['error_message']?.toString(),
      generatedAt: _parseDate(json['generated_at']),
      createdAt: _parseDate(json['created_at']),
    );
  }

  bool get isAvailable => status == 'available' && isDownloadable;

  String get typeLabel => switch (documentType) {
    'advance_receipt' => 'Recu avance',
    'payment_slip' => 'Bulletin',
    'payroll_summary' => 'Resume paie',
    'payment_receipt' => 'Recu paiement',
    _ => 'Document paiement',
  };

  String get statusLabel => switch (status) {
    'pending' => 'En attente',
    'generating' => 'Generation',
    'available' => 'Disponible',
    'failed' => 'Erreur',
    _ => status,
  };

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int? _nullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value.toString());
  }

  static DateTime? _parseDate(dynamic value) {
    if (value == null) return null;
    return DateTime.tryParse(value.toString());
  }
}
