# PLAN SÉCURITÉ & RGPD DÉTAILLÉ
## Leopardo HR — Audit Enterprise 2026

---

## 1. ÉTAT ACTUEL DE LA SÉCURITÉ

### Points forts identifiés
- ✅ **Sanctum** pour l'authentification API (tokens Bearer, révocation)
- ✅ **TokenAutoRefreshMiddleware** — Rotation des tokens active
- ✅ **TenantMiddleware** — Isolation multi-tenant par company_id
- ✅ **Policies Laravel** — RBAC sur 20+ ressources (AbsencePolicy, PayrollPolicy, EmployeePolicy...)
- ✅ **SensitiveDataEncryptor** — Chiffrement AES-256 des données sensibles
- ✅ **RequestIdMiddleware** — Traçabilité des requêtes
- ✅ **SentryContextMiddleware** — Enrichissement contexte erreurs
- ✅ **Rate limiting** sur auth (throttle:auth-sensitive)
- ✅ **OWASP ZAP** en CI (owasp-zap.yml)
- ✅ **CodeQL** en CI (codeql.yml)
- ✅ **secret-scan.yml** — Scan des secrets dans le code
- ✅ **PrivacyController** — RGPD export + demande de suppression + consentement biométrique
- ✅ **AuditLog model** — Base de l'audit trail

### Risques identifiés

---

## 2. VULNÉRABILITÉS & RISQUES

### 2.1 Risques CRITIQUES

#### A. APP_DEBUG en production
**Risque :** Si `APP_DEBUG=true` en production (valeur par défaut Laravel), les stack traces incluent variables d'environnement, credentials DB, structure de code.
**Mitigation :** Forcer `APP_DEBUG=false` + `APP_ENV=production` dans Render.
**Vérification :** `GET /api/v1/health` ne doit jamais retourner des stack traces.

#### B. Isolation multi-tenant non testée
**Risque :** Le TenantMiddleware applique `search_path = shared_tenants,public` mais sans test E2E croisé, une fuite de données entre tenants est possible.
**Mitigation :** Tests feature croisés — authentification tenant A, tentative d'accès ressource tenant B.
```php
// Test à ajouter dans tests/Feature/MultiTenantIsolationTest.php
it('cannot access another tenant employee', function () {
    $userA = loginAsTenant('tenant_a');
    $employeeB = Employee::factory()->forTenant('tenant_b')->create();
    $this->actingAs($userA)->getJson("/api/v1/employees/{$employeeB->id}")
         ->assertStatus(403);
});
```

#### C. JWT/Sanctum : absence de blacklist token
**Risque :** En cas de vol de token, aucune révocation côté serveur sans appel à `/auth/logout`.
**Mitigation :** Implémenter token blacklist Redis avec TTL = durée de vie du token.

### 2.2 Risques MAJEURS

#### D. FCM tokens persistants après déconnexion
**Risque :** Si `DELETE /api/v1/device-tokens` échoue silencieusement à la déconnexion, des push notifications arrivent sur des comptes déconnectés.
**Mitigation :** Valider que le DELETE retourne 200 ET que le token est bien supprimé de la table `device_tokens`.

#### E. Fichiers RH — Stockage Firebase Storage non chiffré côté client
**Risque :** Les bulletins de paie PDF et les documents RH stockés dans Firebase Storage sont accessibles avec l'URL Firebase si les règles de sécurité sont trop permissives.
**Mitigation :** Règles Firebase Storage strictes — accès uniquement via token signé valide.
```
// firebase.storage.rules
rules_version = '2';
service firebase.storage {
  match /b/{bucket}/o {
    match /payslips/{companyId}/{employeeId}/{file} {
      allow read: if request.auth != null && request.auth.token.company_id == companyId;
      allow write: if false; // Backend only
    }
  }
}
```

#### F. Rate limiting FCM manquant
**Risque :** Un acteur malveillant pourrait déclencher des milliers de push notifications en boucle si le contrôle de fréquence n'est pas en place.
**Mitigation :** Rate limit sur `POST /api/v1/device-tokens` + throttle sur les jobs FCM.

#### G. Absence de CORS strict en production
**Risque :** Si `CORS_ALLOWED_ORIGINS=*`, l'API peut être appelée depuis n'importe quel domaine.
**Mitigation :** Définir `CORS_ALLOWED_ORIGINS` explicitement avec les domaines production.

### 2.3 Risques MOYENS

#### H. Logs insuffisamment filtrés
**Risque :** Des mots de passe ou tokens peuvent apparaître dans les logs si les requêtes ne sont pas filtrées.
**Mitigation :** Ajouter `$dontFlash = ['password', 'token', 'secret']` dans tous les handlers.

#### I. RBAC paie — granularité insuffisante
**Risque :** La PayrollPolicy ne distingue pas clairement "voir son propre bulletin" vs "voir les bulletins de tous les employés" pour un rôle RH.
**Mitigation :** Documenter et tester la matrice RBAC complète.

---

## 3. CONFORMITÉ RGPD

### Ce qui est implémenté ✅
- Export de données (`GET /api/v1/privacy/export`)
- Demande de suppression (`POST /api/v1/privacy/deletion-request`)
- Consentement biométrique (`PATCH /api/v1/privacy/biometric-consent`)
- Politique de confidentialité publique (PrivacyController)

### Ce qui manque ❌

#### A. Registre des traitements
**Obligation RGPD Article 30** — Documenter tous les traitements de données personnelles, leur base légale, durée de conservation.
**Plan d'action :** Créer `docs/RGPD_REGISTRE_TRAITEMENTS.md` avec :
- Données collectées (pointage, paie, biométrie, fichiers RH)
- Base légale (contrat, obligation légale, intérêt légitime)
- Durée de conservation (5 ans pour paie selon DZ/MA)
- Destinataires (manager, RH, auditeurs)

#### B. Procédure de réponse aux demandes RGPD
**Délai légal : 30 jours** pour répondre à une demande d'accès ou suppression.
**Plan d'action :** Implémenter un workflow dans PlatformAdmin pour traiter les demandes automatiquement.

#### C. Notification de breach
**Obligation RGPD Article 33** — Notifier la CNIL/DPA dans les 72h en cas de violation de données.
**Plan d'action :** Implémenter un runbook d'incident de sécurité + contact DPA par pays.

#### D. Privacy by Design — Minimisation des données
**Risque :** Certains endpoints retournent trop de champs utilisateur/employé dans les listes.
**Plan d'action :** Audit des ressources API, appliquer des fractal transformers limitant les champs exposés.

#### E. Conformité locale — Loi 18-07 (Algérie) + Loi 09-08 (Maroc)
**Ces lois imposent :**
- Déclaration auprès de l'autorité nationale (ANPDP en DZ, CNDP en MA)
- Consentement explicite pour les données biométriques
- Localisation des données (hébergement en pays ou accord de transfert)
**Plan d'action :** Consulter un juriste local, préparer les déclarations réglementaires.

---

## 4. MATRICE RBAC COMPLÈTE

| Action | Employé | Manager RH | Manager Principal | Super Admin Tenant | Super Admin Platform |
|---|---|---|---|---|---|
| Voir son profil | ✅ | ✅ | ✅ | ✅ | ✅ |
| Voir profil autres employés | ❌ | ✅ | ✅ | ✅ | ✅ |
| Créer employé | ❌ | ✅ | ✅ | ✅ | ❌ |
| Supprimer employé | ❌ | ❌ | ✅ | ✅ | ❌ |
| Voir son bulletin de paie | ✅ | ✅ | ✅ | ✅ | ❌ |
| Voir bulletins tous | ❌ | ✅ | ✅ | ✅ | ❌ |
| Approuver absence | ❌ | ✅ | ✅ | ✅ | ❌ |
| Approuver avance | ❌ | ✅ | ✅ | ✅ | ❌ |
| Lancer paie | ❌ | ❌ | ✅ | ✅ | ❌ |
| Gérer tenants | ❌ | ❌ | ❌ | ❌ | ✅ |
| Voir données inter-tenant | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## 5. PLAN D'ACTION SÉCURITÉ — PRIORITÉS

### Semaine 1 (Bloquants)
1. Forcer `APP_DEBUG=false` + `APP_ENV=production` sur Render
2. Configurer `CORS_ALLOWED_ORIGINS` avec domaines explicites
3. Valider `DELETE /api/v1/device-tokens` à la déconnexion

### Semaine 2-3 (Majeur)
4. Tests feature d'isolation multi-tenant
5. Implémenter blacklist token Redis
6. Audit règles Firebase Storage
7. Rate limiting FCM

### Mois 2 (RGPD)
8. Registre des traitements documenté
9. Procédure réponse demandes RGPD < 30j
10. Runbook incident sécurité
11. Déclarations ANPDP/CNDP

### Mois 3 (Audit)
12. Audit externe sécurité (pentest)
13. Préparation SOC 2 Type I
14. Politique de rotation des secrets (Render env vars)
