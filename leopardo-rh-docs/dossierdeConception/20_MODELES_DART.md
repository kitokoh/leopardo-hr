# MODÈLES DART (FLUTTER) — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. EMPLOYEE (`lib/shared/models/employee.dart`)

```dart
class Employee {
  final int id;
  final String matricule;
  final String? zktecoId;
  final String firstName;
  final String lastName;
  final String email;
  final String role;
  final String? managerRole;
  final int? departmentId;
  final double leaveBalance;
  final String status;
  final String? photoUrl;

  Employee({
    required this.id,
    required this.matricule,
    this.zktecoId,
    required this.firstName,
    required this.lastName,
    required this.email,
    required this.role,
    this.managerRole,
    this.departmentId,
    required this.leaveBalance,
    required this.status,
    this.photoUrl,
  });

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
    id: json['id'],
    matricule: json['matricule'],
    zktecoId: json['zkteco_id'],
    firstName: json['first_name'],
    lastName: json['last_name'],
    email: json['email'],
    role: json['role'],
    managerRole: json['manager_role'],
    departmentId: json['department_id'],
    leaveBalance: (json['leave_balance'] as num).toDouble(),
    status: json['status'],
    photoUrl: json['photo_url'],
  );

  String get fullName => '$firstName $lastName';
}
```

---

## 2. ATTENDANCE LOG (`lib/shared/models/attendance_log.dart`)

```dart
class AttendanceLog {
  final int id;
  final String date;
  final DateTime? checkIn;
  final DateTime? checkOut;
  final double? hoursWorked;
  final String status;
  final String method;

  AttendanceLog({
    required this.id,
    required this.date,
    this.checkIn,
    this.checkOut,
    this.hoursWorked,
    required this.status,
    required this.method,
  });

  factory AttendanceLog.fromJson(Map<String, dynamic> json) => AttendanceLog(
    id: json['id'],
    date: json['date'],
    checkIn: json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
    checkOut: json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
    hours_worked: json['hours_worked']?.toDouble(),
    status: json['status'],
    method: json['method'],
  );
}
```

---

## 3. ABSENCE (`lib/shared/models/absence.dart`)

```dart
class Absence {
  final int id;
  final String label;
  final DateTime startDate;
  final DateTime endDate;
  final int daysCount;
  final String status;
  final String color;

  Absence({
    required this.id,
    required this.label,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    required this.status,
    required this.color,
  });

  factory Absence.fromJson(Map<String, dynamic> json) => Absence(
    id: json['id'],
    label: json['type']['label'],
    startDate: DateTime.parse(json['start_date']),
    endDate: DateTime.parse(json['end_date']),
    daysCount: json['days_count'],
    status: json['status'],
    color: json['type']['color'],
  );
}
```
