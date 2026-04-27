# Arborescence cible backend API

Ce document fixe l'arborescence cible de `api/app` pour faire evoluer le backend Laravel actuel vers une architecture modulaire, lisible et durable.

## Objectif

L'objectif n'est pas de renommer des dossiers pour faire joli. L'objectif est de clarifier les frontieres entre:

- le metier
- les cas d'usage
- l'infrastructure technique
- les interfaces HTTP et web

Le projet actuel a deja de bonnes bases produit, mais il reste encore principalement organise par couches techniques (`Controllers`, `Models`, `Services`, `Policies`, `Requests`). La cible "senior premium" consiste a organiser le code par domaines fonctionnels, avec des couches internes explicites.

## Principes

- `Core` contient le transverse partage par plusieurs modules.
- `Modules` contient le metier par bounded context.
- Chaque module expose quatre couches: `Domain`, `Application`, `Infrastructure`, `Interfaces`.
- Un controller ne contient ni calcul metier, ni mapping complexe, ni queries riches.
- Les reponses HTTP passent par des `Resources` ou des `Presenters`.
- La multi-tenancy ne doit plus dependre partout de `app('current_company')`.

## Arborescence finale cible

```text
api/
  app/
    Core/
      Auth/
        CurrentActor.php
        RoleMap.php
      Exceptions/
        DomainException.php
        HttpExceptionRenderer.php
      Http/
        Concerns/
        Pagination/
        Responses/
      Support/
        Clock.php
        IdGenerator.php
        Money.php
      Tenancy/
        CurrentTenant.php
        TenantContext.php
        TenantResolver.php
        TenantScope.php
      Shared/
        DTO/
        Events/
        ValueObjects/

    Modules/
      HR/
        Attendance/
          Domain/
            AttendanceLog.php
            AttendancePolicy.php
            Exceptions/
            ValueObjects/
          Application/
            Commands/
              CheckInEmployee.php
              CheckOutEmployee.php
              RecalculateAttendanceLog.php
            Queries/
              ListAttendanceHistory.php
              GetTodayAttendance.php
            DTOs/
              CheckInData.php
              CheckOutData.php
          Infrastructure/
            Persistence/
              EloquentAttendanceRepository.php
            Services/
              AttendanceCalculator.php
          Interfaces/
            Api/
              V1/
                Controllers/
                  AttendanceController.php
                Requests/
                  CheckInRequest.php
                  CheckOutRequest.php
                  AttendanceIndexRequest.php
                  AttendanceTodayRequest.php
                Resources/
                  AttendanceLogResource.php
                  AttendanceTodayResource.php

        Absence/
          Domain/
            Absence.php
            AbsencePolicy.php
            Exceptions/
          Application/
            Commands/
              CreateAbsence.php
              ApproveAbsence.php
              RejectAbsence.php
              CancelAbsence.php
            Queries/
              ListAbsences.php
              GetAbsence.php
            DTOs/
          Infrastructure/
            Persistence/
          Interfaces/
            Api/
              V1/
                Controllers/
                Requests/
                Resources/

        Employee/
          Domain/
            Employee.php
            CompanyMember.php
            Policies/
          Application/
            Commands/
              CreateEmployee.php
              UpdateEmployee.php
              ArchiveEmployee.php
            Queries/
              ListEmployees.php
              GetEmployee.php
            DTOs/
          Infrastructure/
            Persistence/
          Interfaces/
            Api/
              V1/
                Controllers/
                Requests/
                Resources/
            Web/
              Controllers/
              ViewModels/

        Payroll/
          Domain/
            Payroll.php
            PayrollPolicy.php
            Exceptions/
            ValueObjects/
          Application/
            Commands/
              CreatePayroll.php
              UpdatePayroll.php
              ValidatePayroll.php
              DeletePayroll.php
            Queries/
              ListPayrolls.php
              GetPayroll.php
            DTOs/
          Infrastructure/
            Persistence/
            Services/
              PayrollCalculator.php
          Interfaces/
            Api/
              V1/
                Controllers/
                Requests/
                Resources/

        Estimation/
          Domain/
            DailySummary.php
            Receipt.php
          Application/
            Queries/
              BuildDailySummary.php
              BuildQuickEstimate.php
              BuildReceipt.php
            DTOs/
          Infrastructure/
            Pdf/
            Persistence/
          Interfaces/
            Api/
              V1/
                Controllers/
                Requests/
                Resources/

        Invitation/
          Domain/
            Invitation.php
            InvitationPolicy.php
          Application/
            Commands/
              CreateInvitation.php
              ResendInvitation.php
              AcceptInvitation.php
            Queries/
              ListInvitations.php
            DTOs/
          Infrastructure/
            Mail/
            Persistence/
          Interfaces/
            Api/
              V1/
                Controllers/
                Requests/
                Resources/
            Web/
              Controllers/
              ViewModels/

      Cameras/
        Domain/
          Camera.php
          CameraAccessToken.php
          CameraPermission.php
          CameraPolicy.php
          Exceptions/
          ValueObjects/
        Application/
          Commands/
            CreateCamera.php
            UpdateCamera.php
            DeleteCamera.php
            IssueAccessToken.php
            RevokeAccessToken.php
          Queries/
            ListCameras.php
            GetCamera.php
            ListCameraAccessLogs.php
          DTOs/
            CreateCameraData.php
            UpdateCameraData.php
            IssueAccessTokenData.php
        Infrastructure/
          Persistence/
            EloquentCameraRepository.php
          Streaming/
            CameraStreamTokenService.php
            MediaMtxTokenVerifier.php
          Security/
            RtspProbe.php
        Interfaces/
          Api/
            V1/
              Controllers/
                CameraController.php
                CameraAccessTokenController.php
                CameraAccessLogController.php
                CameraPermissionController.php
                InternalCameraTokenController.php
                PublicCameraViewerController.php
              Requests/
                StoreCameraRequest.php
                UpdateCameraRequest.php
                StoreCameraAccessTokenRequest.php
                StoreCameraPermissionRequest.php
                TestRtspRequest.php
              Resources/
                CameraResource.php
                CameraAccessTokenResource.php
                CameraAccessLogResource.php

      Platform/
        Domain/
          Company.php
          Plan.php
          SuperAdmin.php
          FeatureFlags/
        Application/
          Commands/
            ProvisionCompany.php
            UpdateCompanyPlan.php
          Queries/
            ListCompanies.php
            GetPlatformDashboard.php
          DTOs/
        Infrastructure/
          Persistence/
          Provisioning/
        Interfaces/
          Api/
            V1/
              Controllers/
                PlatformAuthController.php
                PlatformCompanyController.php
              Requests/
              Resources/
          Web/
            Controllers/
            ViewModels/
```

## Regles de placement

### Core

`Core` ne contient que les briques partagees. Rien dans `Core` ne doit contenir du comportement metier specifique a RH, Cameras ou Platform.

### Domain

`Domain` porte:

- les regles metier
- les policies metier
- les exceptions fonctionnelles
- les value objects

`Domain` doit eviter de dependre directement de `Illuminate` sauf si la migration est encore en cours.

### Application

`Application` porte:

- les cas d'usage
- les DTOs de commandes et requetes
- l'orchestration de plusieurs composants

Un use case prend une intention metier claire et retourne un resultat exploitable par les interfaces.

### Infrastructure

`Infrastructure` porte:

- Eloquent
- les acces base de donnees
- les appels vers ffprobe, mail, PDF, tokens
- les implementations concretes des contrats

### Interfaces

`Interfaces` porte:

- API controllers
- form requests
- resources JSON
- web controllers
- view models

Le role de cette couche est de traduire HTTP vers l'application, pas d'implementer le metier.

## Mouvements cibles depuis l'existant

### A extraire de `app/Services`

- `AttendanceService` vers `Modules/HR/Attendance/Application` et `Infrastructure/Services`
- `AbsenceService` vers `Modules/HR/Absence/...`
- `EmployeeService` vers `Modules/HR/Employee/...`
- `PayrollService` vers `Modules/HR/Payroll/...`
- `CameraService` vers `Modules/Cameras/Application` et `Infrastructure`
- `FeatureFlag` vers `Modules/Platform/Domain/FeatureFlags` ou `Application`

### A extraire de `app/Http/Controllers`

- tout controller API V1 doit etre deplace dans le module concerne
- les controllers web doivent aller dans `Interfaces/Web`

### A extraire de `app/Http/Requests`

- les requests doivent suivre le module et le point d'entree
- exemple: `AttendanceIndexRequest` va dans `Modules/HR/Attendance/Interfaces/Api/V1/Requests`

### A extraire de `app/Models`

Deux approches sont possibles:

- conserver temporairement les modeles Eloquent dans `app/Models` pendant la migration
- ou les faire vivre directement dans `Infrastructure/Persistence/Eloquent`

Pour limiter le risque, la premiere option est la plus sure dans un premier temps.

## Ordre de migration recommande

1. `Cameras`
2. `HR/Attendance`
3. `HR/Absence`
4. `HR/Employee`
5. `HR/Invitation`
6. `HR/Payroll`
7. `HR/Estimation`
8. `Platform`
9. nettoyage final des anciens dossiers transverses

## Regles d'architecture a faire respecter

- un controller appelle un use case et retourne une resource
- un use case ne depend pas d'un controller
- un module ne depend pas des interfaces d'un autre module
- les reponses JSON ne sont plus construites inline dans les controllers
- les dependances transverses passent par `Core`
- toute nouvelle fonctionnalite backend va dans `Modules/...`

## Resultat attendu

Cette cible doit produire:

- un backend plus lisible
- une meilleure vitesse d'onboarding
- moins de couplage implicite
- des tests plus clairs par domaine
- une base prete pour grossir sans transformer `app/Services` en fourre-tout
