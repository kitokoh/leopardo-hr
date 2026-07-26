# 08 — Audit Mobile (Flutter)

> **Quand l'utiliser :** Pour auditer les 3+ apps Flutter : employee, manager, platform admin (et éventuellement marketing). Vérifier le démarrage, les API calls, le design, l'offline, les notifications.
> **Durée estimée :** Long (45-60 min)
> **Prérequis :** Être sur `main` à jour

## Instructions

```
Agis en tant qu'auditeur mobile senior pour le projet Leopardo RH situé dans c:\Users\cheic\Downloads\gestionemployer.

Commence par lire AGENTS.md (sections mobile, StartupGate, extractDataList, PushNotificationService, GPS, branding tenant).

Audite les apps Flutter dans front/mobile_apps/.

Pour CHAQUE app (leopardo_employee, leopardo_manager, leopardo_platform_admin, leopardo_marketing si elle existe) :

1. DÉMARRAGE : Vérifie que main.dart appelle runApp() AVANT tout await. StartupGate doit être le premier widget. Pas de page noire/grise au lancement. Vérifie la conformité avec validate-mobile-runtime-smoke.ps1.

2. CORE PARTAGÉ : Vérifie que l'app utilise leopardo_core correctement. Les API calls doivent passer par requestWithRetry + extractDataList/extractDataMap. Pas d'appels directs via apiClient.dio.* (sauf dio.download).

3. NAVIGATION : Vérifie le router. Employee/manager démarrent sur /welcome, platform admin sur /platform/login. Pas de routes orphelines.

4. NOTIFICATIONS : PushNotificationService doit s'initialiser après auth, synchroniser /device-tokens, supprimer le token au logout. Non bloquant.

5. GPS : AttendanceLocationService via leopardo_core. Permissions natives Android/iOS présentes. Timeout court. Pointage accepté sans GPS.

6. BRANDING TENANT : TenantBranding/TenantTheme depuis leopardo_core. Platform admin ne doit PAS appliquer de thème tenant global.

7. DÉPENDANCES : Vérifie pubspec.yaml. SDK constraint >=3.3.0 <4.0.0. Pas de dépendances obsolètes ou conflictuelles.

8. ÉCRANS CRITIQUES : Vérifie que les écrans Compte gardent la déconnexion en bas. Pas de boutons sans action. Les listes (pointage, absences, avances, etc.) utilisent extractDataList.

9. FIREBASE : Vérifier que Firebase.initializeApp() est protégé et timeboxé. Pas d'accès à FirebaseMessaging.instance avant init. google-services.json présent dans android/app/.

10. OFFLINE : Hive offlineCache avec récupération par suppression/réouverture en cas de corruption.

Produis un rapport par app avec 🔴🟡🟢 et crée des issues pour les 🔴.
```

## Notes

- Les 3 apps sont séparées mais partagent leopardo_core.
- StartupGate auto-libère après timeout/échec critique court (pas de blocage sur logo).
- Le workflow double validation pour avances salaire : manager approve → mark-paid → confirm-received.
- Les builds Firebase doivent avoir des noms préfixés par app (employee-*, manager-*, platform-admin-*).
