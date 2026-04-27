class Evaluation {
  final int id;
  final int employeeId;
  final int evaluatorId;
  final String? evaluatorName;
  final String period;
  final double? score;
  final String status;
  final String? overallComment;
  final DateTime? acknowledgedAt;
  final DateTime createdAt;

  Evaluation({
    required this.id,
    required this.employeeId,
    required this.evaluatorId,
    this.evaluatorName,
    required this.period,
    this.score,
    required this.status,
    this.overallComment,
    this.acknowledgedAt,
    required this.createdAt,
  });

  factory Evaluation.fromJson(Map<String, dynamic> json) {
    return Evaluation(
      id: json['id'] as int,
      employeeId: json['employee_id'] as int,
      evaluatorId: json['evaluator_id'] as int,
      evaluatorName: json['evaluator'] != null
          ? '${json['evaluator']['first_name']} ${json['evaluator']['last_name']}'
          : null,
      period: json['period'] as String,
      score: json['score'] != null ? (json['score'] as num).toDouble() : null,
      status: json['status'] as String,
      overallComment: json['overall_comment'] as String?,
      acknowledgedAt: json['acknowledged_at'] != null
          ? DateTime.parse(json['acknowledged_at'] as String)
          : null,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
