# SPRINT 0 — CHECKLIST TECHNIQUE COMPLÈTE
# Leopardo RH | Avant la première ligne de code
# Version 1.0 | Mars 2026

---

## PHASE 0-A : INFRASTRUCTURE O2SWITCH (Client)

### 0-A.1 Domaine et DNS
```
[ ] Acheter leopardo-rh.com chez OVH, Namecheap ou autre
[ ] Configurer les sous-domaines sur o2switch cPanel :
    - app.leopardo-rh.com   → dossier /public_html/app
    - api.leopardo-rh.com   → dossier /public_html/api
    - admin.leopardo-rh.com → dossier /public_html/admin
[ ] Vérifier la propagation DNS (48h max)
[ ] Activer SSL Let's Encrypt sur chaque sous-domaine depuis cPanel
```

### 0-A.2 Services Firebase (Push Notifications)
```
[ ] Créer un projet Firebase sur console.firebase.google.com
[ ] Activer Firebase Cloud Messaging (FCM)
[ ] Télécharger le fichier google-services.json (Android)
[ ] Télécharger le fichier GoogleService-Info.plist (iOS)
[ ] Copier la clé serveur FCM dans .env : FIREBASE_SERVER_KEY=...
[ ] Télécharger le fichier de compte de service JSON Firebase
    → stocker dans storage/app/firebase-credentials.json
```

### 0-A.3 Stores Mobiles
```
[ ] Créer compte Google Play Developer : play.google.com/apps/publish (25$ unique)
[ ] Créer compte Apple Developer : developer.apple.com (99$/an)
[ ] Créer l'application "Leopardo RH" sur Google Play Console
[ ] Créer l'application "Leopardo RH" sur App Store Connect
[ ] Préparer les assets visuels (logo 512x512, screenshots)
```

---

## PHASE 0-B : SERVEUR O2SWITCH (Équipe dev)

### 0-B.1 Vérification des prérequis o2switch
```bash
# Se connecter en SSH sur le VPS o2switch
ssh user@votre-serveur.o2switch.net

# Vérifier PHP 8.3
php --version
# → PHP 8.3.x

# Vérifier PostgreSQL 16
psql --version
# → psql (PostgreSQL) 16.x

# Vérifier Redis
redis-cli ping
# → PONG

# Vérifier Nginx
nginx -v
# → nginx/1.24.x

# Vérifier Composer
composer --version
# → Composer version 2.x

# Vérifier Node.js (pour Vue.js build)
node --version && npm --version
# → v20.x.x + 10.x.x
```

### 0-B.2 Configuration PostgreSQL
```sql
-- Connexion PostgreSQL
sudo -u postgres psql

-- Créer la base principale (schéma public)
CREATE DATABASE leopardo_db;
CREATE USER leopardo_user WITH ENCRYPTED PASSWORD 'MotDePasseSecurise123!';
GRANT ALL PRIVILEGES ON DATABASE leopardo_db TO leopardo_user;

-- Se connecter à la base
\c leopardo_db

-- Donner les droits de création de schéma (essentiel pour le multi-tenant)
GRANT CREATE ON DATABASE leopardo_db TO leopardo_user;
GRANT ALL ON SCHEMA public TO leopardo_user;

-- Extension UUID
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Quitter
\q
```

### 0-B.3 Configuration Redis
```bash
# Vérifier que Redis est actif
sudo systemctl status redis

# Si non actif, démarrer
sudo systemctl start redis
sudo systemctl enable redis

# Tester la connexion
redis-cli -h 127.0.0.1 ping
# → PONG
```

### 0-B.4 Configuration Nginx
```bash
# Créer le fichier de config pour l'API
sudo nano /etc/nginx/sites-available/leopardo-api

# --- Contenu du fichier ---
server {
    listen 443 ssl http2;
    server_name api.leopardo-rh.com;
    root /var/www/leopardo-rh-api/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/api.leopardo-rh.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.leopardo-rh.com/privkey.pem;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=1r/s;
    limit_req zone=api burst=60 nodelay;

    # Headers de sécurité
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\. { deny all; }
    location ~ /(storage|bootstrap/cache) { deny all; }

    client_max_body_size 10M;
}

server {
    listen 80;
    server_name api.leopardo-rh.com;
    return 301 https://$server_name$request_uri;
}
# --- Fin du fichier ---

# Activer le site
sudo ln -s /etc/nginx/sites-available/leopardo-api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 0-B.5 Configuration Supervisor (Queues Laravel)
```bash
sudo nano /etc/supervisor/conf.d/leopardo-worker.conf

# --- Contenu ---
[program:leopardo-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leopardo-rh-api/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/worker-default.log

[program:leopardo-worker-payroll]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leopardo-rh-api/artisan queue:work redis --queue=payroll --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/leopardo-rh-api/storage/logs/worker-payroll.log
# --- Fin ---

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 0-B.6 Crontab Laravel
```bash
# Editer le crontab de www-data
sudo crontab -u www-data -e

# Ajouter cette seule ligne (le scheduler Laravel gère tout)
* * * * * cd /var/www/leopardo-rh-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## PHASE 0-C : INITIALISATION BACKEND LARAVEL (Claude Code)

### 0-C.1 Créer le projet Laravel 11
```bash
# Dans /var/www/
composer create-project laravel/laravel leopardo-rh-api
cd leopardo-rh-api

# Vérifier la version
php artisan --version
# → Laravel Framework 11.x.x
```

### 0-C.2 Installer les packages Composer
```bash
# Multi-tenant PostgreSQL
composer require stancl/tenancy

# Auth API
composer require laravel/sanctum

# PDF
composer require barryvdh/laravel-dompdf

# Firebase (push notifications)
composer require kreait/laravel-firebase

# Excel export
composer require maatwebsite/excel

# Génération de fichiers CSV/XML pour export bancaire
# (package natif PHP — pas besoin de dépendance)

# Dev tools
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
composer require --dev laravel/pint
composer require --dev barryvdh/laravel-ide-helper

# Initialiser Pest
php artisan pest:install
```

### 0-C.3 Installer les packages NPM (Vue.js + Inertia)
```bash
npm install
npm install @inertiajs/vue3
npm install vue@3
npm install @vitejs/plugin-vue
npm install -D tailwindcss postcss autoprefixer
npm install primevue primeicons
npm install @vueuse/core
npm install axios
npx tailwindcss init -p
```

### 0-C.4 Configurer le fichier .env
```env
APP_NAME="Leopardo RH"
APP_ENV=production
APP_KEY=                          # Généré par php artisan key:generate
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://api.leopardo-rh.com
APP_LOCALE=fr

# Base de données PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=leopardo_db
DB_USERNAME=leopardo_user
DB_PASSWORD=MotDePasseSecurise123!
DB_SCHEMA=public                  # Schéma par défaut

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mail.o2switch.net
MAIL_PORT=587
MAIL_USERNAME=noreply@leopardo-rh.com
MAIL_PASSWORD=MotDePasseMail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@leopardo-rh.com
MAIL_FROM_NAME="Leopardo RH"

# Firebase FCM
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=leopardo-rh-xxxxx

# ZKTeco
ZKTECO_DEFAULT_PORT=4370
ZKTECO_SYNC_INTERVAL=60          # Secondes entre chaque pull

# Sanctum
SANCTUM_STATEFUL_DOMAINS=app.leopardo-rh.com,admin.leopardo-rh.com

# Logging
LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
```

### 0-C.5 Publier et configurer les packages
```bash
php artisan key:generate
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider" --tag=config
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider"

# Configurer Sanctum dans config/sanctum.php
# token_prefix: 'leopardo_'
# expiration: 60 * 24 * 90 (90 jours mobile)

# Optimisation production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 0-C.6 Créer les repositories Git
```bash
# Repository 1 : Backend
git init
git remote add origin git@github.com:ton-compte/leopardo-rh-api.git
git add .
git commit -m "chore: initialisation projet Laravel 11"
git push -u origin main

# Créer .gitignore avec au minimum :
# .env
# /vendor
# /node_modules
# /public/hot
# /public/build
# storage/app/firebase-credentials.json
```

---

## PHASE 0-D : INITIALISATION FLUTTER (Jules)

### 0-D.1 Créer le projet Flutter
```bash
flutter create --org com.leopardo --project-name leopardo_rh leopardo-rh-mobile
cd leopardo-rh-mobile

# Vérifier la version Flutter
flutter --version
# → Flutter 3.x.x
```

### 0-D.2 Contenu pubspec.yaml complet
```yaml
name: leopardo_rh
description: Leopardo RH — Gestion RH Multi-Entreprises
version: 1.0.0+1

environment:
  sdk: '>=3.0.0 <4.0.0'

dependencies:
  flutter:
    sdk: flutter
  flutter_localizations:
    sdk: flutter
  intl: ^0.19.0

  # Navigation
  go_router: ^13.0.0

  # State management
  flutter_riverpod: ^2.5.0
  riverpod_annotation: ^2.3.0

  # HTTP
  dio: ^5.4.0

  # Stockage sécurisé (token)
  flutter_secure_storage: ^9.0.0

  # Push Notifications
  firebase_core: ^2.27.0
  firebase_messaging: ^14.7.19

  # QR Code
  mobile_scanner: ^5.0.0

  # Biométrie mobile (Face ID / empreinte téléphone)
  local_auth: ^2.1.8

  # Permission GPS
  geolocator: ^11.0.0
  permission_handler: ^11.3.0

  # Camera (photo au pointage)
  camera: ^0.10.5+9
  image_picker: ^1.0.7

  # PDF viewer
  flutter_pdfview: ^1.3.2

  # UI Components
  flutter_svg: ^2.0.9
  cached_network_image: ^3.3.1
  shimmer: ^3.0.0

  # Date picker
  table_calendar: ^3.0.9

  # Formatage
  intl: ^0.19.0

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0
  build_runner: ^2.4.8
  riverpod_generator: ^2.3.0
  custom_lint: ^0.6.0
  riverpod_lint: ^2.3.0

flutter:
  uses-material-design: true
  generate: true

  assets:
    - assets/images/
    - assets/l10n/

  fonts:
    - family: LeopardoSans
      fonts:
        - asset: assets/fonts/LeopardoSans-Regular.ttf
        - asset: assets/fonts/LeopardoSans-Medium.ttf
          weight: 500
        - asset: assets/fonts/LeopardoSans-Bold.ttf
          weight: 700
```

### 0-D.3 Fichier l10n.yaml (Flutter i18n)
```yaml
# l10n.yaml (à la racine du projet Flutter)
arb-dir: assets/l10n
template-arb-file: app_fr.arb
output-class: AppLocalizations
nullable-getter: false
```

### 0-D.4 Fichiers ARB initiaux
```json
// assets/l10n/app_fr.arb
{
  "@@locale": "fr",
  "appName": "Leopardo RH",
  "login": "Connexion",
  "email": "Email professionnel",
  "password": "Mot de passe",
  "loginButton": "Se connecter",
  "forgotPassword": "Mot de passe oublié ?",
  "checkIn": "Pointer mon arrivée",
  "checkOut": "Pointer mon départ",
  "checkInConfirmed": "Arrivée enregistrée à {time}",
  "@checkInConfirmed": {
    "placeholders": { "time": { "type": "String" } }
  },
  "checkOutConfirmed": "Départ enregistré à {time}",
  "@checkOutConfirmed": {
    "placeholders": { "time": { "type": "String" } }
  },
  "leaveBalance": "Solde de congés : {days} jour(s)",
  "@leaveBalance": {
    "placeholders": { "days": { "type": "double" } }
  },
  "tasks": "Mes tâches",
  "absences": "Mes absences",
  "payslips": "Mes bulletins",
  "profile": "Mon profil",
  "noInternetCheckIn": "Connexion internet requise pour pointer",
  "gpsOutOfZone": "Vous n'êtes pas dans la zone autorisée",
  "errorGeneric": "Une erreur est survenue. Veuillez réessayer.",
  "pending": "En attente",
  "approved": "Approuvé",
  "rejected": "Refusé",
  "submit": "Soumettre",
  "cancel": "Annuler",
  "save": "Enregistrer",
  "loading": "Chargement..."
}
```

```json
// assets/l10n/app_ar.arb — Arabe (RTL)
{
  "@@locale": "ar",
  "appName": "ليوباردو HR",
  "login": "تسجيل الدخول",
  "email": "البريد المهني",
  "password": "كلمة المرور",
  "loginButton": "دخول",
  "forgotPassword": "نسيت كلمة المرور؟",
  "checkIn": "تسجيل الحضور",
  "checkOut": "تسجيل الانصراف",
  "checkInConfirmed": "تم تسجيل الحضور في {time}",
  "@checkInConfirmed": {
    "placeholders": { "time": { "type": "String" } }
  },
  "checkOutConfirmed": "تم تسجيل الانصراف في {time}",
  "@checkOutConfirmed": {
    "placeholders": { "time": { "type": "String" } }
  },
  "leaveBalance": "رصيد الإجازات : {days} يوم",
  "@leaveBalance": {
    "placeholders": { "days": { "type": "double" } }
  },
  "tasks": "مهامي",
  "absences": "غياباتي",
  "payslips": "كشوف راتبي",
  "profile": "ملفي",
  "noInternetCheckIn": "الاتصال بالإنترنت مطلوب لتسجيل الحضور",
  "gpsOutOfZone": "أنت خارج المنطقة المسموح بها",
  "errorGeneric": "حدث خطأ ما. يرجى المحاولة مرة أخرى.",
  "pending": "في الانتظار",
  "approved": "مقبول",
  "rejected": "مرفوض",
  "submit": "إرسال",
  "cancel": "إلغاء",
  "save": "حفظ",
  "loading": "جار التحميل..."
}
```

### 0-D.5 Structure des dossiers Flutter
```
lib/
├── main.dart
├── core/
│   ├── api/
│   │   ├── api_client.dart          ← Dio singleton + intercepteurs
│   │   ├── auth_interceptor.dart    ← Ajout token Bearer automatique
│   │   └── refresh_interceptor.dart ← Refresh token si 401
│   ├── auth/
│   │   ├── auth_provider.dart       ← Riverpod AuthNotifier
│   │   └── auth_state.dart
│   ├── i18n/
│   │   └── l10n_extension.dart     ← Extension context.l10n
│   ├── router/
│   │   └── app_router.dart         ← GoRouter avec guards auth
│   ├── storage/
│   │   └── secure_storage.dart     ← flutter_secure_storage wrapper
│   └── theme/
│       └── leopardo_theme.dart     ← ThemeData (bleu marine + orange)
├── features/
│   ├── auth/
│   │   ├── login_screen.dart
│   │   └── forgot_password_screen.dart
│   ├── dashboard/
│   │   ├── employee_dashboard.dart
│   │   └── manager_dashboard.dart
│   ├── attendance/
│   │   ├── attendance_screen.dart   ← Grand bouton pointage
│   │   ├── qr_scanner_screen.dart
│   │   └── attendance_history.dart
│   ├── tasks/
│   │   ├── task_list_screen.dart
│   │   ├── task_detail_screen.dart
│   │   └── task_comment_thread.dart
│   ├── absences/
│   │   ├── absence_list_screen.dart
│   │   └── absence_request_screen.dart
│   ├── advances/
│   │   ├── advance_screen.dart
│   │   └── repayment_plan_screen.dart
│   ├── payroll/
│   │   ├── payslip_list_screen.dart
│   │   └── payslip_viewer_screen.dart
│   └── profile/
│       └── profile_screen.dart
└── shared/
    ├── widgets/
    │   ├── leopardo_button.dart
    │   ├── status_badge.dart
    │   ├── loading_overlay.dart
    │   └── error_snackbar.dart
    └── models/                      ← Modèles Dart générés depuis API
        ├── employee.dart
        ├── attendance_log.dart
        ├── absence.dart
        └── task.dart
```

### 0-D.6 GoRouter — Routes principales
```dart
// core/router/app_router.dart
final appRouter = GoRouter(
  initialLocation: '/login',
  redirect: (context, state) {
    final isLoggedIn = ref.read(authProvider).isAuthenticated;
    if (!isLoggedIn && !state.uri.path.startsWith('/login')) {
      return '/login';
    }
    return null;
  },
  routes: [
    GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
    GoRoute(path: '/forgot-password', builder: (_, __) => const ForgotPasswordScreen()),
    ShellRoute(
      builder: (_, __, child) => MainLayout(child: child),
      routes: [
        GoRoute(path: '/dashboard', builder: (_, __) => const DashboardScreen()),
        GoRoute(path: '/attendance', builder: (_, __) => const AttendanceScreen()),
        GoRoute(path: '/attendance/qr', builder: (_, __) => const QrScannerScreen()),
        GoRoute(path: '/tasks', builder: (_, __) => const TaskListScreen()),
        GoRoute(path: '/tasks/:id', builder: (_, state) => TaskDetailScreen(id: state.pathParameters['id']!)),
        GoRoute(path: '/absences', builder: (_, __) => const AbsenceListScreen()),
        GoRoute(path: '/absences/new', builder: (_, __) => const AbsenceRequestScreen()),
        GoRoute(path: '/advances', builder: (_, __) => const AdvanceScreen()),
        GoRoute(path: '/payslips', builder: (_, __) => const PayslipListScreen()),
        GoRoute(path: '/payslips/:id', builder: (_, state) => PayslipViewerScreen(id: state.pathParameters['id']!)),
        GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
      ],
    ),
  ],
);
```

---

## PHASE 0-E : VALIDATION FINALE AVANT DÉVELOPPEMENT

### Checklist Go/No-Go
```
INFRASTRUCTURE
[ ] PostgreSQL répond (psql -U leopardo_user -d leopardo_db)
[ ] Redis répond (redis-cli ping → PONG)
[ ] Nginx actif (curl https://api.leopardo-rh.com → réponse Laravel)
[ ] SSL valide sur tous les sous-domaines
[ ] Supervisor actif (supervisorctl status → RUNNING)
[ ] Cron actif (crontab -u www-data -l → affiche la ligne)

LARAVEL
[ ] php artisan migrate (schéma public) → aucune erreur
[ ] php artisan db:seed --class=PublicSchemaSeeder → succès
[ ] php artisan queue:work --once → job de test traité
[ ] php artisan test → tous les tests passent (même 0 test)

FLUTTER
[ ] flutter pub get → aucune erreur
[ ] flutter gen-l10n → fichiers générés dans .dart_tool/flutter_gen/
[ ] flutter build apk --debug → APK généré
[ ] Application se lance sur émulateur Android
[ ] Application se lance sur simulateur iOS

GIT
[ ] Repo leopardo-rh-api créé et premier commit poussé
[ ] Repo leopardo-rh-mobile créé et premier commit poussé
[ ] .gitignore correctement configuré (pas de .env, pas de credentials)
[ ] Branch strategy définie : main (prod) + develop (dev) + feature/*

FIREBASE
[ ] google-services.json dans android/app/
[ ] GoogleService-Info.plist dans ios/Runner/
[ ] Test notification push reçue sur l'émulateur

COMMUNICATION ÉQUIPE
[ ] Claude Code et Jules ont les deux accès aux repositories
[ ] API_CONTRATS.md partagé et lu par les deux
[ ] ERD_COMPLET.md partagé et lu par les deux
[ ] Réunion de démarrage effectuée — premiers sprints planifiés
```

**→ Tous les éléments cochés = GO pour démarrer le développement Sprint 1.**
