/// Issue #2143 — bloc `compliance` du contrat paie (#1872) exposé par
/// `PaySlipResource` : niveau de confiance des règles pays
/// (production/pilot/placeholder/unknown), avertissement, clé localisée,
/// source légale et date de vérification experte. Rétro-compatible : absent
/// du payload → `null`, aucun affichage côté UI.
class PayrollCompliance {
  final String level;
  final String? warning;
  final String? warningKey;
  final String? source;
  final String? verificationDate;

  const PayrollCompliance({
    required this.level,
    this.warning,
    this.warningKey,
    this.source,
    this.verificationDate,
  });

  factory PayrollCompliance.fromJson(Map<String, dynamic> json) {
    return PayrollCompliance(
      level: (json['level'] ?? 'unknown').toString(),
      warning: json['warning']?.toString(),
      warningKey: json['warning_key']?.toString(),
      source: json['source']?.toString(),
      verificationDate: json['verification_date']?.toString(),
    );
  }

  /// Niveaux nécessitant un message d'avertissement appuyé (maquette ou
  /// inconnu) — utilisé par l'indicateur mobile pour la couleur/le ton.
  bool get isPlaceholderOrUnknown =>
      level == 'placeholder' || level == 'unknown';
}

class Payroll {
  final int id;
  final int employeeId;
  final int month;
  final int year;
  final double grossSalary;
  final double netSalary;
  final String currency;
  final String status;
  final String? pdfPath;
  final DateTime? validatedAt;
  final PayrollCompliance? compliance;
  final String? countryCode;

  Payroll({
    required this.id,
    required this.employeeId,
    required this.month,
    required this.year,
    required this.grossSalary,
    required this.netSalary,
    this.currency = '',
    required this.status,
    this.pdfPath,
    this.validatedAt,
    this.compliance,
    this.countryCode,
  });

  factory Payroll.fromJson(Map<String, dynamic> json) {
    // Issue #2143 — contrat RÉEL de /me/pay-slips (PaySlipResource) :
    // `period_start`/`period_end` (ISO) + `period` (YYYY-MM) + `country_code`
    // + bloc `compliance`. Les formes historiques `period_month`/`period_year`
    // restent acceptées (rétro-compatibilité) — l'ancien parsing levait une
    // exception sur le contrat actuel (écran de paie mobile en erreur).
    int? month;
    int? year;
    final periodShort = json['period']?.toString() ?? '';
    if (periodShort.isNotEmpty) {
      final parts = periodShort.split('-');
      if (parts.length == 2) {
        year = int.tryParse(parts[0]);
        month = int.tryParse(parts[1]);
      }
    }
    if (month == null || year == null) {
      final periodStart = json['period_start']?.toString() ?? '';
      final parsedStart =
          periodStart.isNotEmpty ? DateTime.tryParse(periodStart) : null;
      month = parsedStart?.month ?? (json['period_month'] as num?)?.toInt();
      year = parsedStart?.year ?? (json['period_year'] as num?)?.toInt();
    }

    final complianceJson = json['compliance'];

    return Payroll(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      month: month ?? 1,
      year: year ?? DateTime.now().year,
      grossSalary: (json['gross_salary'] as num).toDouble(),
      netSalary: (json['net_salary'] as num).toDouble(),
      currency: json['currency']?.toString() ?? '',
      status: json['status'] as String,
      pdfPath: json['pdf_path'] as String?,
      validatedAt: json['validated_at'] != null
          ? DateTime.tryParse(json['validated_at'] as String? ?? '')
          : null,
      compliance: complianceJson is Map<String, dynamic>
          ? PayrollCompliance.fromJson(complianceJson)
          : null,
      countryCode: json['country_code']?.toString(),
    );
  }
}
