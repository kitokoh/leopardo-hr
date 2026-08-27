export 'package:leopardo_core/features/attendance/data/attendance_repository.dart'
    show AttendanceRepository, ManagerAnomalyReport, ManagerAnomaly;

/// Leopardo manager — passerelle vers le repository attendance partagé
/// (leopardo_core, issue #5279). Le payload de pointage construit par
/// [AttendanceRepository] transporte les coordonnées GPS du check-in :
/// `gps_lat` / `gps_lng` / `gps_accuracy` (contrat CheckInRequest/CheckOutRequest
/// côté API). L'app manager ré-exporte l'implémentation core — aucune
/// duplication locale (garde de readiness mobile, lot #5279).
