class Project {
  final int id;
  final String name;
  final String? description;
  final DateTime? startDate;
  final DateTime? endDate;
  final String status;
  final List<int> memberIds;

  Project({
    required this.id,
    required this.name,
    this.description,
    this.startDate,
    this.endDate,
    required this.status,
    required this.memberIds,
  });

  factory Project.fromJson(Map<String, dynamic> json) {
    return Project(
      id: json['id'] as int,
      name: json['name'] as String,
      description: json['description'] as String?,
      startDate: json['start_date'] != null ? DateTime.parse(json['start_date'] as String) : null,
      endDate: json['end_date'] != null ? DateTime.parse(json['end_date'] as String) : null,
      status: json['status'] as String,
      memberIds: (json['members'] as List?)?.map((e) => e as int).toList() ?? [],
    );
  }
}

class Task {
  final int id;
  final String title;
  final String? description;
  final List<int> assignedTo;
  final int? projectId;
  final DateTime? dueDate;
  final String priority;
  final String status;

  Task({
    required this.id,
    required this.title,
    this.description,
    required this.assignedTo,
    this.projectId,
    this.dueDate,
    required this.priority,
    required this.status,
  });

  factory Task.fromJson(Map<String, dynamic> json) {
    return Task(
      id: json['id'] as int,
      title: json['title'] as String,
      description: json['description'] as String?,
      assignedTo: (json['assigned_to'] as List?)?.map((e) => e as int).toList() ?? [],
      projectId: json['project_id'] as int?,
      dueDate: json['due_date'] != null ? DateTime.parse(json['due_date'] as String) : null,
      priority: json['priority'] as String,
      status: json['status'] as String,
    );
  }
}
