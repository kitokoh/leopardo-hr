# RUNBOOK - ROLLBACK MARKETING

Date : 2026-05-21

## Objectif

Stopper proprement l'acquisition et proteger l'experience client si la plateforme montre des signaux rouges pendant une campagne : API degradee, formulaires instables, CRM/email indisponible, queue saturee ou deploy defectueux.

## Declencheurs

- `Launch Observability Smoke` rouge sur API, vitrine ou admin.
- Erreurs 5xx API > 3% pendant 10 minutes.
- Queue depth > 100 pendant 15 minutes.
- Leads captures mais non transmis CRM/email pendant plus de 15 minutes.
- Login client ou admin impossible pour un compte valide.
- Incident securite ou fuite potentielle.

## Actions immediates

1. Geler les nouvelles campagnes ads.
2. Mettre en pause les posts planifies si l'incident touche signup/demo.
3. Desactiver temporairement les CTA payants dans les campagnes, pas dans le code, sauf incident severe.
4. Basculer la qualification lead en manuel : exporter logs `marketing.lead_captured` et traiter par email/interne.
5. Notifier l'equipe support et sales avec statut, impact et prochaine mise a jour.

## Actions techniques

### API / Render

1. Verifier `/api/v1/health`, `/api/v1/health/ready`, logs Render.
2. Si regression recente : rollback Render vers le dernier deploy vert.
3. Si queue saturee : redemarrer workers, puis verifier failed jobs.
4. Si PDF paie congestione la queue : mettre `PAYROLL_QUEUE_PDF_WARMUP=false` temporairement et redeployer.

### Vitrine / Vercel

1. Verifier home, `/pricing`, `/demo`, `/signup`.
2. Si regression frontend : rollback Vercel vers le dernier deploy vert.
3. Si webhooks CRM/email echouent mais les formulaires repondent : garder le site ouvert et traiter les logs lead en manuel.

### Admin / Cloudflare Pages

1. Verifier la page login admin et le smoke auth si credentials de staging disponibles.
2. Rollback Cloudflare Pages vers le deploy precedent si la SPA ne charge pas.
3. Ne pas modifier `VITE_API_URL` sans verifier qu'il pointe bien vers `.../api/v1`.

## Communication

| Audience | Message |
|---|---|
| Interne sales/support | Etat, impact, consigne sur nouveaux leads |
| Leads recents | Message simple si demo/signup touche |
| Clients pilotes | Seulement si l'espace client ou API est impacte |

## Reprise

1. Tous les probes `Launch Observability Smoke` verts pendant 30 minutes.
2. k6 smoke read-only vert si l'incident etait performance.
3. Aucun failed job recurrent.
4. Test manuel : signup, demande demo, login client, login admin.
5. Reprise progressive des campagnes par canal, un canal a la fois.
