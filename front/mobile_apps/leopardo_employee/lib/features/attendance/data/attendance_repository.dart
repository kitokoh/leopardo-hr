export 'package:leopardo_core/features/attendance/data/attendance_repository.dart'
    show AttendanceRepository, ManagerAnomalyReport, ManagerAnomaly;

/// Leopardo employee — passerelle vers le repository attendance partagé
/// (#7652, réconciliation de la duplication core ↔ apps). L'implémentation
/// canonique vit dans `leopardo_core` : sessions multiples, work_type,
/// punch_note, file hors-ligne idempotente (RTMX #5407) et règle F-21
/// « 1er pointage gagne ». Aucune duplication locale.
