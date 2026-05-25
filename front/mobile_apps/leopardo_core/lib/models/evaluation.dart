import 'package:leopardo_core/models/employee.dart';

class Evaluation {
  const Evaluation({
    required this.id,
    required this.employeeId,
    required this.evaluatorId,
    required this.period,
    required this.status,
    this.employee,
    this.evaluator,
    this.score,
    this.criteria = const <EvaluationCriterion>[],
    this.strengths,
    this.improvements,
    this.overallComment,
    this.acknowledgedAt,
    this.createdAt,
    this.updatedAt,
  });

  final int id;
  final int employeeId;
  final int evaluatorId;
  final String period;
  final String status;
  final Employee? employee;
  final Employee? evaluator;
  final double? score;
  final List<EvaluationCriterion> criteria;
  final String? strengths;
  final String? improvements;
  final String? overallComment;
  final DateTime? acknowledgedAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  factory Evaluation.fromJson(Map<String, dynamic> json) {
    final rawCriteria = json['criteria'];
    final criteria = <EvaluationCriterion>[];
    if (rawCriteria is List) {
      for (final item in rawCriteria) {
        if (item is Map) {
          criteria.add(
            EvaluationCriterion.fromJson(item.cast<String, dynamic>()),
          );
        }
      }
    }

    return Evaluation(
      id: (json['id'] as num?)?.toInt() ?? 0,
      employeeId: (json['employee_id'] as num?)?.toInt() ?? 0,
      evaluatorId: (json['evaluator_id'] as num?)?.toInt() ?? 0,
      period: (json['period'] ?? '') as String,
      status: (json['status'] ?? 'draft') as String,
      employee: _parseEmployee(json['employee']),
      evaluator: _parseEmployee(json['evaluator']),
      score: _parseDouble(json['score']),
      criteria: criteria,
      strengths: json['strengths'] as String?,
      improvements: json['improvements'] as String?,
      overallComment: json['overall_comment'] as String?,
      acknowledgedAt: DateTime.tryParse(
        json['acknowledged_at']?.toString() ?? '',
      ),
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      updatedAt: DateTime.tryParse(json['updated_at']?.toString() ?? ''),
    );
  }

  static Employee? _parseEmployee(dynamic value) {
    if (value is! Map) return null;
    final data = value.cast<String, dynamic>();
    return Employee.fromJson({
      'id': data['id'] ?? 0,
      'first_name': data['first_name'] ?? '',
      'last_name': data['last_name'] ?? '',
      'email': data['email'] ?? '',
      'status': data['status'] ?? 'active',
    });
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }
}

class EvaluationCriterion {
  const EvaluationCriterion({required this.label, required this.score});

  final String label;
  final double score;

  factory EvaluationCriterion.fromJson(Map<String, dynamic> json) {
    return EvaluationCriterion(
      label: (json['label'] ?? '') as String,
      score: ((json['score'] as num?) ?? 0).toDouble(),
    );
  }

  Map<String, dynamic> toJson() => {'label': label, 'score': score};
}
