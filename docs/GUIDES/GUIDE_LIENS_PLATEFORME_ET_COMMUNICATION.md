# Guide liens plateforme et communication

Date : 2026-05-22

## Liens applicatifs

| Surface | Usage | URL actuelle / cible |
| --- | --- | --- |
| API interne Render | Backend Laravel, endpoints web/mobile/kiosque | `https://gestionemployerbackend.onrender.com` |
| API versionnee | Base API consommee par les frontends | `https://gestionemployerbackend.onrender.com/api/v1` |
| Documentation API | Documentation publique integrateurs | `https://gestionemployerbackend.onrender.com/docs` |
| Vitrine / portail client Vercel | Marketing, signup, demo, espace manager/client | `https://gestionemployer-backend.vercel.app/` |
| Admin plateforme Cloudflare Pages | Super-admin interne | `https://leo-admin.pages.dev` |
| Workers Cloudflare | Automatisations edge futures | Projet Cloudflare `gestionemploye` si reactive |
| Repository GitHub | Source de verite code et PR | `kitokoh/leopardo-hr` |

Les URLs finales devront etre remplacees par les domaines officiels apres achat du nom de domaine. Recommandation :

- `www.leopardo-rh.com` : vitrine publique.
- `app.leopardo-rh.com` : espace client web.
- `admin.leopardo-rh.com` : admin plateforme.
- `api.leopardo-rh.com` : API interne et partenaires.
- `docs.leopardo-rh.com` ou `api.leopardo-rh.com/docs` : documentation API.

## Variables frontend importantes

| Projet | Variable | Valeur attendue |
| --- | --- | --- |
| `front/web` | `NEXT_PUBLIC_API_URL` | `https://gestionemployerbackend.onrender.com/api/v1` |
| `front/web` | `NEXT_PUBLIC_ADMIN_URL` | URL Cloudflare Pages admin |
| `front/web` | `NEXT_PUBLIC_SITE_URL` | URL publique Vercel/domaine |
| `front/admin-dashboard` | `VITE_API_URL` | `https://gestionemployerbackend.onrender.com/api/v1` |
| `front/admin-dashboard` | `VITE_WEBSOCKET_URL` | `https://gestionemployerbackend.onrender.com` |
| `front/zkteco-kiosk` | `apiBaseUrl` | API avec ou sans `/api/v1`, normalisee par l'app |

## Serveurs et hebergeurs recommandes

| Besoin | Option gratuite/dev | Option production |
| --- | --- | --- |
| API Laravel | Render free/Starter pour test | Render paid, Fly.io, Railway, Hetzner, AWS ECS |
| PostgreSQL | Render Postgres free/dev si disponible | Neon, Supabase, Render paid, AWS RDS |
| Vitrine Next.js | Vercel hobby | Vercel Pro, Cloudflare Pages, Netlify |
| Admin statique | Cloudflare Pages free | Cloudflare Pages + Access |
| Assets statiques | Cloudflare Pages/public | Cloudflare R2 + CDN |
| Logs/uptime | GitHub Actions + Render logs | Better Stack, Sentry, Grafana Cloud |
| Email transactionnel | Resend/Brevo free tier | Amazon SES, Resend Pro, Brevo paid |
| Push mobile | Firebase Cloud Messaging gratuit | Firebase + monitoring BigQuery |
| Web push | Web Push natif gratuit | OneSignal si besoin dashboard |
| SMS | Sandbox provider uniquement | Twilio, Vonage, Infobip, operateur local |
| WhatsApp | Meta WhatsApp Cloud API test number | WhatsApp Cloud API production/BSP |

## Strategie gratuite au lancement

1. Emails : demarrer avec le mailer Laravel puis Resend ou Brevo en free tier pour demo, invitation et reset password.
2. Push mobile : utiliser Firebase Cloud Messaging, gratuit et standard Flutter.
3. Web push : utiliser le protocole Web Push natif pour eviter un abonnement SaaS au debut.
4. SMS : garder le provider audit-only/sandbox en dev ; activer seulement les workflows critiques en production.
5. WhatsApp : commencer avec Meta Cloud API test number pour valider les templates et webhooks.
6. Observabilite : garder GitHub Actions + logs Render + artefacts Playwright, puis ajouter Better Stack/Sentry paid quand le trafic augmente.

## Etat implementation communication

| Bloc | Etat | Note |
| --- | --- | --- |
| Preferences utilisateur | Livre | `GET/PATCH /api/v1/notification-preferences` + page web `/settings/notifications` |
| Audit multi-canal | Livre | `communication_events` trace app, push, email, SMS, WhatsApp |
| Orchestrateur | Livre | `App\Services\Communication\CommunicationService` |
| Async | Livre fondation | `DispatchCommunicationJob` sur queue `COMMUNICATION_QUEUE` |
| Push | Livre fondation | Device tokens + Firebase existants, dispatch audite |
| SMS/WhatsApp | Sandbox | `MessageProviderInterface` + provider audit-only par defaut |
| Providers production | A configurer | Choix fournisseur, secrets, signatures webhook, quotas par plan |

## Regles operationnelles

- Ne jamais envoyer de donnees paie completes par SMS ou WhatsApp.
- Toujours pointer vers une page authentifiee pour les contenus sensibles.
- Mettre SPF, DKIM et DMARC avant toute campagne email.
- Garder les tokens providers en secrets CI/cloud, jamais dans le repo.
- Faire passer tous les envois par une queue et par l'audit `communication_events`.
- Documenter chaque nouveau domaine ou provider dans ce guide et dans `AGENTS.md`.
