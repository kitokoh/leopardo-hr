<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'monitoring_webhook' => env('SLACK_MONITORING_WEBHOOK_URL'),
        'failed_jobs_threshold' => (int) env('SLACK_FAILED_JOBS_THRESHOLD', 10),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
        // #8053 — audiences Google Sign-In autorisées (aud) : ces clés sont
        // lues par GoogleIdentityVerifier::allowedAudiences() — elles
        // doivent EXISTER ici pour être configurables par env (fail-closed
        // si toutes vides).
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        // #7686 — callback OAuth du module Communication (connexion Gmail par
        // utilisateur, distinct du login Google) : GET
        // /api/v1/communication/integrations/google/callback en absolu.
        'communication_redirect' => env('GOOGLE_COMMUNICATION_REDIRECT_URL'),
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],

    // PA2-JOB-003 - Twilio credentials shared by the SMS and WhatsApp
    // Cloud API-compatible communication providers. `whatsapp_from` is the
    // Twilio WhatsApp-enabled sender number (E.164, e.g. "whatsapp:+14155238886");
    // `from` is the plain SMS sender number.
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_pilot' => env('STRIPE_PRICE_PILOT', env('STRIPE_PRICE_STARTER')),
        'price_operations' => env('STRIPE_PRICE_OPERATIONS', env('STRIPE_PRICE_BUSINESS')),
        'price_enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
    ],

    'chargily' => [
        'api_key' => env('CHARGILY_API_KEY'),
        'webhook_secret' => env('CHARGILY_WEBHOOK_SECRET'),
        'mode' => env('CHARGILY_MODE', 'live'), // 'test' | 'live'
    ],

    // #5725/#1208 — Meta WhatsApp Business Cloud API. UN SEUL bloc : deux
    // clés `'whatsapp'` de premier niveau se seraient écrasées (la seconde
    // gagne) et `phone_number_id`/`access_token` auraient disparu de la
    // config résolue — le canal CRM répondait alors 503
    // (CRM_CHANNEL_NOT_CONFIGURED) et le provider Notification retombait
    // silencieusement sur l'audit-only.
    'whatsapp' => [
        // PA2-COMM-008 : si l'un des deux secrets manque,
        // `CommunicationService::providerFor('whatsapp')` retombe sur le
        // provider audit-only au lieu d'échouer à l'envoi.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com/v19.0'),
        // #5725 — Token système WhatsApp Business Cloud API (Meta Graph API),
        // lu par `WhatsAppAdapter::accessToken()`. Secret manager uniquement :
        // jamais en frontend, DB ou logs.
        'token' => env('WHATSAPP_CLOUD_API_TOKEN', ''),
    ],

    'saml' => [
        // #3890 : moteur de validation SAML non implémenté (stub fail-closed).
        // Gate de feature explicite : tant que SAML_ENABLED=false (défaut),
        // le callback SAML répond 501 SAML_FEATURE_DISABLED. À passer à true
        // uniquement quand la validation des assertions sera livrée.
        'enabled' => (bool) env('SAML_ENABLED', false),
    ],

    'mail_bounce_webhook' => [
        // Issue #3058 : le secret du webhook de rebond email n'était défini
        // nulle part → 503 permanent (fail-closed #2616). Configurable via
        // MAIL_BOUNCE_WEBHOOK_SECRET ; vide = webhook refusé (défensif).
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET'),
    ],

    'marketing_lead_webhook' => [
        // PA2-MKT-007 - Shared secret the public vitrine (front/web Next.js
        // API routes) sends in `X-Marketing-Lead-Token` (or as a Bearer
        // token) on every call to POST /api/v1/marketing/leads. Reuses the
        // same value as `MARKETING_LEAD_WEBHOOK_TOKEN`, already documented
        // in docs/validation/LAUNCH_OBSERVABILITY_DASHBOARD.md for the
        // CRM/email forward webhooks. En local/test la valeur est vide.
        //
        // #7301 : quand la valeur est vide, l'endpoint ne peut pas
        // authentifier l'appelant — il PERSISTE quand même le lead (ne jamais
        // perdre un lead d'acquisition) et émet une alerte. Dès que la valeur
        // est renseignée, la vérification est fail-closed (#3888).
        'secret' => env('MARKETING_LEAD_WEBHOOK_TOKEN'),
        // #7301 - Relais d'alerte best-effort (Slack/CRM/mail) utilisé quand
        // l'ingestion tourne sans secret ou quand la persistance échoue.
        // Même variable que le volet vitrine (`MARKETING_ALERT_WEBHOOK_URL`).
        'alert_url' => env('MARKETING_ALERT_WEBHOOK_URL'),
    ],

    // BC-36 TRADE (TRADE-007/#8482, spec §8) — adaptateurs de la verticale
    // International Trade OS. AUCUN service payant n'est souscrit et aucune
    // clé n'est inventée : par défaut, chaque driver est en mode dégradé
    // explicite et l'intégration est déclarée NON configurée
    // (`GET /trade/meta/integrations`). Renseigner les variables
    // d'environnement ci-dessous branche l'adaptateur sans changement de code.
    // Secrets côté serveur uniquement — jamais exposés au frontend.
    'trade_fx' => [
        // `manual` : l'utilisateur saisit le taux (rate/date/source tracés).
        // `http`   : adaptateur HTTP externe (base_url + key requis).
        'driver' => env('TRADE_FX_DRIVER', 'manual'),
        'base_url' => env('TRADE_FX_BASE_URL'),
        'key' => env('TRADE_FX_KEY'),
        'timeout' => (int) env('TRADE_FX_TIMEOUT', 5),
    ],

    'trade_tariff' => [
        // `null` : aucune source de tarifs douaniers — la plateforme ne
        // chiffre AUCUN droit ni taxe et le signale explicitement.
        'driver' => env('TRADE_TARIFF_DRIVER', 'null'),
        'base_url' => env('TRADE_TARIFF_BASE_URL'),
        'key' => env('TRADE_TARIFF_KEY'),
        'timeout' => (int) env('TRADE_TARIFF_TIMEOUT', 5),
    ],

    'trade_company_verification' => [
        'driver' => env('TRADE_COMPANY_VERIFICATION_DRIVER', 'null'),
        'base_url' => env('TRADE_COMPANY_VERIFICATION_BASE_URL'),
        'key' => env('TRADE_COMPANY_VERIFICATION_KEY'),
        'timeout' => (int) env('TRADE_COMPANY_VERIFICATION_TIMEOUT', 5),
    ],

    'trade_carrier_quote' => [
        // `null` : les demandes de cotation sont diffusées à des prestataires
        // qui répondent dans la plateforme (aucun tarif simulé).
        'driver' => env('TRADE_CARRIER_QUOTE_DRIVER', 'null'),
        'base_url' => env('TRADE_CARRIER_QUOTE_BASE_URL'),
        'key' => env('TRADE_CARRIER_QUOTE_KEY'),
        'timeout' => (int) env('TRADE_CARRIER_QUOTE_TIMEOUT', 5),
    ],

    'mail_bounce_webhook' => [
        // PA2-COMM-007 - Secret partagé du webhook entrant de rebonds email
        // (Postmark/SES/Mailgun). Le contrôleur est fail-closed (#2616) :
        // secret absent → 503. La clé n'existait dans aucune config ni dans
        // .env.example (#3058) → la feature était morte en permanence.
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET'),
    ],

    'ayrshare' => [
        // Cle API primaire du compte Leopardo cote Ayrshare (Business/Enterprise
        // plan requis pour gerer des profils utilisateur par tenant).
        'api_key' => env('AYRSHARE_API_KEY'),
        'base_url' => env('AYRSHARE_BASE_URL', 'https://api.ayrshare.com/api'),
    ],

    'mail_bounce_webhook' => [
        // #3058 : secret partage authentifiant les webhooks de rebond email
        // (header X-Bounce-Webhook-Secret, EmailBounceWebhookController).
        // Vide = webhook rejete (fail-closed #2616). A positionner en
        // production (MAIL_BOUNCE_WEBHOOK_SECRET).
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET', ''),
    ],

    'mobile_app_links' => [
        // #4180 : liens réels des apps sur l'App Store, par rôle, chargés depuis
        // l'environnement (JSON : {"rh": "...", "employee": "...", ...}). Tant
        // que les apps ne sont pas publiées (distribution Firebase App
        // Distribution), les clés sont absentes → 'ios' reste null et les
        // surfaces (e-mail, API) n'affichent pas de lien iOS. Interdiction de
        // revenir aux placeholders id000000000*.
        'ios' => json_decode((string) env('LEOPARDO_IOS_APP_LINKS', '{}'), true) ?: [],
    ],

    'github_stack_switch' => [
        // #8673 (épic #8670) — jeton GitHub utilisé par le bouton superadmin de
        // bascule de stack : il déclenche un `repository_dispatch` (événement
        // `stack-switch`) que `.github/workflows/stack-switch-dispatch.yml`
        // route vers `stack-switch.yml`. Portée minimale attendue : un
        // fine-grained token limité à ce dépôt avec « Contents: read » +
        // « Actions: write » — jamais un PAT classique `repo`.
        // Absent = l'écran affiche l'état des stacks mais refuse de basculer
        // (fail-closed explicite, cf. PlatformStackSwitchAction).
        'token' => env('GITHUB_STACK_SWITCH_TOKEN', ''),
        'repository' => env('GITHUB_STACK_SWITCH_REPOSITORY', 'kitokoh/leopardo-hr'),
    ],

    // #8738 — canal SMS du code de connexion a usage unique (OTP).
    // `endpoint` + `api_key` vides = canal NON configure : le controleur
    // replit alors sur l'e-mail au lieu de generer un code qui ne partirat
    // jamais. Voir App\Core\Auth\Infrastructure\Sms\LogOnlySmsCodeSender.
    'sms_otp' => [
        'endpoint' => env('SMS_OTP_ENDPOINT', ''),
        'api_key' => env('SMS_OTP_API_KEY', ''),
        'from' => env('SMS_OTP_FROM', ''),
    ],

];
