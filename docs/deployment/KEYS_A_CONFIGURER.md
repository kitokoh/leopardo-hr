# 🔑 Clés API à Configurer sur Render (ou autre hébergeur)

> ⚠️ À faire AVANT la mise en production. Ces variables sont absentes de `.env.example` ou non renseignées.
> Aller sur : **Render > Environment > Add Environment Variable**

---

## Stripe (Paiement en ligne)

| Variable | Description | Où trouver |
|---|---|---|
| `STRIPE_KEY` | Clé publique Stripe (pk_live_...) | dashboard.stripe.com → API Keys |
| `STRIPE_SECRET` | Clé secrète Stripe (sk_live_...) | dashboard.stripe.com → API Keys |
| `STRIPE_WEBHOOK_SECRET` | Secret pour vérifier les webhooks (whsec_...) | dashboard.stripe.com → Webhooks → signing secret |
| `STRIPE_PRICE_STARTER` | Price ID du plan Starter (price_...) | dashboard.stripe.com → Products → Starter |
| `STRIPE_PRICE_BUSINESS` | Price ID du plan Business (price_...) | dashboard.stripe.com → Products → Business |
| `STRIPE_PRICE_ENTERPRISE` | Price ID du plan Enterprise (price_...) | dashboard.stripe.com → Products → Enterprise |

**Webhook à enregistrer sur Stripe :**
```
https://gestionemployerbackend.onrender.com/api/v1/webhooks/stripe
```
Événements à écouter : `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`, `charge.refunded`

---

## Sentry (Monitoring des erreurs)

| Variable | Description | Où trouver |
|---|---|---|
| `SENTRY_LARAVEL_DSN` | DSN du projet Laravel Sentry | sentry.io → Settings → Projects → Leopardo API → DSN |
| `SENTRY_TRACES_SAMPLE_RATE` | Taux d'échantillonnage traces (0.1 recommandé) | Mettre `0.1` en prod |

**Pour le mobile Flutter :** Ajouter le DSN dans les secrets de build Firebase App Distribution ou dans les variables d'environnement de build.

---

## Emails (Mailer en production)

| Variable | Description | Où trouver |
|---|---|---|
| `MAIL_HOST` | SMTP host (ex: smtp.resend.com) | Resend / Mailgun / SES |
| `MAIL_PORT` | Port SMTP (587 TLS ou 465 SSL) | |
| `MAIL_USERNAME` | Identifiant SMTP | |
| `MAIL_PASSWORD` | Mot de passe / API Key SMTP | |
| `MAIL_FROM_ADDRESS` | Adresse expéditeur (ex: hello@leopardo-rh.com) | |
| `MAIL_FROM_NAME` | Nom expéditeur (Leopardo RH) | |

---

## Statut

- [ ] Stripe → keys renseignées sur Render
- [ ] Stripe → webhook enregistré et testé
- [ ] Sentry → DSN renseigné et premier événement reçu
- [ ] Mailer → SMTP configuré et email de bienvenue testé

---

## Notifications Push (Firebase FCM)

| Variable | Description | Où trouver |
|---|---|---|
| `FIREBASE_PROJECT_ID` | L'ID de votre projet Firebase (ex: leopardo-rh) | console.firebase.google.com → Project Settings |
| `FIREBASE_SERVER_KEY` | Clé serveur legacy (optionnelle selon implémentation) | Project Settings → Cloud Messaging |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | JSON de la Service Account (compressé sur 1 ligne) | Project Settings → Service Accounts → Generate new private key |

> **Astuce pour le JSON :** Pour mettre le fichier JSON sur une seule ligne afin de le copier dans Render, vous pouvez utiliser un outil en ligne comme "JSON Minifier" ou simplement supprimer les retours à la ligne.

---

## Google Connexion (OAuth / Socialite)

| Variable | Description | Où trouver |
|---|---|---|
| `GOOGLE_CLIENT_ID` | Identifiant client Google (se termine par .apps.googleusercontent.com) | console.cloud.google.com/apis/credentials |
| `GOOGLE_CLIENT_SECRET` | Code secret du client | Idem |
| `GOOGLE_REDIRECT_URL` | L'URL de callback de votre API | `https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback` |

> **Attention côté Google Cloud :** N'oubliez pas d'autoriser l'URL de redirection exacte `https://gestionemployerbackend.onrender.com/api/v1/auth/google/callback` dans les paramètres "Origines JavaScript autorisées" et "URI de redirection autorisés" du Client ID OAuth.

---

*Créé le 2026-07-12 — À supprimer ou archiver après configuration.*
