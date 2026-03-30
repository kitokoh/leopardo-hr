# STRATÉGIE DE TESTS — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. NIVEAUX DE TESTS (PYRAMIDE)

### Tests Unitaires (PHPUnit/Pest)
- **Objectif** : Valider la logique métier pure (Services).
- **Cibles prioritaires** : `PayrollService`, `AttendanceService`, `AbsenceService`.
- **Exigence** : 100% de couverture sur les calculs (HS, Pénalités, Net à payer).

### Tests de Fonctionnalité (Feature Tests)
- **Objectif** : Valider les endpoints API et l'isolation.
- **Cibles prioritaires** : `Auth`, `TenantIsolation`, `CRUD Employees`.
- **Exigence** : 100% de succès sur le test d'isolation `TenantIsolationTest`.

### Tests Widget/Integration (Flutter)
- **Objectif** : Valider le flux utilisateur mobile.
- **Cibles prioritaires** : `LoginScreen`, `AttendanceScreen`, `AbsenceRequest`.

---

## 2. TESTS CRITIQUES (SCÉNARIOS BLOQUANTS)

### TenantIsolationTest
Vérifie qu'un employé de l'entreprise A ne peut jamais voir les données de l'entreprise B, même en changeant l'ID dans l'URL.

### PayrollCalculationTest
Vérifie que pour un brut donné, le net est calculé avec les bons taux de cotisation et tranches d'IR (modèle par pays).

### AttendanceConflictTest
Vérifie qu'un employé ne peut pas pointer deux fois l'arrivée sans avoir pointé le départ.

---

## 3. OUTILS ET AUTOMATISATION

- **Backend** : Pest PHP + Laravel Mocking.
- **Mobile** : Flutter Test + Mockito.
- **CI/CD** : GitHub Actions (Exécution auto à chaque Pull Request).
