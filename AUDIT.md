# 🔍 AUDIT — Leopardo RH
> Généré le 2026-07-01 | Auteur : audit automatisé ooks/KiloClaw  
> Périmètre : API (Laravel), front/web (Next.js), front/admin-dashboard (Vue), apps mobiles Flutter, CI/CD GitHub Actions

> ⚠️ **Revue de suivi — 2026-07-05** : plusieurs points ci-dessous ont été corrigés dans le code depuis la génération initiale (voir checklist finale pour le détail à jour). Ce document décrit l'état constaté au 2026-07-01 ; ne pas retraiter les points déjà marqués `[x]` en fin de fichier sans vérifier le code courant.

---

## 📋 Résumé Exécutif

| Domaine | API | Mobile | Web (vitrine) | Admin Web | État global |
|---------|:---:|:------:|:-------------:|:---------:|:-----------:|
| Notifications push (FCM) | ✅ | ✅ | — | ✅ SSE | ⚠️ Vars manquantes |
| Redis (cache/queue) | ✅ | — | — | — | ⚠️ Config partielle |
| Envoi d'emails | ✅ | — | — | — | ⚠️ SMTP prod vide |
| Paiement (Stripe) | ✅ | ❌ non applicable | ✅ | ❌ absent | ⚠️ Vars + signature |
| Google OAuth | ✅ | ✅ | ⚠️ Partiel | ❌ absent | ❌ Vars API manquantes |
| CI/CD | — | — | — | — | ❌ Bugs + sur-déclenchement |

---

## 1. 🔔 Notifications Push (FCM / SSE)

### État côté API ✅
- `PushNotificationService.php` : envoi FCM via HTTP API Firebase (sans SDK)
- `SendPushNotificationJob.php` + `SendBulkNotificationsJob.php` : jobs queués via Redis
- `NotificationController` + `NotificationPreferenceController` : gestion des préférences
- `NotificationStreamController` : flux SSE temps réel pour l'admin web
- Module `Notification` en architecture DDD sous `app/Modules/Notification/`
- Modèle `DeviceToken` pour stocker les tokens FCM par employé

### État côté Mobile ✅
- `leopardo_core/lib/core/services/push_notification_service.dart` : initialise Firebase Messaging + flutter_local_notifications
- `push_notification_repository.dart` : register/unregister device token via API
- Dépendances présentes dans tous les pubspecs (`firebase_messaging: ^15.2.5`)
- `google-services.json` (Android) + `GoogleService-Info.plist` (iOS) présents pour : employee, manager, hr, platform_admin

### État côté Admin Web (Vue) ✅
- `useNotificationStream.js` : SSE vers `/api/v1/notifications/stream`
- `NotificationPanel.vue` : affichage + compteur non-lus

### ❌ Manquements critiques

#### 1.1 Variables d'environnement Firebase absentes du `.env.example`
Le fichier `api/.env.example` ne contient **aucune** des variables Firebase nécessaires.

**Action manuelle à faire dans `api/.env.example`** puis dans l'env Render :
```dotenv
# Firebase / FCM — Push notifications
# Obtenir depuis Firebase Console > Project Settings > Service Accounts
FIREBASE_SERVER_KEY=AAAA...votre_server_key_legacy_ou_unused
FIREBASE_PROJECT_ID=leopardo-rh
# Coller ici le JSON complet de la service account (format minifié sur une ligne)
FIREBASE_SERVICE_ACCOUNT_JSON={"type":"service_account","project_id":"leopardo-rh",...}
```
> Le `PushNotificationService.php` utilise `config('services.firebase.project_id')` et `config('services.firebase.credentials')` — ces deux variables sont **requises** pour que les pushs fonctionnent en production.

#### 1.2 Secrets GitHub Actions manquants pour la distribution Firebase
Le workflow `deploy-main.yml` utilise `FIREBASE_TOKEN`, `FIREBASE_*_ANDROID_APP_ID`, `FIREBASE_SERVICE_ACCOUNT_JSON` pour la distribution via Firebase App Distribution.

**À ajouter dans Settings > Secrets du repo GitHub :**
| Secret | Valeur à obtenir |
|--------|-----------------|
| `FIREBASE_TOKEN` | `firebase login:ci` |
| `FIREBASE_EMPLOYEE_ANDROID_APP_ID` | Firebase Console > leopardo_employee > App ID Android |
| `FIREBASE_MANAGER_ANDROID_APP_ID` | Firebase Console > leopardo_manager > App ID Android |
| `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` | Firebase Console > leopardo_platform_admin > App ID Android |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | Firebase Console > Project Settings > Service Accounts > Generate new key |

#### 1.3 Token SSE transmis en query parameter (sécurité)
Dans `useNotificationStream.js` :
```js
const url = `${apiUrl}/api/v1/notifications/stream?token=${encodeURIComponent(token)}`
```
EventSource ne supporte pas les headers custom → token exposé dans les logs serveur.  
**Recommandation** : utiliser un token SSE courte durée via un endpoint dédié `POST /sse-token` et passer ce token. Acceptable en dev, à corriger avant passage en prod.

---

## 2. 🟥 Redis (Cache / Queue / Session)

### État côté API ✅
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` dans `.env.example`
- Upstash Redis TLS (URL `rediss://…`) préconfiguré dans `.env.example`
- 6 queues Redis spécialisées : `redis-notifications`, `redis-pdf`, `redis-payroll`, `redis-documents`, `redis-webhooks`, `redis`

### ❌ Manquements

#### 2.1 `REDIS_CLIENT=predis` — vérifier la dépendance Composer
Le `.env.example` fixe `REDIS_CLIENT=predis`. Vérifier que `predis/predis` est bien dans `composer.json` :

```bash
cd api && composer show predis/predis
```
Si absent :
```bash
composer require predis/predis
```

#### 2.2 Workers Redis non démarrés sur Render (probable)
Le déploiement via Render webhook (`RENDER_DEPLOY_HOOK_URL`) déclenche un re-deploy du service web mais **ne démarre pas automatiquement un worker de queue**.

**Action manuelle sur Render** :
1. Créer un service **Background Worker** séparé sur Render pointant vers le même repo
2. Commande de démarrage : `php artisan queue:work redis --queue=redis-notifications,redis-pdf,redis-payroll --tries=3 --sleep=3`
3. Optionnel : workers dédiés par queue pour isoler les priorités

> Sans worker actif, les jobs (pushs, emails, documents PDF) restent en attente indéfiniment.

#### 2.3 Redis Upstash — credentials engagées dans `.env.example` public
Le `.env.example` contenait une URL Redis Upstash **avec mot de passe en clair** (valeur réelle retirée de ce document le 2026-07-19, voir `SECURITY_INCIDENT_REDIS_2026-07.md` pour le détail de la remédiation et le statut de rotation) :
```
REDIS_URL=rediss://default:<REDACTED>@<REDACTED_HOST>.upstash.io:6379
```
Ce fichier est public sur GitHub. **Changer immédiatement ce mot de passe** dans Upstash Dashboard > Reset Password, puis mettre à jour les secrets Render et `.env.example` avec un placeholder.

---

## 3. 📧 Envoi d'Emails

### État côté API ✅
- `config/mail.php` configuré SMTP
- `PaymentFailedMail.php` + template Blade `payment-failed.blade.php`
- CI `tests.yml` envoie un rapport email via `dawidd6/action-send-mail`

### ❌ Manquements

#### 3.1 `MAIL_MAILER=log` par défaut dans `config/mail.php`
```php
'default' => env('MAIL_MAILER', 'log'),
```
Si `MAIL_MAILER` n'est pas défini dans `.env`, les emails partent dans les logs au lieu d'être envoyés. Le `.env.example` corrige ça avec `MAIL_MAILER=smtp`, mais si l'env n'est pas configuré sur Render l'email ne sera jamais envoyé.

**Action** : Dans Render Dashboard, ajouter les variables d'environnement :
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.votre-fournisseur.com
MAIL_PORT=587
MAIL_USERNAME=votre_username
MAIL_PASSWORD=votre_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@leopardo-rh.com
MAIL_FROM_NAME="Leopardo RH"
```
Fournisseurs recommandés : **Resend**, **Mailgun**, **Postmark** (gratuits en faible volume).

#### 3.2 Mailables manquants pour les flux métier essentiels
Seul `PaymentFailedMail` est présent. Les emails suivants **n'ont pas de Mailable dédié** :

| Email attendu | Mailable | Template Blade |
|---------------|:--------:|:--------------:|
| Bienvenue employé (onboarding) | ❌ | ❌ |
| Invitation à rejoindre l'entreprise | ❌ | ❌ |
| Réinitialisation mot de passe | ⚠️ (Laravel natif) | ⚠️ (générique) |
| Confirmation d'abonnement Stripe | ❌ | ❌ |
| Expiration de licence (Edge) | ❌ | ❌ |

**Pour chaque email à créer** :
```bash
cd api
php artisan make:mail WelcomeEmployeeMail --markdown=emails.welcome-employee
php artisan make:mail InvitationMail --markdown=emails.invitation
php artisan make:mail SubscriptionConfirmedMail --markdown=emails.subscription-confirmed
```

---

## 4. 💳 Paiement (Stripe + Chargily)

### État côté API ✅
- `StripeService.php` : Checkout Session, Customer Portal, gestion des plans
- `StripeWebhookController.php` : `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`
- Chargily webhook : `PaymentWebhookController::chargily()` → `checkout.paid`
- Routes publiques : `POST /webhooks/stripe`, `POST /webhooks/chargily`
- `BulkPaymentController` + `ProcessBulkPaymentJob` pour les paiements salariaux

### État côté Web (front/web) ✅
- `src/app/api/billing/checkout/route.ts` : crée une session Stripe via le backend + mode sandbox si `STRIPE_SECRET_KEY` absent

### ❌ Manquements

#### 4.1 Variables Stripe absentes des .env (API + Web)
**Dans `api/.env.example`** (déjà présentes mais vides → à compléter sur Render) :
```dotenv
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_BUSINESS=price_...
STRIPE_PRICE_ENTERPRISE=price_...
```

**Dans `front/web` — fichier `.env.local` à créer** (actuellement inexistant) :
```dotenv
STRIPE_SECRET_KEY=sk_live_...
LEOPARDO_API_URL=https://gestionemployerbackend.onrender.com
NEXT_PUBLIC_API_URL=https://gestionemployerbackend.onrender.com/api/v1
```
> Créer aussi `front/web/.env.local.example` pour documenter ces variables.

#### 4.2 Signature Stripe non vérifiée dans le webhook (Modules version)
`app/Modules/Billing/Interfaces/Api/V1/PaymentWebhookController.php` :
```php
public function stripe(Request $request): JsonResponse
{
    $payload = $request->all(); // ❌ pas de vérification de signature
```
Un attaquant peut envoyer un faux webhook Stripe et activer/désactiver des abonnements.

**Correction à appliquer** :
```php
public function stripe(Request $request): JsonResponse
{
    $payload = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $webhookSecret = config('services.stripe.webhook_secret');

    try {
        // Vérification manuelle (pas de SDK) :
        [$timestamp, $v1] = $this->parseStripeSignature($sigHeader);
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);
        if (!hash_equals($expectedSig, $v1)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Webhook error'], 400);
    }
    // suite du traitement...
}
```

#### 4.3 Chargily : aucune variable dans `.env.example`
Le webhook Chargily est implémenté mais les clés API Chargily sont absentes du `.env.example`.

**Action — ajouter dans `api/.env.example`** :
```dotenv
# Chargily — Payment provider (Algérie)
# https://pay.chargily.net/test/dashboard (test) | https://pay.chargily.net/dashboard (prod)
CHARGILY_API_KEY=
CHARGILY_WEBHOOK_SECRET=
CHARGILY_MODE=test  # test | live
```
Et implémenter la vérification de signature Chargily (HMAC-SHA256) dans le webhook.

#### 4.4 Mobile : pas d'interface de paiement
Les apps mobiles Flutter n'ont pas d'écran de paiement. Seul le modèle `PaymentDocument` est présent.  
**Décision à prendre** : est-ce intentionnel (paiement réservé au web) ? Si oui, documenter dans le README mobile. Si non, intégrer `flutter_stripe` ou Chargily SDK Flutter.

---

## 5. 🔑 Connexion Google OAuth

### État côté API ✅
- Routes : `GET /auth/google`, `GET /auth/google/callback`, `POST /auth/google/token`
- Laravel Socialite configuré dans `config/services.php`
- `AuthGoogleSignInTest.php` : test feature avec mock Socialite

### État côté Mobile ✅
- `google_sign_in: ^7.2.0` dans pubspec de toutes les apps
- `loginWithGoogle()` implémenté dans `auth_repository.dart` (employee, manager, hr)
- `google-services.json` (Android) + `GoogleService-Info.plist` (iOS) présents

### État côté Web (front/web) ⚠️ Partiel
- La page de login `src/app/auth/login/page.tsx` existe (email/password)
- **Aucun bouton "Se connecter avec Google"** dans la page de login web
- No NextAuth, no `@next-auth/google` provider configuré

### État côté Admin dashboard (Vue) ❌ Absent
- Aucun OAuth Google dans l'admin-dashboard

### ❌ Manquements critiques

#### 5.1 Variables Google OAuth absentes du `api/.env.example`
```dotenv
# Google OAuth (Socialite)
# https://console.cloud.google.com/apis/credentials
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URL=https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback
```
**Ces 3 variables sont requises mais complètement absentes du `.env.example`.** Sans elles, toute connexion Google retourne une erreur 500.

#### 5.2 Étapes manuelles pour configurer Google OAuth

**1. Créer le projet Google :**
1. Aller sur https://console.cloud.google.com/apis/credentials
2. `+ Créer des identifiants` → `ID client OAuth 2.0`
3. Type : **Application Web**
4. Origines autorisées : `https://gestionemployerbackend.onrender.com`
5. URI de redirection autorisés : `https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback`
6. Copier `Client ID` et `Client Secret`

**2. Pour le mobile (Web Client ID type 3 Firebase) :**
1. Firebase Console > ton projet > Project Settings > General > Tes apps > Android > Download `google-services.json`
2. Dans `main.dart` de chaque app :
```dart
await GoogleSignIn.instance.initialize(
  serverClientId: 'VOTRE_WEB_CLIENT_ID_FIREBASE_TYPE_3.apps.googleusercontent.com',
);
```
Le `serverClientId` doit correspondre au `client_id` de type `3` (Web client) dans `google-services.json`.

**3. Pour le web (front/web — si connexion Google souhaitée) :**
Ajouter dans `front/web/src/app/auth/login/page.tsx` :
```tsx
// Bouton Google OAuth
<button onClick={() => window.location.href = `${process.env.NEXT_PUBLIC_API_URL}/auth/google`}>
  Se connecter avec Google
</button>
```
Ou intégrer NextAuth avec le provider Google.

#### 5.3 App employee : Google Sign-In non exposé dans l'UI
`leopardo_employee` a le code `loginWithGoogle()` dans le repository mais **aucun bouton Google** dans l'écran de login. À vérifier et compléter si nécessaire.

---

## 6. ⚙️ CI/CD — Bugs et sur-déclenchement

### 6.1 ❌ `tests.yml` déclenché sur CHAQUE push (pas de filtre de chemin)

**Problème** : Le workflow `tests.yml` se déclenche sur tout push vers `main` et `develop`, même si seul un `README.md` a changé. Cela gaspille des minutes CI et déclenche `deploy-main.yml` (qui déclenche un déploiement Render) même pour des changements non-fonctionnels.

**Correction appliquée** → voir section *Fichiers CI corrigés* ci-dessous.

### 6.2 ❌ Pattern `web_changed` incorrect dans `tests.yml`

**Problème** : Le script de détection cherche `^(admin-dashboard/|…)` alors que le chemin réel dans le repo est `front/admin-dashboard/`. `web_changed` est donc **toujours false**, ce qui signifie que le déploiement ne tient jamais compte des changements admin, et ne bloque jamais un déploiement quand le front admin est cassé.

```bash
# actuel (bugué) :
grep -Eq '^(admin-dashboard/|\.github/workflows/web-ci\.yml)'

# corrigé :
grep -Eq '^(front/admin-dashboard/|\.github/workflows/web-ci\.yml)'
```

### 6.3 ❌ `web-marketing-ci.yml` se déclenche sur `develop` et `staging`

**Problème** : La vitrine client (Next.js `front/web`) se compile à chaque push vers `develop` et `staging` en plus de `main`. Le build E2E (Playwright) est long.

**Correction appliquée** : push trigger limité à `main` uniquement. Les PR vers `main` depuis `develop`/`staging` déclenchent encore le CI via `pull_request`.

### 6.4 ⚠️ `deploy-main.yml` déclenché à chaque complétion de `tests.yml`

**Comportement actuel** : `deploy-main.yml` se déclenche via `workflow_run` dès que `tests.yml` se termine (même quand tous les jobs sont skippés). Sur `main`, chaque push déclenche donc un déploiement Render même si rien d'important n'a changé.

**Recommandation** : Ajouter un chemin minimum dans `deploy-main.yml` ou accepter ce comportement si Render gère l'idempotence.

---

## 📝 Fichiers CI corrigés

### `web-marketing-ci.yml` — Restriction du push à `main` uniquement

Changer le bloc `on:` de :
```yaml
on:
  workflow_dispatch:
  push:
    branches: [main, develop, staging]
    paths:
      - 'front/web/**'
      - '.github/workflows/web-marketing-ci.yml'
  pull_request:
    branches: [main, develop, staging]
    paths:
      - 'front/web/**'
      - '.github/workflows/web-marketing-ci.yml'
```

En :
```yaml
on:
  workflow_dispatch:
  push:
    branches: [main]                         # ← seulement main
    paths:
      - 'front/web/**'
      - '.github/workflows/web-marketing-ci.yml'
  pull_request:
    branches: [main]                         # ← PRs vers main uniquement
    paths:
      - 'front/web/**'
      - '.github/workflows/web-marketing-ci.yml'
```

### `tests.yml` — Ajout d'un filtre de chemin + correction du pattern web_changed

Changer le bloc `on:` de :
```yaml
on:
  workflow_dispatch:
  pull_request:
    branches: [develop, main]
  push:
    branches: [develop, main]
```

En :
```yaml
on:
  workflow_dispatch:
  pull_request:
    branches: [develop, main]
    paths:
      - 'api/**'
      - 'front/admin-dashboard/**'
      - '.github/workflows/tests.yml'
      - '.github/workflows/phpstan-baseline.yml'
  push:
    branches: [develop, main]
    paths:
      - 'api/**'
      - 'front/admin-dashboard/**'
      - '.github/workflows/tests.yml'
      - '.github/workflows/phpstan-baseline.yml'
```

Et dans le step `Detect changed directories`, corriger le pattern `web_changed` :
```bash
# De (bugué) :
if printf '%s\n' "${changed_files}" | grep -Eq '^(admin-dashboard/|\.github/workflows/web-ci\.yml)'; then

# En (corrigé) :
if printf '%s\n' "${changed_files}" | grep -Eq '^(front/admin-dashboard/|\.github/workflows/web-ci\.yml)'; then
```

---

## 🔐 Secrets GitHub Actions — Récapitulatif complet

Aller dans **Settings > Secrets and variables > Actions > New repository secret** et ajouter :

### Secrets (valeurs sensibles)
| Nom | Description | Où obtenir |
|-----|-------------|------------|
| `RENDER_DEPLOY_HOOK_URL` | URL webhook de déploiement Render (API) | Render Dashboard > Service > Deploy Hook |
| `RENDER_ROLLBACK_HOOK_URL` | URL webhook de rollback Render | Render Dashboard > Service |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe | dashboard.stripe.com/apikeys |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature webhook Stripe | Stripe > Webhooks > Signing secret |
| `FIREBASE_TOKEN` | Token CI Firebase | `firebase login:ci` |
| `FIREBASE_EMPLOYEE_ANDROID_APP_ID` | App ID Firebase Android (employee) | Firebase Console |
| `FIREBASE_MANAGER_ANDROID_APP_ID` | App ID Firebase Android (manager) | Firebase Console |
| `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` | App ID Firebase Android (platform_admin) | Firebase Console |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | JSON service account Firebase | Firebase Console > Project Settings > Service Accounts |
| `CI_SMTP_SERVER` | Serveur SMTP pour rapports CI | Ex: `smtp.mailgun.org` |
| `CI_SMTP_USERNAME` | Username SMTP CI | |
| `CI_SMTP_PASSWORD` | Password SMTP CI | |
| `API_HEALTHCHECK_URL` | URL de healthcheck API post-deploy | Ex: `https://…/api/v1/health` |

### Variables (valeurs non sensibles)
| Nom | Valeur suggérée |
|-----|-----------------|
| `CI_REPORT_TO` | `ton-email@example.com` |
| `CI_REPORT_FROM` | `ci@leopardo-rh.com` |
| `BACKEND_COVERAGE_MIN` | `60` |

---

## 🔧 Variables d'environnement Render — Récapitulatif

Aller dans **Render Dashboard > ton service API > Environment** et ajouter/vérifier :

```dotenv
# Google OAuth
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URL=https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback

# Firebase / FCM
FIREBASE_PROJECT_ID=leopardo-rh
FIREBASE_SERVICE_ACCOUNT_JSON={"type":"service_account",...}  # JSON minifié

# Stripe
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_STARTER=price_...
STRIPE_PRICE_BUSINESS=price_...
STRIPE_PRICE_ENTERPRISE=price_...

# Chargily
CHARGILY_API_KEY=...
CHARGILY_WEBHOOK_SECRET=...

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...

# Redis — CHANGER après rotation du mot de passe Upstash
REDIS_URL=rediss://default:NOUVEAU_MDP@noted-tomcat-92597.upstash.io:6379
REDIS_PASSWORD=NOUVEAU_MDP
```

---

## 📦 Checklist finale (mise à jour 2026-07-05)

> Vérifié contre le code de `main` au 2026-07-05. Les points déjà implémentés sont cochés
> avec la référence qui le prouve ; ne pas les retraiter sans re-vérifier le code.

```
[x] Signature Stripe vérifiée — StripeWebhookController + StripeService::verifyWebhookSignature()
[x] Signature Chargily vérifiée — PaymentWebhookController (header X-Chargily-Signature)
[x] Google Sign-In présent — leopardo_employee user_login_screen.dart (_buildGoogleButton) et login_screen.dart
[x] Mailables créés — WelcomeEmployeeMail, InvitationMail/UserInvitationMail, SubscriptionConfirmedMail, TrialWelcomeMail, TrialDripMail, TrialExpiringMail, LicenseExpiringMail, RoleAssignmentMail, CabinetShareMail
[x] GOOGLE_CLIENT_ID/SECRET/REDIRECT_URL présents dans api/.env.example
[x] FIREBASE_PROJECT_ID/SERVER_KEY/SERVICE_ACCOUNT_JSON présents dans api/.env.example
[x] CHARGILY_API_KEY/WEBHOOK_SECRET/MODE présents dans api/.env.example
[x] Background Worker Render pour la queue Redis — render.yaml (`leopardo-queue-worker`, `php artisan queue:work redis`)
[x] CI web-marketing-ci.yml restreint à push/PR vers main uniquement, avec paths filter
[x] CI tests.yml a un paths filter et le pattern web_changed pointe bien vers front/admin-dashboard/
[x] Token SSE non exposé en query param — useNotificationStream.js échange désormais le bearer token
    contre un jeton SSE à usage unique via POST /api/v1/notifications/sse-token avant d'ouvrir l'EventSource
    (endpoint backend SseTokenController déjà présent, coté frontend corrigé le 2026-07-05)
[x] front/web/.env.local.example créé (STRIPE_SECRET_KEY, LEOPARDO_API_URL, NEXT_PUBLIC_API_URL, etc.)
[~] 🔴 URGENT — Rotation du mot de passe Redis Upstash. Un vrai mot de passe Upstash a été committé
    en clair dans l'historique git (valeur retirée de ce document le 2026-07-19 — voir
    `SECURITY_INCIDENT_REDIS_2026-07.md` pour la référence de commit exacte et le statut de remédiation)
    et reste récupérable par quiconque clone le repo (repo public) tant que l'historique n'est pas purgé.
    Documentation nettoyée le 2026-07-19 (ce fichier + PLAN_ACTION*) : le mot de passe en clair n'apparaît
    plus dans aucun fichier Markdown suivi. Reste à faire, hors du périmètre code (action manuelle) :
    reset password dans le dashboard Upstash, mise à jour REDIS_URL/REDIS_PASSWORD sur Render, puis purge
    de l'historique git (BFG/filter-repo) une fois la rotation confirmée — voir le rapport pour le détail.
    L'historique git lui-même ne peut pas être nettoyé sans rewrite (BFG/filter-repo) coordonné avec l'équipe.
[ ] Ajouter les secrets GitHub Actions listés ci-dessus dans Settings > Secrets — action manuelle GitHub,
    hors du périmètre code (aucun moyen de vérifier depuis le repo si déjà fait)
```


