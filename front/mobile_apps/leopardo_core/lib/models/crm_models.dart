/// Modèles CRM client (tenant) — issue #5730 (CRM-V1-14, mobile terrain).
///
/// Alignés sur les Resources Laravel du module CRM (`/api/v1/crm/*`,
/// contrats #5712) : clés snake_case, enveloppes `data`/`meta` gérées par
/// `extractDataMap`/`extractDataList`. PII (email/phone) jamais affichée
/// sans autorisation — les Resources API masquent déjà ces champs.
library;

class CrmAccount {
  final int id;
  final String name;
  final String status;
  final String? email;
  final String? phone;
  final String? ownerName;
  final bool archived;

  const CrmAccount({
    required this.id,
    required this.name,
    required this.status,
    this.email,
    this.phone,
    this.ownerName,
    this.archived = false,
  });

  factory CrmAccount.fromJson(Map<String, dynamic> json) {
    return CrmAccount(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      ownerName: json['owner_name'] as String?,
      archived: json['archived_at'] != null,
    );
  }
}

class CrmContact {
  final int id;
  final int? accountId;
  final String firstName;
  final String? lastName;
  final String? email;
  final String? phone;
  final bool isPrimary;

  const CrmContact({
    required this.id,
    this.accountId,
    required this.firstName,
    this.lastName,
    this.email,
    this.phone,
    this.isPrimary = false,
  });

  factory CrmContact.fromJson(Map<String, dynamic> json) {
    return CrmContact(
      id: json['id'] as int? ?? 0,
      accountId: json['account_id'] as int?,
      firstName: json['first_name'] as String? ?? '',
      lastName: json['last_name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      isPrimary: json['is_primary'] as bool? ?? false,
    );
  }

  String get fullName => '${firstName.trim()} ${lastName?.trim() ?? ''}'.trim();
}

class CrmLead {
  final int id;
  final String? firstName;
  final String? lastName;
  final String? companyName;
  final String? email;
  final String? phone;
  final String source;
  final String status;

  const CrmLead({
    required this.id,
    this.firstName,
    this.lastName,
    this.companyName,
    this.email,
    this.phone,
    this.source = 'manual',
    this.status = 'new',
  });

  factory CrmLead.fromJson(Map<String, dynamic> json) {
    return CrmLead(
      id: json['id'] as int? ?? 0,
      firstName: json['first_name'] as String?,
      lastName: json['last_name'] as String?,
      companyName: json['company_name'] as String?,
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      source: json['source'] as String? ?? 'manual',
      status: json['status'] as String? ?? 'new',
    );
  }

  String get displayName {
    final full = '${firstName?.trim() ?? ''} ${lastName?.trim() ?? ''}'.trim();
    return full.isNotEmpty ? full : (companyName ?? 'Lead #$id');
  }
}

class CrmOpportunity {
  final int id;
  final int? pipelineId;
  final String name;
  final String stage;
  final String status;
  final double? amount;
  final String? expectedCloseDate;

  const CrmOpportunity({
    required this.id,
    this.pipelineId,
    required this.name,
    required this.stage,
    this.status = 'open',
    this.amount,
    this.expectedCloseDate,
  });

  factory CrmOpportunity.fromJson(Map<String, dynamic> json) {
    return CrmOpportunity(
      id: json['id'] as int? ?? 0,
      pipelineId: json['pipeline_id'] as int?,
      name: json['name'] as String? ?? '',
      stage: json['stage'] as String? ?? 'prospection',
      status: json['status'] as String? ?? 'open',
      amount: (json['amount'] as num?)?.toDouble(),
      expectedCloseDate: json['expected_close_date'] as String?,
    );
  }
}

class CrmActivity {
  final int id;
  final String subject;
  final String activityType;
  final String? relatedType;
  final int? relatedId;
  final String happenedAt;

  const CrmActivity({
    required this.id,
    required this.subject,
    required this.activityType,
    this.relatedType,
    this.relatedId,
    required this.happenedAt,
  });

  factory CrmActivity.fromJson(Map<String, dynamic> json) {
    return CrmActivity(
      id: json['id'] as int? ?? 0,
      subject: json['subject'] as String? ?? '',
      activityType: json['activity_type'] as String? ?? 'note',
      relatedType: json['related_type'] as String?,
      relatedId: json['related_id'] as int?,
      happenedAt: json['happened_at'] as String? ?? '',
    );
  }
}

class CrmTask {
  final int id;
  final String subject;
  final String status;
  final String priority;
  final String? dueAt;

  const CrmTask({
    required this.id,
    required this.subject,
    required this.status,
    this.priority = 'medium',
    this.dueAt,
  });

  factory CrmTask.fromJson(Map<String, dynamic> json) {
    return CrmTask(
      id: json['id'] as int? ?? 0,
      subject: json['subject'] as String? ?? '',
      status: json['status'] as String? ?? 'todo',
      priority: json['priority'] as String? ?? 'medium',
      dueAt: json['due_at'] as String?,
    );
  }
}
