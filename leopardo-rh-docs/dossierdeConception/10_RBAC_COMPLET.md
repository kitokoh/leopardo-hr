# RBAC COMPLET — LEOPARDO RH
# Version 1.0 | Mars 2026
# Remplace : rbac_matrrix.md (supprimé — incomplet et incohérent avec l'ERD)

---

## RÔLES DU SYSTÈME

Le système définit **7 rôles distincts**, pas 4.

```
Super Admin          → Éditeur SaaS — accès total à la plateforme
Manager Principal    → Gestionnaire de toute l'entreprise (role=manager, manager_role=principal)
Manager RH           → RH uniquement (role=manager, manager_role=rh)
Manager Département  → Son département uniquement (role=manager, manager_role=dept)
Manager Comptable    → Paie et facturation (role=manager, manager_role=comptable)
Superviseur          → Son équipe assignée (role=manager, manager_role=superviseur)
Employé              → Ses propres données (role=employee)
```

En base de données, les 6 derniers sont tous dans la table `employees` du schéma tenant.
Le Super Admin est dans la table `super_admins` du schéma public.

---

## MATRICE DES PERMISSIONS COMPLÈTE

### Module Entreprises (Super Admin uniquement)

| Action | Super Admin |
|--------|-------------|
| Créer une entreprise | ✅ |
| Modifier une entreprise | ✅ |
| Suspendre / Réactiver | ✅ |
| Voir toutes les entreprises | ✅ |
| Accéder en mode lecture à une entreprise | ✅ |
| Gérer les plans tarifaires | ✅ |
| Générer factures manuelles | ✅ |
| Voir statistiques plateforme | ✅ |

---

### Module Employés

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Lister tous les employés | ✅ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Voir fiche employé | ✅ | ✅ | ✅ | Son dept | Partiel* | Son équipe | Sa fiche |
| Créer un employé | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Modifier un employé | ❌ | ✅ | ✅ | Son dept | ❌ | ❌ | Son profil** |
| Supprimer (archiver) | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Import CSV employés | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Voir salaire / IBAN | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | Son salaire |
| Modifier salaire / IBAN | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

*Comptable voit : nom, matricule, salaire, IBAN — pas les données personnelles
**Employé peut modifier : photo, téléphone, mot de passe

---

### Module Pointage

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Pointer arrivée/départ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Voir ses propres pointages | ❌ | — | — | — | — | — | ✅ |
| Voir pointages de l'équipe | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Corriger un pointage | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Saisir un pointage manuel | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Vue temps réel présences | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Gérer les appareils ZKTeco | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

### Module Absences & Congés

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Soumettre une demande | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Voir ses demandes | ❌ | — | — | — | — | — | ✅ |
| Voir demandes équipe | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Approuver / Refuser | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Saisir absence directe | ❌ | ✅ | ✅ | Son dept | ❌ | ❌ | ❌ |
| Ajuster solde congés | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Configurer types absence | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Voir calendrier équipe | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |

---

### Module Tâches

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Créer une tâche | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Voir ses tâches | ❌ | — | — | — | — | — | ✅ |
| Voir tâches équipe | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Mettre à jour statut | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ | Ses tâches |
| Valider une tâche terminée | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Refuser / retourner | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Ajouter commentaire | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ | Ses tâches |
| Créer un projet | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Vue Kanban | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |

---

### Module Avances sur Salaire

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Soumettre une demande | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (si activé) |
| Voir ses avances | ❌ | — | — | — | — | — | ✅ |
| Voir avances de l'équipe | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Approuver / Refuser | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Modifier plan remboursement | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Activer le module | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

### Module Paie

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Simuler le calcul de paie | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Valider la paie du mois | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Voir tous les bulletins | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Voir son bulletin | ❌ | — | — | — | — | — | ✅ |
| Télécharger son bulletin PDF | ❌ | — | — | — | — | — | ✅ |
| Exporter virement bancaire | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Configurer cotisations / IR | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |

---

### Module Évaluations

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Créer une évaluation | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Voir ses évaluations | ❌ | — | — | — | — | — | ✅ |
| Remplir auto-évaluation | ❌ | — | — | — | — | — | ✅ (si activé) |
| Voir toutes les évaluations | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |

---

### Module Rapports

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Rapport présence | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Rapport absences | ❌ | ✅ | ✅ | Son dept | ❌ | ❌ | ❌ |
| Rapport paie | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Rapport performance | ❌ | ✅ | ✅ | Son dept | ❌ | Son équipe | ❌ |
| Export Excel | ❌ | ✅ | ✅ | Son dept | ✅ | ❌ | ❌ |

---

### Module Paramètres Entreprise

| Action | Super Admin | Principal | RH | Dept | Comptable | Superviseur | Employé |
|--------|:-----------:|:---------:|:--:|:----:|:---------:|:-----------:|:-------:|
| Paramètres généraux (langue, devise...) | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Plannings de travail | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Départements / Postes | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Sites de travail | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Options pointage (GPS, photo) | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Cotisations / IR | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Appareils ZKTeco | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Créer d'autres gestionnaires | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Modèle RH par pays | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## IMPLÉMENTATION LARAVEL

### Middleware et Policies
```php
// Vérification du rôle tenant (dans TenantMiddleware ou Policy)
auth()->user()->role === 'manager'
auth()->user()->manager_role === 'principal'

// Vérification du périmètre département (ManagerDepartmentScope)
auth()->user()->manager_role === 'dept'
    && $employee->department_id === auth()->user()->department_id

// Vérification de l'équipe superviseur (champ extra_data ou table pivot future)
auth()->user()->manager_role === 'superviseur'
    && in_array($employee->id, auth()->user()->supervised_employee_ids)
```

### Conventions de nommage des Policies
```
EmployeePolicy   → view, create, update, delete, viewSalary
AttendancePolicy → view, correct, manualEntry
AbsencePolicy    → view, approve, reject, create
PayrollPolicy    → simulate, validate, export, viewSlip
TaskPolicy       → view, create, updateStatus, validate
```