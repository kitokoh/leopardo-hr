# INDEX DES PATCHES — À LIRE EN PREMIER
# Ces fichiers complètent les prompts v2. Ils ne les remplacent pas.
# Chaque patch précise à quel moment l'appliquer.

---

## ORDRE D'APPLICATION

| Patch | Quand l'appliquer | Priorité |
|---|---|---|
| `PATCH-CC-02-SECURITE.md` | Pendant CC-02 (Auth) | 🔴 CRITIQUE |
| `PATCH-CC-03-CHIFFREMENT.md` | Pendant CC-03 (Employee model) | 🔴 CRITIQUE |
| `PATCH-CC-06-FICHIERS-ET-EXPORT.md` | Pendant CC-06 (Paie) | 🔴 CRITIQUE |
| `PATCH-CC-07-MODULES-MANQUANTS.md` | Pendant CC-07 (Super Admin) | 🟠 ÉLEVÉ |
| `PATCH-TRANSVERSAL-I18N-NOTIFS-STATES.md` | Lire AVANT CC-01, relire à chaque module | 🟡 RÉFÉRENCE |
| `PATCH-FLUTTER-ONBOARDING-SUBSCRIPTION.md` | JU-01 (onboarding) + JU-03 (subscription) | 🟠 ÉLEVÉ |
| `PATCH-FRONTEND-ONBOARDING-USERFLOWS.md` | Pendant CU-01 | 🟠 ÉLEVÉ |

---

## CE QUE CHAQUE PATCH CORRIGE

### PATCH-CC-02-SECURITE
- Durées de session différentes : Flutter 90j / SPA 8h / SuperAdmin 4h
- Table `super_admin_tokens` + double provider Sanctum
- Middleware `CheckSubscription` avec période de grâce 3 jours
- Commande cron `subscriptions:check`
- 6 tests de sécurité

### PATCH-CC-03-CHIFFREMENT
- Cast `encrypted` sur `national_id`, `iban`, `bank_account` dans Employee
- EmployeeResource : champs sensibles masqués selon le rôle
- EmployeeObserver : champs sensibles exclus des audit logs
- Colonnes TEXT (pas VARCHAR) pour les champs chiffrés
- 4 tests de chiffrement

### PATCH-CC-06-FICHIERS-ET-EXPORT
- Contrôleur de fichiers privés (PDF jamais en URL directe)
- Structure de stockage par tenant_uuid
- GeneratePayslipPdfJob : chemin privé + pas d'URL publique
- BOM UTF-8 dans l'export bancaire DZ (Excel algérien)
- 4 tests de sécurité fichiers

### PATCH-CC-07-MODULES-MANQUANTS
- Module Settings entreprise (GET/PUT /settings + apply-hr-model)
- Module Appareils ZKTeco complet (6 endpoints + rotate-token)
- Module Onboarding guidé (OnboardingService + auto-detection progression)
- Webhooks Stripe + Paydunya (vérification signature + job async)
- Module Facturation Super Admin
- Script backup PostgreSQL + test de restauration mensuel
- Alerte annuelle mise à jour jours fériés islamiques

### PATCH-TRANSVERSAL-I18N-NOTIFS-STATES
- Structure complète lang/{fr,ar,en,tr}/{errors,notifications,pdf}.php
- Liste exhaustive des 14 codes d'erreur
- Matrice 14 types de notifications × 5 canaux
- NotificationService unifié
- State machines pour Absence, Avance, Tâche, Paie, Évaluation
- Validation des transitions d'état dans les controllers
- Index des diagrammes UML à lire par module
- Interface Super Admin ajout pays + alerte jours fériés

### PATCH-FLUTTER-ONBOARDING-SUBSCRIPTION
- OnboardingScreen (4 étapes, barre de progression, QR store)
- Intégration dans GoRouter (redirect si onboarding non terminé)
- SubscriptionInterceptor Dio (détection headers grâce/expiration)
- SubscriptionExpiredScreen
- GracePeriodBanner dans AppLayout
- 2 tests widget

### PATCH-FRONTEND-ONBOARDING-USERFLOWS
- OnboardingWizard.vue (modal bloquante, 4 étapes)
- SubscriptionGraceBanner.vue
- HandleInertiaRequests : inject onboarding + subscriptionGrace
- 4 user flows détaillés (création employé, approbation, paie, correction pointage)
- Checklist conformité frontend
