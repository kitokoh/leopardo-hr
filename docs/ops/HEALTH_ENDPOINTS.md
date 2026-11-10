# Sondes de santé de l'API

Trois endpoints, trois responsabilités distinctes (issue #7255). Les confondre
a un coût direct : un incident réel (Redis HS, queue bloquée, mailer muet) est
resté invisible pendant que la sonde publique répondait `200 status: ok`.

**Mise à jour #7385** : le mailer muet était *cité* ici comme incident réel
tout en restant **hors** de la sonde — le trou est comblé, `mail` est
désormais exposé par `/health` et `/health/ready`.

| Endpoint | Question posée | Dépendances interrogées | Réponses |
| --- | --- | --- | --- |
| `GET /api/v1/health/live` | « le processus PHP répond-il ? » | aucune (zéro I/O) | toujours `200 {"status":"ok"}` |
| `GET /api/v1/health/ready` | « l'instance peut-elle servir du trafic ? » | **base + Redis + queue** (critiques) + **mail** (dégradé) | `200 status=ok` / `200 status=degraded` / `503 status=fail` + `failed_checks[]` + `degraded_checks[]` |
| `GET /api/v1/health` | matrice détaillée (8 checks) + probe Render | base (pilote le statut), Redis, storage, queue, mémoire, web, delivery, **mail** (exposés) | `200` si la base répond, `503` sinon |

## Sémantique

- **`ok: true`** — le check a été exécuté et a réussi.
- **`status: skipped`** (avec `ok: true`) — la dépendance n'est pas configurée :
  Redis absent, storage local à vérifier plus tard, mailer non applicable hors
  production. **Une dépendance absente n'est jamais une panne.**
- **`status: degraded`** (avec `ok: false`) — la dépendance est configurée mais
  ne répond pas correctement (Redis en `ConnectionException`, mailer dégradé
  avec `issues[]`, …).
- **`status: fail`** (niveau endpoint) — au moins une dépendance **critique** de
  `/health/ready` est en panne : `503` + `failed_checks` nomme la ou les
  dépendance(s) fautive(s).
- **`status: degraded`** (niveau endpoint, `200`) — aucune dépendance critique
  en panne, mais une dépendance **non critique** l'est : `degraded_checks` la
  nomme. Cas d'usage : **le mailer muet** (#7385). Sans SMTP l'API reste
  parfaitement servable — connexion, données, pointage — donc un 503 serait
  sur-réagir et ferait redémarrer des instances saines. Mais la
  **réinitialisation de mot de passe est morte** : le contrôleur avale l'échec
  d'envoi par anti-énumération (#6751), donc l'utilisateur lit « un lien vient
  d'être envoyé » et ne reçoit jamais rien. **Le seul signal possible est
  ici — surveillez `degraded_checks`.**

## Le check `mail`

- Transports **locaux** (`log`, `array`, `null`) → `status: skipped` : aucune
  dépendance externe, rien à sonder.
- Transport **`smtp`** → `tcp connect` court (2 s) vers `MAIL_HOST:MAIL_PORT`.
  Il attrape **l'hôte injoignable et le port fermé** — la panne de transport,
  celle qui a réellement bloqué la production le 2026-09-14.
  Il **n'attrape pas** un mot de passe SMTP invalide (le socket s'ouvre) :
  d'où `status: reachable` et non `sent`. Vérifier les identifiants reste une
  opération manuelle.
- `MAIL_HOST` absent → `status: misconfigured` (`error: MAIL_HOST_MISSING`).
- **L'hôte n'est jamais exposé** dans la réponse : `/health` est public, et un
  nom d'hôte SMTP interne est une information d'infrastructure.

### Mise à jour #9317 — le transport RÉELLEMENT résolu est sondé

Avant #9317, la garde était `$mailer !== 'smtp'` : **tout transport autre que
`smtp` était rapporté `skipped` + `ok: true`**. Le transport de production
étant `resend` (transport HTTP, #5139), un mailer cassé produisait un faux vert
— mesuré le 2026-11-10 (`POST /trial/signup` en 503 `TRIAL_OTP_SEND_FAILED`
pendant que `/health/ready` répondait `mail: {ok: true, status: "skipped"}`).

Désormais :

| Transport résolu | Sonde | Statuts possibles |
| --- | --- | --- |
| `log`, `array`, `null` | aucune (pas de dépendance externe) | `skipped` |
| `smtp` | `tcp connect` 2 s vers `MAIL_HOST:MAIL_PORT` | `reachable`, `unreachable`, `misconfigured` |
| `resend` (HTTP-API) | présence de la clé + **existence DNS du domaine d'envoi** | `configured`, `misconfigured`, `unverified_domain` |
| autre transport réseau | présence des credentials connus | `unprobed`, `misconfigured` |

- `resend` sans clé → `misconfigured` (`error: RESEND_KEY_MISSING`), **jamais**
  `skipped`. La clé est lue dans `services.resend.key` **ou** `resend.api_key`
  (les deux emplacements que lit `resend/resend-laravel`).
- Domaine de l'expéditeur **inexistant** (NXDOMAIN) →
  `unverified_domain` (`error: MAIL_FROM_DOMAIN_UNRESOLVED`). Un domaine
  inexistant ne peut pas être vérifié chez le fournisseur : aucun envoi ne
  partira, quelle que soit la validité de la clé. C'est le cas mesuré —
  `MAIL_FROM_ADDRESS` pointait sur `leopardo-rh.com`, NXDOMAIN (cf. #8062).
- La **validité** de la clé n'est pas testée (cela exigerait un appel réseau
  sortant à chaque sonde) : d'où `configured` et non `sent`.
- L'adresse expéditrice n'est jamais exposée (même raison que l'hôte SMTP).

La résolution transport → credentials vit dans
`App\Core\Mail\MailTransportDiagnostics`, partagée par `checkMail()`,
`ProductionDeliveryGuard` et `mail:check-config` — un transport nouvellement
adopté ne peut plus être silencieusement ignoré par l'un des trois.

## Quelle sonde brancher où

| Consommateur | Sonde | Pourquoi |
| --- | --- | --- |
| Render (`healthCheckPath`, `render.yaml` / `render.prod.yaml`) | `/api/v1/health` | Contrat inchangé depuis #7255 : la base pilote le statut. Un Redis partiel ne doit **pas** déclencher de redémarrage d'instance, et 6 workflows de déploiement parsent `"status":"ok"` sur ce payload. |
| Monitoring externe (UptimeRobot, Better Uptime, probes k8s…) | **`/health/ready`** | C'est la seule sonde qui renvoie un code non-2xx quand une dépendance critique tombe. `/health/live` ne verra **jamais** une dégradation. |
| Liveness pur (détecter un process figé, décider d'un redémarrage) | `/health/live` | Aucune I/O : la réponse ne dépend que du runtime PHP. |
| Super-admin (portail admin, `SystemView.vue`) | `/health/ready` + `/health/live` + `/platform/metrics/overview` | Vue détaillée : la base via `/health/ready`, la disponibilité des services API via `/health/live`. |

## Reproduire en local

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<api>/api/v1/health/ready   # 200 attendu ; 503 = dépendance critique HS
curl -s https://<api>/api/v1/health | jq '.status, .checks.redis, .checks.queue'
```

Tests de non-régression : `api/tests/Feature/HealthReadyCriticalDepsTest.php`
(Redis HS → 503 ; queue HS → 503 ; Redis non configuré → 200 ; `/health` reste
un probe liveness DB-only).
