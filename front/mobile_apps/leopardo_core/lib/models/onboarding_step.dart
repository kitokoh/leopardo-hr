class OnboardingStep {
  final int id;
  final String key;
  final String title;
  final String? description;
  final String status; // 'pending' | 'completed' | 'skipped'
  final int order;
  final bool required;

  bool get completed => status == 'completed';
  bool get skipped => status == 'skipped';

  OnboardingStep({
    required this.id,
    required this.key,
    required this.title,
    this.description,
    required this.status,
    required this.order,
    required this.required,
  });

  factory OnboardingStep.fromJson(Map<String, dynamic> json) {
    return OnboardingStep(
      id: json['id'] as int,
      // API returns 'step_key', fallback to 'key' for compatibility
      key: (json['step_key'] ?? json['key'] ?? '') as String,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      status: json['status'] as String? ?? 'pending',
      order: (json['order'] ?? 0) as int,
      required: json['required'] as bool? ?? false,
    );
  }
}
