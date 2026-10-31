# Contrat d'API — Compte public (grand public)

- **Version** : 1.0 (proposition)
- **Date** : 31 octobre 2026 · **HEAD** : `31ef78e04`
- **Destinataire** : application mobile grand public, front `travel-web`, `front/web`
- **Guard** : `user_api` (provider `users`) — **jamais** `sanctum` (employés)

> ⚠️ **Statut** : ce document décrit **l'existant vérifié** et les **ajouts proposés**.
> Les routes marquées ✅ existent au HEAD audité ; celles marquées 🆕 sont **proposées**
> et ne sont pas encore implémentées. Aucune n'a été créée par cette mission.

---

## 0. Ce qui existe déjà (vérifié)

| Méthode | Route | Guard | Preuve |
|---|---|---|---|
| POST | `/api/v1/user/register` | public (`throttle:auth-sensitive`) | `routes/modules/user.php:20-24` |
| POST | `/api/v1/user/login` | public | idem |
| POST | `/api/v1/user/google-signin` | public | idem |
| GET | `/api/v1/user/me` | `user_api` | `:25-26` |
| PATCH | `/api/v1/user/profile` | `user_api` | `:27` |
| POST | `/api/v1/user/change-password` | `user_api` | `:28` |
| POST | `/api/v1/user/logout` | `user_api` | `:29` |
| PATCH | `/api/v1/user/personal-statuses` | `user_api` | `:32` |
| GET | `/api/v1/user/employee-links` | `user_api` | `:49` |
| POST | `/api/v1/user/company-requests` | `user_api` | `:40` |

**Et pour la marketplace travel** (guard distinct `travel_customer`) :
`/api/v1/public/travel/marketplace/account/{register,login,logout,me,bookings}` —
`routes/api.php:277-286`.

---

## 1. Ce qui manque pour l'application mobile

Trois manques, tous constatés dans le code (§1.5, §1.7 du rapport d'audit) :

1. **Pas de connexion par téléphone** — `LoginCodeController` n'accepte que l'e-mail
   (`:54-55`).
2. **Pas d'expiration de token** sur `UserAuthService::issueToken()` (`:145`) : le token
   rendu n'a **aucune échéance**.
3. **Pas de rafraîchissement** exposé — `RefreshTokenAction` existe
   (`app/Core/Auth/Application/Actions/RefreshTokenAction.php:52`) mais aucune route
   publique ne l'expose sous `user_api`.

---

## 2. Contrat proposé

### 2.1 Inscription

```http
POST /api/v1/user/register
Content-Type: application/json

{
  "first_name": "Fatou",
  "last_name": "Diallo",
  "email": "fatou@example.com",     // requis si phone absent
  "phone_e164": "+221770000000",    // 🆕 requis si email absent
  "password": "••••••••",
  "preferred_language": "fr"
}
```

**Réponses**

| Code | Corps | Cas |
|---|---|---|
| 201 | `{data:{user:{id,first_name,last_name,email,phone_e164,personal_statuses}}, token, token_type:"Bearer", token_expires_at}` | créé |
| 422 | `{error:"VALIDATION_ERROR", errors:{password:[…]}}` | politique de mot de passe (`PasswordPolicy`) |
| 422 | `{error:"EMAIL_ALREADY_USED"}` | e-mail déjà pris |
| 422 | `{error:"PHONE_ALREADY_USED"}` | 🆕 `phone_e164` déjà pris (index unique partiel, #8736) |
| 429 | `{error:"TOO_MANY_REQUESTS"}` | `throttle:auth-sensitive` |

> **Note de sécurité** : `EMAIL_ALREADY_USED` / `PHONE_ALREADY_USED` sont une
> **énumération assumée**. C'est un compromis : l'utilisateur doit savoir qu'il a déjà un
> compte. La mitigation est le rate limiting, pas le silence — un silence créerait des
> comptes fantômes.

### 2.2 Connexion par mot de passe

```http
POST /api/v1/user/login
{ "email": "fatou@example.com", "password": "••••••••", "device_name": "iPhone 15" }
```

| Code | Corps |
|---|---|
| 200 | `{data:{user}, token, token_type, token_expires_at}` |
| 401 | `{error:"INVALID_CREDENTIALS"}` |
| 423 | `{error:"ACCOUNT_LOCKED", locked_until}` (après `failed_login_attempts`) |

### 2.3 Connexion par code (OTP) — 🆕 **par téléphone**

C'est **l'ajout central** : aujourd'hui le code part par e-mail uniquement.

```http
POST /api/v1/user/login-code/request
{ "phone_e164": "+221770000000" }        // 🆕 accepté

→ 204 No Content
```

```http
POST /api/v1/user/login-code/verify
{ "phone_e164": "+221770000000", "code": "123456" }

→ 200 {data:{user}, token, token_type, token_expires_at}
→ 401 {error:"INVALID_CODE"}
→ 410 {error:"CODE_EXPIRED"}
→ 429 {error:"TOO_MANY_ATTEMPTS"}
```

**Règles reprises de l'existant** (`LoginCodeController`) :

- code **6 chiffres**, **haché** en cache (`auth:login_code:{sha256(clé)}`, `:294`),
  TTL 10 min ;
- vérification par `Hash::check` (`:164`) — jamais de comparaison en clair ;
- **un seul code actif** par identifiant (le nouveau écrase l'ancien).

**Règles NOUVELLES imposées par ce contrat** (parce que le SMS a un coût et un risque de
fraude) :

| Règle | Valeur | Raison |
|---|---|---|
| Anti-énumération | **204 toujours**, même si le numéro est inconnu | Ne pas révéler qu'un numéro a un compte |
| Limite par numéro | **3 codes / 15 min** | Coût SMS + harcèlement |
| Limite par IP | **10 codes / heure** | Robots |
| Backoff exponentiel | après 3 échecs de vérification → 1 min, 5 min, 15 min | Anti-brute-force |
| Coût | plafond quotidien par pays, alerte si dépassé | Fraude au numéro surtaxé |

> ⚠️ **Prérequis non négociable** : l'envoi SMS doit **réellement émettre**.
> Aujourd'hui `LogOnlySmsCodeSender` est un stub qui retourne un `traceId` sans appel HTTP
> (`LogOnlySmsCodeSender.php:119-128`) et fait **sauter l'e-mail**
> (`LoginCodeController.php:87,102`). Le contrat ci-dessus ne peut pas être honoré tant que
> ce stub n'est pas remplacé — voir lot L1 du plan.

### 2.4 Rafraîchissement

```http
POST /api/v1/user/refresh
Authorization: Bearer <token>          // token proche de l'expiration
→ 200 { token, token_expires_at }      // nouveau token, l'ancien est révoqué
→ 401 { error: "TOKEN_EXPIRED" }
```

**Règles** : rotation **systématique** (l'ancien token est supprimé dans la même
transaction), fenêtre de grâce 0 — pas de réutilisation. `RefreshTokenAction` existe déjà
et doit être **branché**, pas réécrit.

### 2.5 Révocation

```http
POST /api/v1/user/logout
Authorization: Bearer <token>
→ 204
```

```http
POST /api/v1/user/logout-all      // 🆕 révoque TOUS les appareils
→ 204
```

### 2.6 Suppression de compte

```http
DELETE /api/v1/user/account
Authorization: Bearer <token>
{ "password": "••••••••", "confirmation": "SUPPRIMER" }
```

| Code | Corps | Effet |
|---|---|---|
| 202 | `{status:"pending", purge_scheduled_at}` | suppression **différée** (30 j) |
| 401 | `{error:"INVALID_CREDENTIALS"}` | mot de passe requis |

**Règles** :

- **Ré-authentification obligatoire** (mot de passe ou code) — un token volé ne doit pas
  pouvoir supprimer un compte.
- **Suppression différée de 30 jours** : réversible, avec un e-mail de confirmation
  contenant un lien d'annulation. C'est la seule façon de survivre à une suppression
  accidentelle ou malveillante.
- **Anonymisation, pas effacement brutal** : les données de facturation/réservation sont
  **conservées** (obligation légale) avec les champs personnels purgés. Les `users` liés à
  des `employees` ne sont **jamais** supprimés — seul le lien est rompu.

---

## 3. Format d'erreur uniforme

```json
{
  "error": "CODE_MACHINE",
  "message": "Human readable (EN)",
  "localized_message": "Message localisé (fr/ar/tr/en)",
  "errors": { "field": ["…"] }
}
```

Repris de l'existant (`LoginRequest`, `localized_message` déjà utilisé par
`AuthController`).

---

## 4. Ce que ce contrat **ne** propose pas

- ❌ **Aucun changement** aux routes existantes : elles restent valides, sans rupture.
- ❌ **Aucun envoi de mot de passe en clair**, jamais, par aucun canal.
- ❌ **Aucune migration de données** : les comptes `users` existants restent tels quels ;
  `phone_e164` est nullable.
- ❌ **Aucune route `travel_customer` ajoutée** (ce guard est en dépréciation planifiée,
  voir ADR §4).

---

## 5. Séquencement recommandé

| Étape | Contenu | Bloquant ? |
|---|---|---|
| 1 | Corriger l'envoi OTP (stub SMS, expiration de token) | **Oui** — sinon le contrat est un mensonge |
| 2 | `refresh` + `logout-all` | Non (mais nécessaire à l'app) |
| 3 | OTP par téléphone | **Oui** pour le grand public mobile |
| 4 | `DELETE /account` | Non, mais RGPD |
