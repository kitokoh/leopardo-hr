# Runbook — Configuration du Super Admin en Production

> **Contexte** : Le compte super-admin (`admin@leopardo-rh.com`) est créé par
> `SuperAdminSeeder` à chaque déploiement. Si la variable `SUPER_ADMIN_PASSWORD`
> n'est pas renseignée dans le dashboard Render, le seeder génère un mot de
> passe aléatoire qui n'est **jamais affiché en production** → login impossible.

---

## Symptôme

```
POST /api/v1/platform/auth/login
→ 401 INVALID_CREDENTIALS
```

L'admin dashboard (`leo-admin.pages.dev`) est inaccessible.

---

## Correction (5 minutes)

### Étape 1 — Render Dashboard

1. Aller sur **https://dashboard.render.com**
2. Sélectionner le service **`gestionemployerbackend`**
3. Onglet **Environment**
4. Localiser la variable `SUPER_ADMIN_PASSWORD` (déjà déclarée, valeur vide)
5. Cliquer sur le crayon et saisir un mot de passe fort (≥ 16 caractères, mixte)
6. **Save Changes**

> `FORCE_SUPER_ADMIN_PASSWORD_RESET=true` est déjà configuré dans `render.yaml`
> — le prochain déploiement réinitialisera automatiquement le hash.

### Étape 2 — Déclencher un déploiement

Depuis l'onglet **Deploys** du service, cliquer **Deploy latest commit**,
ou pousser un commit sur `main`.

### Étape 3 — Vérifier

```bash
curl -s -X POST https://gestionemployerbackend.onrender.com/api/v1/platform/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@leopardo-rh.com","password":"<TON_MOT_DE_PASSE>"}'
# Attendu : HTTP 200 + token
```

---

## Après connexion

1. Se connecter sur [leo-admin.pages.dev](https://leo-admin.pages.dev)
2. **Changer le mot de passe** depuis les paramètres du compte super-admin
3. Remettre `SUPER_ADMIN_PASSWORD` à vide dans Render (ou conserver pour rollback)

---

## Variables Render liées au super admin

| Variable | Valeur | Description |
|---|---|---|
| `SUPER_ADMIN_EMAIL` | `admin@leopardo-rh.com` | Email du compte (peut être changé) |
| `SUPER_ADMIN_PASSWORD` | *(à saisir)* | Mot de passe initial — **sync: false** |
| `FORCE_SUPER_ADMIN_PASSWORD_RESET` | `true` | Réinitialise le hash à chaque déploiement si PASSWORD est défini |

---

## Variables Render pour activer les emails (OTP trial signup)

Sans ces variables, le flux OTP trial/signup retourne 500 silencieux.

| Variable | Exemple | Note |
|---|---|---|
| `MAIL_HOST` | `smtp.resend.com` | Fournisseur SMTP (Resend, Mailgun, SES…) |
| `MAIL_USERNAME` | `resend` | Login SMTP |
| `MAIL_PASSWORD` | `re_xxxx` | Clé API SMTP — **sync: false** |
| `MAIL_PORT` | `587` | Déjà configuré dans render.yaml |
| `MAIL_ENCRYPTION` | `tls` | Déjà configuré |
| `MAIL_FROM_ADDRESS` | `noreply@leopardo-rh.com` | Déjà configuré |

---

## Variables Render pour activer Stripe (billing)

| Variable | Note |
|---|---|
| `STRIPE_SECRET_KEY` | Clé secrète Stripe (`sk_live_…`) — **sync: false** |
| `STRIPE_WEBHOOK_SECRET` | Secret du webhook Stripe (`whsec_…`) — **sync: false** |
| `STRIPE_PRICE_STARTER` | ID du price Stripe pour le plan Pilot |
| `STRIPE_PRICE_BUSINESS` | ID du price Stripe pour le plan Operations |

---

*Dernière mise à jour : 2026-08-16 — Neo*
