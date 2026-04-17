# 📋 RAPPORT DE DÉPLOIEMENT — LEOPARDO RH BACKEND
**Date :** 2026-04-13  
**Version :** 4.1.34  
**Auteur :** Antigravity (IA) + kitokoh  
**Statut :** ✅ DÉPLOYÉ ET OPÉRATIONNEL

---

## 1. 🎯 Résumé Exécutif

Le backend **Leopardo RH** (API Laravel 11 / FrankenPHP) est désormais hébergé sur le cloud
via le couple **Render.com (Web Service) + Neon.tech (PostgreSQL)**.

| Composant | Avant | Après |
| :--- | :--- | :--- |
| Hébergeur | o2switch (mutualisé) | **Render.com (Docker)** |
| Base de données | MySQL o2switch | **Neon.tech (PostgreSQL 16)** |
| Serveur PHP | PHP-FPM + Nginx | **FrankenPHP 1.x (Caddy intégré)** |
| Coût | Payant | **100% Gratuit (phase Dev)** |
| URL de production | *(indisponible)* | **https://gestionemployerbackend.onrender.com** |

---

## 2. 🌐 URL et Accès API

**URL de base (production) :**
```
https://gestionemployerbackend.onrender.com
```

**Endpoint de health-check :**
```
GET https://gestionemployerbackend.onrender.com/api/health
```

**Endpoint d'authentification (mobile) :**
```
POST https://gestionemployerbackend.onrender.com/api/v1/auth/login
Body: { "email": "...", "password": "..." }
```

> [!IMPORTANT]
> Le plan **Gratuit de Render** met le service en veille après 15 minutes d'inactivité.
> Le **premier appel** après une période d'inactivité peut prendre **30 à 60 secondes**.
> C'est un comportement normal en phase de développement. En production payante, ce délai disparaît.

---

## 3. 🔑 Variables d'Environnement (Render Dashboard)

Ces variables sont configurées dans le tableau de bord Render et ne doivent **jamais** être commitées dans le code.

| Clé | Valeur |
| :--- | :--- |
| `APP_KEY` | `*** secret Render - ne jamais documenter en clair ***` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | `postgresql://<user>:<password>@<host>/<database>?sslmode=require` |
| `RUN_MIGRATIONS` | `true` |

---

## 4. 👤 Identifiants de Test (Comptes par Défaut)

> [!WARNING]
> Ces identifiants sont pour l'environnement de **développement uniquement**.
> Ils doivent être modifiés avant tout passage en production réelle.

### 4.1 Super Admin (Portail d'Administration)
> Ce compte n'est **pas** accessible depuis l'application mobile.
> Il est réservé à l'administration de la plateforme multi-tenant.

| Champ | Valeur |
| :--- | :--- |
| **Email** | `admin@leopardo-rh.com` |
| **Mot de passe** | *(défini via var. env. `SUPER_ADMIN_PASSWORD`)* |
| **Endpoint** | `POST /api/v1/super-admin/login` |

---

### 4.2 Comptes Employés / Testeurs (Application Mobile)

> [!TIP]
> Ces comptes sont créés par le **DemoCompanySeeder** pour le local et le test.
> En production Render, ne pas lancer ce seeder tant qu'un seeder de démonstration explicitement autorisé en production n'existe pas.

**Entreprise de démo :** TechCorp Algérie SARL  
**Endpoint de login :** `POST /api/v1/auth/login`

#### 🔴 Rôle : Manager Principal
| Champ | Valeur |
| :--- | :--- |
| **Nom** | Ahmed Benali |
| **Matricule** | EMP-001 |
| **Email** | `ahmed.benali@techcorp-algerie.dz` |
| **Mot de passe** | `*** communiquer hors repo / a faire tourner si depot public ***` |
| **Permissions** | Tout (validation absences, paies, équipe) |

#### 🟠 Rôle : Manager RH
| Champ | Valeur |
| :--- | :--- |
| **Nom** | Fatima Meziane |
| **Matricule** | EMP-002 |
| **Email** | `fatima.meziane@techcorp-algerie.dz` |
| **Mot de passe** | `*** communiquer hors repo / a faire tourner si depot public ***` |
| **Permissions** | Gestion RH (congés, absences, avances) |

#### 🟢 Rôle : Employé (×5 comptes disponibles)
| Nom | Email | Mot de passe |
| :--- | :--- | :--- |
| Karim Aouad | `karim.aouad@techcorp-algerie.dz` | `*** communiquer hors repo / a faire tourner si depot public ***` |
| Amina Brahimi | `amina.brahimi@techcorp-algerie.dz` | `*** communiquer hors repo / a faire tourner si depot public ***` |
| Youcef Kaddour | `youcef.kaddour@techcorp-algerie.dz` | `*** communiquer hors repo / a faire tourner si depot public ***` |
| Nadia Tlemçani | `nadia.tlemcani@techcorp-algerie.dz` | `*** communiquer hors repo / a faire tourner si depot public ***` |
| Mehdi Saadi | `mehdi.saadi@techcorp-algerie.dz` | `*** communiquer hors repo / a faire tourner si depot public ***` |

---

## 5. 🛠️ Problèmes Rencontrés et Solutions

### Bug #1 — Tag Docker inexistant
| | |
| :--- | :--- |
| **Erreur** | `dunglas/frankenphp:latest-php8.4-alpine` introuvable |
| **Cause** | Le tag `latest-php8.4-alpine` n'existe pas sur Docker Hub |
| **Solution** | Remplacé par `dunglas/frankenphp:php8.4-alpine` |

### Bug #2 — Connexion DB refusée (127.0.0.1:5432)
| | |
| :--- | :--- |
| **Erreur** | `Connection refused 127.0.0.1:5432` |
| **Cause** | `php artisan config:cache` exécuté **pendant le build** Docker, avant l'injection des variables Render. Laravel mémorisait `DB_HOST=localhost`. |
| **Solution** | Déplacement des commandes `config:cache`, `route:cache`, `view:cache` vers le `docker-entrypoint.sh` (exécuté **au démarrage** du conteneur, quand les vars sont disponibles) |

### Bug #3 — Operation not permitted (EPERM 126)
| | |
| :--- | :--- |
| **Erreur** | `/usr/local/bin/docker-entrypoint.sh: exec: line 14: frankenphp: Operation not permitted` |
| **Cause** | L'image officielle FrankenPHP installe le binaire avec la capability Linux `cap_net_bind_service=+ep`. Render exécute les conteneurs en mode **"No New Privileges" (NoNewPrivs)** strict, le noyau refuse de lancer tout binaire avec des capabilities additionnelles. |
| **Solution** | Ajout d'un `RUN cp /usr/local/bin/frankenphp ... && mv ...` dans le Dockerfile pour purger les capabilities du binaire par copie/déplacement. |

### Bug #4 — Port 80 non autorisé
| | |
| :--- | :--- |
| **Erreur** | FrankenPHP essayait d'écouter sur le port 80 (port privilégié) |
| **Cause** | Comportement par défaut de FrankenPHP/Caddy |
| **Solution** | Ajout de `export SERVER_NAME=":${PORT:-8080}"` dans l'entrypoint pour forcer l'écoute sur le port dynamique fourni par Render |

### Bug #5 — `/login` retourne 500 alors que l'API health est OK
| | |
| :--- | :--- |
| **Symptôme** | `GET /api/v1/health` répond, mais `GET /login` renvoie `500` |
| **Cause probable** | Variables runtime Web absentes ou incohérentes côté Render (`APP_KEY`, `SESSION_DRIVER`, `CACHE_STORE`, `APP_URL`) ou cache Laravel généré avec de mauvaises valeurs |
| **Vérifications** | Confirmer `APP_KEY` non vide, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `APP_URL=https://gestionemployerbackend.onrender.com`, puis relancer un déploiement complet |
| **Action Render** | Ouvrir `Logs`, rechercher la stack trace exacte du `500`, puis déclencher `Manual Deploy -> Clear build cache & deploy` si les variables viennent d'être modifiées |

---

## 6. 📱 Guide pour les Testeurs Mobiles

### 6.1 Configuration de l'Application Flutter

Dans le fichier de configuration de l'API Flutter (ex: `lib/core/constants/api_constants.dart` ou équivalent), remplacer l'URL locale par :

```dart
// AVANT (dev local)
static const String baseUrl = 'http://10.0.2.2:8000';

// APRÈS (serveur Render)
static const String baseUrl = 'https://gestionemployerbackend.onrender.com';
```

### 6.2 Scénarios de Test Recommandés

Les testeurs doivent valider ces parcours utilisateur en priorité :

| # | Scénario | Compte à utiliser |
| :--- | :--- | :--- |
| T-01 | Login avec email/password | N'importe lequel |
| T-02 | Pointage (Check-In) | Karim, Amina, Youcef, Nadia ou Mehdi |
| T-03 | Pointage (Check-Out) | (suite du T-02) |
| T-04 | Consulter son historique | N'importe quel employé |
| T-05 | Voir l'équipe et les présences | Ahmed Benali (Manager) |
| T-06 | Valider une absence | Fatima Meziane (RH) |

### 6.3 Comportement Attendu (Plan Gratuit Render)

```
 1er appel après inactivité  →  Délai de 30-60s (démarrage à froid)
 Appels suivants             →  Réponse normale < 300ms
 Inactivité > 15 min        →  Le service se remet en veille
```

---

## 7. 📦 État Final de l'Infrastructure

```
┌─────────────────────────────────────────────────────────────┐
│                    LÉOPARDO RH — CLOUD MVP                  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📱 Application Mobile (Flutter)                            │
│     └─► HTTPS ──────────────────────────────────────►       │
│                                                             │
│  🌐 Render.com (Web Service - Free Tier)                    │
│     ├── Image : dunglas/frankenphp:php8.4-alpine            │
│     ├── Entrypoint : docker-entrypoint.sh                   │
│     │   ├── php artisan config:cache (runtime)              │
│     │   ├── php artisan route:cache  (runtime)              │
│     │   ├── php artisan migrate --force                     │
│     │   └── export SERVER_NAME=":${PORT}"                   │
│     └── Port : $PORT (dynamique, assigné par Render)        │
│                                                             │
│  🐘 Neon.tech (PostgreSQL 16 - Free Tier)                   │
│     ├── Host : ep-odd-morning-abt600ow-pooler.eu-west-2     │
│     ├── DB   : neondb                                       │
│     └── SSL  : require                                      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. ⏭️ Prochaines Étapes

- [ ] **Initialiser les données de démo** : Lancer `php artisan db:seed --class=DemoCompanySeeder` via la console Render pour créer les 7 comptes testeurs.
- [ ] **Brancher l'app mobile** : Mettre à jour `baseUrl` dans le code Flutter vers l'URL Render.
- [ ] **Distribuer aux testeurs** : Partager ce document (section 4 + 6) avec l'équipe de test.
- [ ] **Passer en plan payant** : Quand l'équipe est prête pour la Beta, passer Render en `Starter ($7/mois)` pour supprimer la mise en veille automatique.
- [ ] **Configurer un domaine custom** : Pointer `api.leopardo-rh.com` vers le service Render.
