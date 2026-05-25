class OnboardingStep {
  final int id;
  final String key;
  final String title;
  final String? description;
  final bool completed;
  final bool skipped;

  OnboardingStep({
    required this.id,
    required this.key,
    required this.title,
    this.description,
    required this.completed,
    required this.skipped,
  });

  factory OnboardingStep.fromJson(Map<String, dynamic> json) {
    return OnboardingStep(
      id: json['id'] as int,
      key: json['key'] as String? ?? '',
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      completed: json['completed'] as bool? ?? false,
      skipped: json['skipped'] as bool? ?? false,
    );
  }
}
