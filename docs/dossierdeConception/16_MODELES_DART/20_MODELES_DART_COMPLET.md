# MODÈLES DART (FLUTTER) — LEOPARDO RH
# Version 2.0 FINALE | Mars 2026
# Toutes les classes — fromJson / toJson / copyWith / enums
# Dossier cible : mobile/lib/shared/models/

---

## RÈGLES CRITIQUES DART
- Toutes les dates/heures : TOUJOURS `DateTime`, JAMAIS `String`
- Tous les montants : `.toDouble()` — JAMAIS `.toString()`
- Champs optionnels : explicitement déclarés avec `?`
- Enums : factory `fromString()` avec fallback

---

## 1. `employee.dart`

```dart
// lib/shared/models/employee.dart

enum EmployeeRole { employee, manager, superAdmin }
enum EmployeeStatus { active, suspended, archived }
enum ManagerRole { principal, rh, dept, comptable, superviseur }

class EmployeeCompany {
  final String id;
  final String name;
  final String language;
  final String timezone;
  final String currency;
  final String? logoUrl;

  const EmployeeCompany({
    required this.id,
    required this.name,
    required this.language,
    required this.timezone,
    required this.currency,
    this.logoUrl,
  });

  factory EmployeeCompany.fromJson(Map<String, dynamic> json) => EmployeeCompany(
    id: json['id'] as String,
    name: json['name'] as String,
    language: json['language'] as String,
    timezone: json['timezone'] as String,
    currency: json['currency'] as String,
    logoUrl: json['logo_url'] as String?,
  );
}

class Department {
  final int id;
  final String name;
  const Department({required this.id, required this.name});
  factory Department.fromJson(Map<String, dynamic> json) =>
      Department(id: json['id'] as int, name: json['name'] as String);
}

class Position {
  final int id;
  final String name;
  const Position({required this.id, required this.name});
  factory Position.fromJson(Map<String, dynamic> json) =>
      Position(id: json['id'] as int, name: json['name'] as String);
}

class Employee {
  final int id;
  final String matricule;
  final String? zktecoId;
  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String role;
  final String? managerRole;
  final Department? department;
  final Position? position;
  final double leaveBalance;
  final String status;
  final String? photoUrl;
  final String? hireDate;
  final EmployeeCompany? company;

  const Employee({
    required this.id,
    required this.matricule,
    this.zktecoId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    required this.role,
    this.managerRole,
    this.department,
    this.position,
    required this.leaveBalance,
    required this.status,
    this.photoUrl,
    this.hireDate,
    this.company,
  });

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
    id: json['id'] as int,
    matricule: json['matricule'] as String,
    zktecoId: json['zkteco_id'] as String?,
    firstName: json['first_name'] as String,
    lastName: json['last_name'] as String,
    email: json['email'] as String,
    phone: json['phone'] as String?,
    role: json['role'] as String,
    managerRole: json['manager_role'] as String?,
    department: json['department'] != null
        ? Department.fromJson(json['department'] as Map<String, dynamic>)
        : null,
    position: json['position'] != null
        ? Position.fromJson(json['position'] as Map<String, dynamic>)
        : null,
    leaveBalance: (json['leave_balance'] as num).toDouble(),
    status: json['status'] as String,
    photoUrl: json['photo_url'] as String?,
    hireDate: json['hire_date'] as String?,
    company: json['company'] != null
        ? EmployeeCompany.fromJson(json['company'] as Map<String, dynamic>)
        : null,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'matricule': matricule,
    'first_name': firstName,
    'last_name': lastName,
    'email': email,
    'role': role,
    'leave_balance': leaveBalance,
    'status': status,
  };

  String get fullName => '$firstName $lastName';
  bool get isManager => role == 'manager';
  bool get isEmployee => role == 'employee';

  Employee copyWith({
    String? firstName,
    String? lastName,
    String? phone,
    String? photoUrl,
    double? leaveBalance,
    String? status,
  }) => Employee(
    id: id,
    matricule: matricule,
    firstName: firstName ?? this.firstName,
    lastName: lastName ?? this.lastName,
    email: email,
    phone: phone ?? this.phone,
    role: role,
    managerRole: managerRole,
    department: department,
    position: position,
    leaveBalance: leaveBalance ?? this.leaveBalance,
    status: status ?? this.status,
    photoUrl: photoUrl ?? this.photoUrl,
    hireDate: hireDate,
    company: company,
  );
}
```

---

## 2. `attendance_log.dart`

```dart
// lib/shared/models/attendance_log.dart

enum AttendanceStatus { ontime, late, absent, incomplete, leave, holiday }
enum AttendanceMethod { mobile, qr, biometric, manual }

class AttendanceLog {
  final int id;
  final String date;           // Format: "2026-04-15"
  final int sessionNumber;     // 1 = session principale, 2 = split-shift
  final DateTime? checkIn;     // TOUJOURS DateTime — jamais String
  final DateTime? checkOut;    // TOUJOURS DateTime — jamais String
  final double? hoursWorked;
  final double? overtimeHours;
  final String status;
  final String method;
  final bool isManualEdit;

  const AttendanceLog({
    required this.id,
    required this.date,
    this.sessionNumber = 1,
    this.checkIn,
    this.checkOut,
    this.hoursWorked,
    this.overtimeHours,
    required this.status,
    required this.method,
    this.isManualEdit = false,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) => AttendanceLog(
    id: json['id'] as int,
    date: json['date'] as String,
    sessionNumber: json['session_number'] as int? ?? 1,
    checkIn: json['check_in'] != null
        ? DateTime.parse(json['check_in'] as String).toLocal()
        : null,
    checkOut: json['check_out'] != null
        ? DateTime.parse(json['check_out'] as String).toLocal()
        : null,
    hoursWorked: json['hours_worked'] != null
        ? (json['hours_worked'] as num).toDouble()
        : null,
    overtimeHours: json['overtime_hours'] != null
        ? (json['overtime_hours'] as num).toDouble()
        : null,
    status: json['status'] as String,
    method: json['method'] as String,
    isManualEdit: json['is_manual_edit'] as bool? ?? false,
  );

  bool get hasCheckedIn => checkIn != null;
  bool get hasCheckedOut => checkOut != null;
  bool get isComplete => checkIn != null && checkOut != null;
  bool get isLate => status == 'late';
  bool get isAbsent => status == 'absent';

  AttendanceLog copyWith({
    DateTime? checkIn,
    DateTime? checkOut,
    double? hoursWorked,
    String? status,
  }) => AttendanceLog(
    id: id,
    date: date,
    checkIn: checkIn ?? this.checkIn,
    checkOut: checkOut ?? this.checkOut,
    hoursWorked: hoursWorked ?? this.hoursWorked,
    overtimeHours: overtimeHours,
    status: status ?? this.status,
    method: method,
    isManualEdit: isManualEdit,
  );
}

class AttendanceTodayContext {
  final bool isHoliday;
  final bool isLeave;
  final String? expectedStart;

  const AttendanceTodayContext({
    this.isHoliday = false,
    this.isLeave = false,
    this.expectedStart,
  });

  factory AttendanceTodayContext.fromJson(Map<String, dynamic> json) =>
      AttendanceTodayContext(
        isHoliday: json['is_holiday'] as bool? ?? false,
        isLeave: json['is_leave'] as bool? ?? false,
        expectedStart: json['expected_start'] as String?,
      );
}
```

---

## 3. `absence.dart`

```dart
// lib/shared/models/absence.dart

enum AbsenceStatus { pending, approved, rejected, cancelled }

AbsenceStatus absenceStatusFromString(String s) {
  switch (s) {
    case 'approved': return AbsenceStatus.approved;
    case 'rejected': return AbsenceStatus.rejected;
    case 'cancelled': return AbsenceStatus.cancelled;
    default: return AbsenceStatus.pending;
  }
}

class AbsenceType {
  final int id;
  final String label;
  final String color;
  final bool requiresDocument;
  final bool isPaid;

  const AbsenceType({
    required this.id,
    required this.label,
    required this.color,
    this.requiresDocument = false,
    this.isPaid = true,
  });

  factory AbsenceType.fromJson(Map<String, dynamic> json) => AbsenceType(
    id: json['id'] as int,
    label: json['label'] as String,
    color: json['color'] as String,
    requiresDocument: json['requires_document'] as bool? ?? false,
    isPaid: json['is_paid'] as bool? ?? true,
  );
}

class Absence {
  final int id;
  final AbsenceType type;
  final DateTime startDate;   // TOUJOURS DateTime
  final DateTime endDate;     // TOUJOURS DateTime
  final int daysCount;
  final AbsenceStatus status;
  final String? comment;
  final String? rejectedReason;
  final DateTime? createdAt;

  const Absence({
    required this.id,
    required this.type,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    required this.status,
    this.comment,
    this.rejectedReason,
    this.createdAt,
  });

  factory Absence.fromJson(Map<String, dynamic> json) => Absence(
    id: json['id'] as int,
    type: AbsenceType.fromJson(json['type'] as Map<String, dynamic>),
    startDate: DateTime.parse(json['start_date'] as String),
    endDate: DateTime.parse(json['end_date'] as String),
    daysCount: json['days_count'] as int,
    status: absenceStatusFromString(json['status'] as String),
    comment: json['comment'] as String?,
    rejectedReason: json['rejected_reason'] as String?,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isPending => status == AbsenceStatus.pending;
  bool get isApproved => status == AbsenceStatus.approved;
  bool get canBeCancelled => status == AbsenceStatus.pending;
}
```

---

## 4. `salary_advance.dart`

```dart
// lib/shared/models/salary_advance.dart

enum AdvanceStatus { pending, approved, rejected, active, repaid }
// 'active' = avance approuvée en cours de remboursement (PayrollService)

AdvanceStatus advanceStatusFromString(String s) {
  switch (s) {
    case 'approved': return AdvanceStatus.approved;
    case 'rejected': return AdvanceStatus.rejected;
    case 'active':   return AdvanceStatus.active;
    case 'repaid':   return AdvanceStatus.repaid;
    default: return AdvanceStatus.pending;
  }
}

class SalaryAdvance {
  final int id;
  final double amount;
  final double amountRemaining;
  final String reason;
  final AdvanceStatus status;
  final int? repaymentMonths;
  final double? monthlyDeduction;
  final DateTime? approvedAt;
  final DateTime? createdAt;

  const SalaryAdvance({
    required this.id,
    required this.amount,
    required this.amountRemaining,
    required this.reason,
    required this.status,
    this.repaymentMonths,
    this.monthlyDeduction,
    this.approvedAt,
    this.createdAt,
  });

  factory SalaryAdvance.fromJson(Map<String, dynamic> json) => SalaryAdvance(
    id: json['id'] as int,
    amount: (json['amount'] as num).toDouble(),
    amountRemaining: (json['amount_remaining'] as num).toDouble(),
    reason: json['reason'] as String,
    status: advanceStatusFromString(json['status'] as String),
    repaymentMonths: json['repayment_months'] as int?,
    monthlyDeduction: json['monthly_deduction'] != null
        ? (json['monthly_deduction'] as num).toDouble()
        : null,
    approvedAt: json['approved_at'] != null
        ? DateTime.parse(json['approved_at'] as String)
        : null,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isPending => status == AdvanceStatus.pending;
  bool get isApproved => status == AdvanceStatus.approved;
  bool get isActive => status == AdvanceStatus.active;  // En cours de remboursement
  bool get isFullyRepaid => amountRemaining <= 0;
  double get repaidAmount => amount - amountRemaining;
  double get repaidPercentage => amount > 0 ? (repaidAmount / amount) * 100 : 0;
}
```

---

## 5. `task.dart`

```dart
// lib/shared/models/task.dart

enum TaskStatus { todo, inprogress, review, done, rejected, cancelled }
enum TaskPriority { low, normal, high, urgent }

TaskStatus taskStatusFromString(String s) {
  switch (s) {
    case 'inprogress': return TaskStatus.inprogress;
    case 'review': return TaskStatus.review;
    case 'done': return TaskStatus.done;
    case 'rejected': return TaskStatus.rejected;
    case 'cancelled': return TaskStatus.cancelled;
    default: return TaskStatus.todo;
  }
}

TaskPriority taskPriorityFromString(String s) {
  switch (s) {
    case 'low': return TaskPriority.low;
    case 'high': return TaskPriority.high;
    case 'urgent': return TaskPriority.urgent;
    default: return TaskPriority.normal;
  }
}

class ChecklistItem {
  final int id;
  final String label;
  final bool done;

  const ChecklistItem({required this.id, required this.label, required this.done});

  factory ChecklistItem.fromJson(Map<String, dynamic> json) => ChecklistItem(
    id: json['id'] as int,
    label: json['label'] as String,
    done: json['done'] as bool? ?? false,
  );

  Map<String, dynamic> toJson() => {'id': id, 'done': done};

  ChecklistItem copyWith({bool? done}) =>
      ChecklistItem(id: id, label: label, done: done ?? this.done);
}

class TaskProject {
  final int id;
  final String name;
  const TaskProject({required this.id, required this.name});
  factory TaskProject.fromJson(Map<String, dynamic> json) =>
      TaskProject(id: json['id'] as int, name: json['name'] as String);
}

class Task {
  final int id;
  final String title;
  final String? description;
  final TaskStatus status;
  final TaskPriority priority;
  final TaskProject? project;
  final DateTime? dueDate;   // TOUJOURS DateTime
  final List<ChecklistItem> checklist;
  final int commentsCount;
  final DateTime? createdAt;

  const Task({
    required this.id,
    required this.title,
    this.description,
    required this.status,
    required this.priority,
    this.project,
    this.dueDate,
    this.checklist = const [],
    this.commentsCount = 0,
    this.createdAt,
  });

  factory Task.fromJson(Map<String, dynamic> json) => Task(
    id: json['id'] as int,
    title: json['title'] as String,
    description: json['description'] as String?,
    status: taskStatusFromString(json['status'] as String),
    priority: taskPriorityFromString(json['priority'] as String),
    project: json['project'] != null
        ? TaskProject.fromJson(json['project'] as Map<String, dynamic>)
        : null,
    dueDate: json['due_date'] != null
        ? DateTime.parse(json['due_date'] as String)
        : null,
    checklist: json['checklist'] != null
        ? (json['checklist'] as List)
            .map((e) => ChecklistItem.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    commentsCount: json['comments_count'] as int? ?? 0,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isDone => status == TaskStatus.done;
  bool get isOverdue => dueDate != null && dueDate!.isBefore(DateTime.now()) && !isDone;
  int get checklistCompleted => checklist.where((i) => i.done).length;
  double get checklistProgress =>
      checklist.isEmpty ? 0.0 : checklistCompleted / checklist.length;

  Task copyWith({TaskStatus? status, List<ChecklistItem>? checklist}) => Task(
    id: id,
    title: title,
    description: description,
    status: status ?? this.status,
    priority: priority,
    project: project,
    dueDate: dueDate,
    checklist: checklist ?? this.checklist,
    commentsCount: commentsCount,
    createdAt: createdAt,
  );
}

class TaskComment {
  final int id;
  final String authorName;
  final String? authorPhotoUrl;
  final String content;
  final DateTime createdAt;  // TOUJOURS DateTime

  const TaskComment({
    required this.id,
    required this.authorName,
    this.authorPhotoUrl,
    required this.content,
    required this.createdAt,
  });

  factory TaskComment.fromJson(Map<String, dynamic> json) {
    final author = json['author'] as Map<String, dynamic>;
    return TaskComment(
      id: json['id'] as int,
      authorName: author['name'] as String,
      authorPhotoUrl: author['photo_url'] as String?,
      content: json['content'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
```

---

## 6. `payroll_slip.dart`

```dart
// lib/shared/models/payroll_slip.dart

enum PayrollStatus { draft, validated }

PayrollStatus payrollStatusFromString(String s) {
  switch (s) {
    case 'validated': return PayrollStatus.validated;
    default: return PayrollStatus.draft;
  }
}

class PayrollDeductions {
  final double socialSecurity;
  final double incomeTax;
  final double advanceDeduction;
  final double absenceDeduction;

  const PayrollDeductions({
    required this.socialSecurity,
    required this.incomeTax,
    required this.advanceDeduction,
    required this.absenceDeduction,
  });

  factory PayrollDeductions.fromJson(Map<String, dynamic> json) => PayrollDeductions(
    socialSecurity: (json['social_security'] as num).toDouble(),
    incomeTax: (json['income_tax'] as num).toDouble(),
    advanceDeduction: (json['advance_deduction'] as num? ?? 0).toDouble(),
    absenceDeduction: (json['absence_deduction'] as num? ?? 0).toDouble(),
  );

  double get total => socialSecurity + incomeTax + advanceDeduction + absenceDeduction;
}

class PayrollSlip {
  final int id;
  final String period;        // "Avril 2026"
  final int month;
  final int year;
  final double grossSalary;
  final double? overtimePay;
  final PayrollDeductions? deductions;
  final double netSalary;
  final PayrollStatus status;
  final String? pdfUrl;
  final DateTime? validatedAt;

  const PayrollSlip({
    required this.id,
    required this.period,
    required this.month,
    required this.year,
    required this.grossSalary,
    this.overtimePay,
    this.deductions,
    required this.netSalary,
    required this.status,
    this.pdfUrl,
    this.validatedAt,
  });

  factory PayrollSlip.fromJson(Map<String, dynamic> json) => PayrollSlip(
    id: json['id'] as int,
    period: json['period'] as String,
    month: json['month'] as int,
    year: json['year'] as int,
    grossSalary: (json['gross_salary'] as num).toDouble(),
    overtimePay: json['overtime_pay'] != null
        ? (json['overtime_pay'] as num).toDouble()
        : null,
    deductions: json['deductions'] != null
        ? PayrollDeductions.fromJson(json['deductions'] as Map<String, dynamic>)
        : null,
    netSalary: (json['net_salary'] as num).toDouble(),
    status: payrollStatusFromString(json['status'] as String),
    pdfUrl: json['pdf_url'] as String?,
    validatedAt: json['validated_at'] != null
        ? DateTime.parse(json['validated_at'] as String)
        : null,
  );

  bool get isValidated => status == PayrollStatus.validated || status == PayrollStatus.paid;
  bool get hasPdf => pdfUrl != null;
}
```

---

## 7. `app_notification.dart`

```dart
// lib/shared/models/app_notification.dart

enum NotificationType {
  absenceApproved,
  absenceRejected,
  advanceApproved,
  advanceRejected,
  taskAssigned,
  taskCommented,
  payslipAvailable,
  evaluationReceived,
  unknown,
}

NotificationType notificationTypeFromString(String s) {
  switch (s) {
    case 'absence_approved': return NotificationType.absenceApproved;
    case 'absence_rejected': return NotificationType.absenceRejected;
    case 'advance_approved': return NotificationType.advanceApproved;
    case 'advance_rejected': return NotificationType.advanceRejected;
    case 'task_assigned': return NotificationType.taskAssigned;
    case 'task_commented': return NotificationType.taskCommented;
    case 'payslip_available': return NotificationType.payslipAvailable;
    case 'evaluation_received': return NotificationType.evaluationReceived;
    default: return NotificationType.unknown;
  }
}

class AppNotification {
  final int id;
  final NotificationType type;
  final String title;
  final String body;
  final Map<String, dynamic>? data;   // données additionnelles (ex: absence_id)
  final bool isRead;
  final DateTime createdAt;           // TOUJOURS DateTime

  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.data,
    required this.isRead,
    required this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
    id: json['id'] as int,
    type: notificationTypeFromString(json['type'] as String),
    title: json['title'] as String,
    body: json['body'] as String,
    data: json['data'] as Map<String, dynamic>?,
    isRead: json['is_read'] as bool,
    createdAt: DateTime.parse(json['created_at'] as String),
  );

  AppNotification copyWith({bool? isRead}) => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    data: data,
    isRead: isRead ?? this.isRead,
    createdAt: createdAt,
  );
}
```

---

## 8. `project.dart`

```dart
// lib/shared/models/project.dart

enum ProjectStatus { active, completed, archived }

class Project {
  final int id;
  final String name;
  final String? description;
  final ProjectStatus status;
  final int tasksCount;
  final int tasksDone;
  final DateTime? createdAt;

  const Project({
    required this.id,
    required this.name,
    this.description,
    required this.status,
    this.tasksCount = 0,
    this.tasksDone = 0,
    this.createdAt,
  });

  factory Project.fromJson(Map<String, dynamic> json) => Project(
    id: json['id'] as int,
    name: json['name'] as String,
    description: json['description'] as String?,
    status: _statusFromString(json['status'] as String),
    tasksCount: json['tasks_count'] as int? ?? 0,
    tasksDone: json['tasks_done'] as int? ?? 0,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  static ProjectStatus _statusFromString(String s) {
    switch (s) {
      case 'completed': return ProjectStatus.completed;
      case 'archived': return ProjectStatus.archived;
      default: return ProjectStatus.active;
    }
  }

  double get completionRate => tasksCount > 0 ? tasksDone / tasksCount : 0.0;
}
```

---

## 9. `evaluation.dart`

```dart
// lib/shared/models/evaluation.dart

class EvaluationCriterion {
  final String name;
  final int score;       // 1-5
  final String? comment;

  const EvaluationCriterion({
    required this.name,
    required this.score,
    this.comment,
  });

  factory EvaluationCriterion.fromJson(Map<String, dynamic> json) => EvaluationCriterion(
    name: json['name'] as String,
    score: json['score'] as int,
    comment: json['comment'] as String?,
  );

  Map<String, dynamic> toJson() => {
    'name': name,
    'score': score,
    if (comment != null) 'comment': comment,
  };
}

class Evaluation {
  final int id;
  final int employeeId;
  final String period;          // "2026-Q1"
  final double overallScore;
  final List<EvaluationCriterion> criteria;
  final String? globalComment;
  final bool selfEvalDone;
  final List<EvaluationCriterion> selfCriteria;
  final String? selfGlobalComment;
  final String status;          // "draft", "completed"
  final DateTime? createdAt;

  const Evaluation({
    required this.id,
    required this.employeeId,
    required this.period,
    required this.overallScore,
    required this.criteria,
    this.globalComment,
    this.selfEvalDone = false,
    this.selfCriteria = const [],
    this.selfGlobalComment,
    required this.status,
    this.createdAt,
  });

  factory Evaluation.fromJson(Map<String, dynamic> json) => Evaluation(
    id: json['id'] as int,
    employeeId: json['employee_id'] as int,
    period: json['period'] as String,
    overallScore: (json['overall_score'] as num).toDouble(),
    criteria: json['criteria'] != null
        ? (json['criteria'] as List)
            .map((e) => EvaluationCriterion.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    globalComment: json['global_comment'] as String?,
    selfEvalDone: json['self_eval_done'] as bool? ?? false,
    selfCriteria: json['self_criteria'] != null
        ? (json['self_criteria'] as List)
            .map((e) => EvaluationCriterion.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    selfGlobalComment: json['self_global_comment'] as String?,
    status: json['status'] as String,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );
}
```

---

## 10. `pagination.dart` (utilitaire partagé)

```dart
// lib/shared/models/pagination.dart

class PaginationMeta {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const PaginationMeta({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory PaginationMeta.fromJson(Map<String, dynamic> json) => PaginationMeta(
    currentPage: json['current_page'] as int,
    lastPage: json['last_page'] as int,
    perPage: json['per_page'] as int,
    total: json['total'] as int,
  );

  bool get hasNextPage => currentPage < lastPage;
  bool get hasPreviousPage => currentPage > 1;
}

class PaginatedResult<T> {
  final List<T> data;
  final PaginationMeta meta;

  const PaginatedResult({required this.data, required this.meta});
}
```
