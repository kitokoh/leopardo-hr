# SmartAttendance — Application layer (façade BC, ADR-0016 Phase 4)

Les actions applicatives SmartAttendance ont été fusionnées dans le module
Attendance (ADR-0016 Phase 4, issue #5355) : `App\Modules\Attendance\Application\Actions\*`.
Ce dossier est conservé comme marqueur structurel du module (PA2-ARCH-006) —
aucune classe n'y réside ; les controllers BC de SmartAttendance consomment
directement les implémentations Attendance.
