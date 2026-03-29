# ERREURS ET LOGGING — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. FORMAT DE RÉPONSE D'ERREUR API

Toutes les erreurs retournent un objet JSON standardisé :

```json
{
  "error": "CODE_ERREUR_MAJUSCULE",
  "message": "Description lisible par l'humain (traduite)",
  "details": { "champ": ["La validation a échoué"] }
}
```

### Codes HTTP recommandés :
- **400 Bad Request** : Requête malformée.
- **401 Unauthorized** : Token manquant ou expiré.
- **403 Forbidden** : Permission insuffisante (RBAC).
- **404 Not Found** : Ressource inexistante.
- **409 Conflict** : État invalide (ex: déjà pointé).
- **422 Unprocessable Entity** : Échec de validation (FormRequest).
- **429 Too Many Requests** : Rate limit atteint.
- **500 Internal Server Error** : Erreur PHP/DB imprévue.

---

## 2. NIVEAUX DE LOGGING (SERVER)

Nous utilisons les niveaux PSR-3 via `Log::` :

- **EMERGENCY / ALERT** : Système instable (DB down, Redis down). *Notification Super Admin immédiate.*
- **ERROR** : Exception non gérée dans le code. *Tracé dans Sentry/Bugsnag.*
- **WARNING** : Tentative d'accès interdit, anomalies de pointage suspectes.
- **INFO** : Connexion utilisateur, validation de paie, export bancaire.
- **DEBUG** : Détails des requêtes (uniquement en local).

---

## 3. LOGS APPLICATIFS (AUDIT)

Ne pas confondre avec les logs système. Les **Audit Logs** sont stockés en base de données (Table `audit_logs`) et consultables par les administrateurs. Ils tracent **qui** a fait **quoi** sur **quelle ressource** (ex: modification de salaire).
