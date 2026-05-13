class TrainingEnrollment {
  final int id;
  final String courseTitle;
  final String? sessionDate;
  final int progress;
  final String status;

  TrainingEnrollment({
    required this.id,
    required this.courseTitle,
    this.sessionDate,
    required this.progress,
    required this.status,
  });

  factory TrainingEnrollment.fromJson(Map<String, dynamic> json) {
    return TrainingEnrollment(
      id: json['id'] as int,
      courseTitle: json['course_title'] as String? ?? '',
      sessionDate: json['session_date'] as String?,
      progress: json['progress'] as int? ?? 0,
      status: json['status'] as String? ?? 'enrolled',
    );
  }
}
