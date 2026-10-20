# Inventaire des comptes et services de la plateforme

> Épic #8670 · issue **#8671**. État **MESURÉ le 2026-10-18** via les API
> Render / Vercel / Neon / Cloudflare / GitHub. Chaque ligne porte sa valeur,
> son statut réel et son plan B. Les comptes non encore ouverts mais nécessaires
> sont marqués **À CRÉER**.

## 0. Légende des statuts

| Statut | Sens |
|---|---|
| **ACTIF** | utilisé par la prod ou le dev aujourd'hui |
| **ACTIF / DÉGRADÉ** | utilisé, mais limité ou suspendu |
| **VIDE** | compte authentifié, **aucune ressource** (vérifié) |
| **INVALIDE** | identifiant fourni refusé par le fournisseur |
| **À CRÉER** | nécessaire, pas encore ouvert |
| **HORS PÉRIMÈTRE** | ne concerne pas cette mission |

---

## 1. Backend / API

| # | Compte / service | Fournisseur | Rôle & valeur | Identifiant | Statut | Plan B |
|---|---|---|---|---|---|---|
| 1 | `leopardo-prod` | Render | **API prod**, sert web + admin + mobile | `srv-dacsr6gae00c73ddk150` (frankfurt) | **ACTIF / DÉGRADÉ** — free, **suspendu 28/09→01/10** (quota) | stack B (`leopardo-prod-b`) |
| 2 | `gestionemployerBackend` | Render (africanovatech) | **API dev** stack A | `srv-d7dro8u7r5hc73a395pg` | **SUSPENDU** (`suspender=billing`, down depuis 20/09) | stack B/C dev, ou upgrade |
| 3 | *(aucun service)* | Render `ibrahim.ali` | **Compte de réserve prod** | `rnd_GoF7…` | **VIDE** (MESURÉ) | → accueille `leopardo-prod-b` |
| 4 | *(aucun service)* | Render `nour` | Réserve pour une 3ᵉ API | `rnd_2YNRR4A6…` | **VIDE** (MESURÉ) | → stack C |
| 5 | CI/CD | **GitHub Actions** | Build, tests, déploiements, bascules | `kitokoh/leopardo-hr` | **ACTIF** (2 000 min/mois free) | runners self-hosted si dépassement |
| 6 | Registry d'images | **GitHub Container Registry** | (non utilisé aujourd'hui — Render build sur Dockerfile) | — | **À ÉVALUER** | utile si déploiement hors Render |

## 2. Base de données

| # | Compte / service | Fournisseur | Rôle & valeur | Identifiant | Statut | Plan B |
|---|---|---|---|---|---|---|
| 7 | Neon `LEOPARDO` | Neon (ALI MAHADI) | **Base prod** (données bêta réelles) | `rough-cherry-23234590`, `ep-odd-morning-abt600ow.eu-west-2` | **ACTIF** — free (5 Go transfert) | `db-standby-failover.yml` → standby (RPO ≤ 6 h) |
| 8 | Neon `leopardorh` | Neon (principal) | **Base dev** stack A | compte `napi_rvn1ge…` | **ACTIF** (MESURÉ) | stacks dev B/C avec leur propre projet |
| 9 | Neon `leo` | Neon (nour) | Base dev de réserve (PG18, us-east-2) | `long-hat-28351058` | **VIDE / À RÉUTILISER** | → base de la stack dev C |
| 10 | Redis / KV | **Upstash** (`50dfa129…`, `37717d25…`) | Cache / sessions / file Redis | 2 bases existantes | **IDs seuls fournis** — pas de clé API → **non vérifiable** | Render KV `leopardoai` (MESURÉ actif sur dev A) |
| 11 | Render Key Value | Render | Cache/sessions dev A | `leopardoai` (frankfurt) | **ACTIF** free | file store (déjà le défaut) |
| 12 | Sauvegardes | **Backblaze B2** (via secrets CI) | Backups chiffrés quotidiens de la base prod | `database-backup.yml` | **ACTIF** — free 10 Go | second bucket si > 10 Go |
| 13 | Sauvegarde « à froid » | GitHub Releases / artifacts | Dump ponctuel avant migration risquée | — | **À PRÉVOIR** | — |

## 3. Front web

| # | Compte / service | Fournisseur | Rôle & valeur | Identifiant | Statut | Plan B |
|---|---|---|---|---|---|---|
| 14 | `leopardo-prod` | Vercel (`ibrahimkoubaye-6514`) | **Portail web prod** | `prj_Q2UQ7h5SeozRB2fVttHRAAq8PTdc` | **ACTIF / DÉGRADÉ** — Hobby, **bloqué `api-deployments-free-per-day` depuis le 20/09** | projet web stack B (compte B) |
| 15 | `leopardo-dev` | Vercel | Portail web dev | `prj_cfkUOhZ4JRD5yWvxO7zmShWpMdRi` | **ACTIF** | idem |
| 16 | `leopardo-dev-b` | Vercel | Portail web dev stack B | `prj_e51HecI2HK9CJoQkt8nLeJlm4VvE` | **ACTIF** (MESURÉ) | — |
| 17 | `leopardo-dev-c` | Vercel | Portail web dev stack C | `prj_Uxch4JgC1gQJd7O9BTAG4pqQTD5h` | **ACTIF** (MESURÉ) | — |
| 18 | `gestionemployer-backend` | Vercel | Ancien portail dev (naming historique) | `prj_HKrhSwq1mnLoN3VcfFQYJIPq4sn8` | **ACTIF** | à archiver quand plus utilisé |
| 19 | `leopardoai` | Vercel | Ancien portail (naming historique) | `prj_mQtuScwGh4uXHVfcV23q3nJHcvbA` | **ACTIF** | à archiver |
| 20 | `leopardo-recrutement` | Vercel | Ancien portail (naming historique) | `prj_1T5bIeW54mNvHVXMY8J8DfRRY4kF` | **ACTIF** | à archiver |
| 21 | team `team_VHKDY50i…` | Vercel `ibrahim.ali` | **Équipe de réserve prod** | `team_VHKDY50iJHW2ADKvAD7gKkZu` | **VIDE** (0 projet, MESURÉ) | → accueille le web stack B |
| 22 | team `team_OODVOTi9…` | Vercel `nour` | Réserve prod/dev | `team_OODVOTi98eo34Pvmgo5Kw0mX` | **VIDE** (0 projet, MESURÉ) | → stack C |
| 23 | Projet team `ibrahimkoubaye-6514` | Vercel | Projet « Leopardo » legacy | `prj_ylTUo7gGDCzcZr3CxHwueFWEZ9fO` | **ACTIF** | — |

## 4. Admin / back-office

| # | Compte / service | Fournisseur | Rôle & valeur | Identifiant | Statut | Plan B |
|---|---|---|---|---|---|---|
| 24 | `leo-admin-prod` | Cloudflare Pages (compte principal) | **Admin prod** | Account `30540c0b…` | **ACTIF** — free (500 builds/mois) | projet Pages stack B |
| 25 | `leo-admin` | Cloudflare Pages | **Admin dev** | Account `28a39f56…` | **ACTIF** | — |
| 26 | `infra/cloudflare/dev-keepalive-worker` | Cloudflare Worker | Relais de ping fiable (crons GitHub défaillants) | idem | **ACTIF** | désactivable (voir §8) |
| 27 | Tokens API Cloudflare | Cloudflare | Déploiement Pages / Workers | 2 jetons fournis | **INVALIDE** — `GET /user/tokens/verify` refuse les deux (**MESURÉ**) | ⚠️ **les régénérer** : les pipeline Pages prod/dev en dépendent |

## 5. Messagerie / notifications

| # | Compte / service | Fournisseur | Rôle & valeur | Statut | Plan B |
|---|---|---|---|---|---|
| 28 | Mailgun | Mailgun | E-mails prod **et** dev (à fournir) | **À FOURNIR** par le fondateur | Resend (`MAIL_MAILER=resend`, déjà supporté) |
| 29 | Resend | Resend | Alternative e-mail transactionnel | **ACTIF** (clé présente dans le blueprint) | Mailgun |
| 30 | Firebase / FCM | Google | Push mobile | **ACTIF** (`FIREBASE_*` dans les secrets) | — |
| 31 | WhatsApp Cloud API | Meta | Notifications WhatsApp | **ACTIF si jeton posé** | e-mail / SMS |
| 32 | Chargily | Chargily (DZ) | Paiement local | **ACTIF** (clés en secrets) | Stripe |
| 33 | Stripe | Stripe | Paiement international + abonnements | **ACTIF** (clés en secrets) | Chargily |
| 34 | Google OAuth | Google Cloud | Connexion sociale | **ACTIF** | mot de passe seul |
| 35 | Sentry | Sentry | Traces d'erreur API | **à confirmer** (DSN vide dans le blueprint) | logs stderr Render |

## 6. Nom de domaine, DNS, CDN

| # | Compte / service | Fournisseur | Rôle & valeur | Statut | Plan B |
|---|---|---|---|---|---|
| 36 | Domaine `hurkonsan.com` | — | URL des e-mails (`noreply@hurkonsan.com`) | **ACTIF** (MESURÉ : SOA résout) | — |
| 37 | Domaine `leopardo-rh.com` | — | URL produit | **MESURÉ : SOA résout** — mais le fondateur déclare **aucun domaine acheté** → à clarifier (enregistrement dormant ?) | à trancher avant la bêta |
| 38 | Cloudflare DNS (zone prod) | Cloudflare | DNS / proxy / règles | **ACTIF** en lecture via l'API authentifiée (jeton fourni invalide pour `/user/tokens`, mais zone servie) | registrar secondaire |
| 39 | Certificats TLS | Cloudflare / Vercel / Render | HTTPS | **ACTIF** (géré) | — |

## 7. Comptes à prévoir (pas encore ouverts)

| # | Compte / service | Fournisseur | Pourquoi c'est nécessaire | Quand |
|---|---|---|---|---|
| 40 | Compte Render dédié **stack C** | Render | 3 stacks dev = 3 comptes (quota 750 h **par compte**) | avant d'ouvrir 3 stacks |
| 41 | Compte **Neon stack C** | Neon | base dev de la 3ᵉ stack (ou réutiliser le projet `leo` de nour) | idem |
| 42 | Compte **Cloudflare stack C** | Cloudflare | admin de la 3ᵉ stack (optionnel) | si isolation souhaitée |
| 43 | **Page de statut publique** | statuspage.io / Better Stack / page statique | pendant un incident, les bêta-testeurs n'ont **aucun** endroit où lire « on est au courant » | **avant la bêta** |
| 44 | **Observabilité payante** (option) | Better Stack / Grafana Cloud | mesures p95, saturation queue, alertes | quand le trafic bêta le justifie |
| 45 | **SMTP de secours** | Resend/Mailgun alternate | panne fournisseur = plus d'invitations | avant la bêta |
| 46 | **Sentry** (compte confirmé) | Sentry | corrélation erreurs ↔ bascule | avant la bêta |
| 47 | **Registre d'images** | GHCR / Docker Hub | si l'on quitte Render pour du self-hosted | plus tard |
| 48 | **Stripe test/live séparés** | Stripe | éviter un vrai prélèvement pendant les tests | avant la bêta |

## 8. Ce qui consomme du quota sans servir la prod

| Élément | Effet | Décision |
|---|---|---|
| `dev-keepalive.yml` (cron 06:04–22:54 UTC) | ~17 h/jour de ping **par stack dev** | conservé : empêche le cold start pendant les heures de travail |
| `infra/cloudflare/dev-keepalive-worker` | ping hors GitHub | conservé, mais **à couper si le quota Render est atteint** |
| `queue-supervision.yml` (`*/5 * * * *`) | 288 runs/jour (minutes CI + appels API) | **à fenêtrer** : voir §9 |
| 5 projets Vercel legacy | chaînent preview/builds | **archiver** `leopardo-recrutement`, `leopardoai`, `gestionemployer-backend` |
| 3 stacks dev (Vercel + Render) | 3 × quota Render | ne garder allumée que la stack active : les autres doivent rester `suspended`/`planned` |

## 9. Angles critiques (ce que la mission met au jour)

1. **Les jetons Cloudflare fournis sont invalides** — vérifié : les deux
   `cfat_…` sont refusés par `/user/tokens/verify`. Les déploiements admin
   prod/dev ne peuvent donc **pas** être rejoués aujourd'hui. C'est bloquant
   pour la prochaine release, indépendamment du quota.
2. **La prod est figée depuis le 20/09** (Vercel). Le code déployé n'est pas
   celui de `main` : tout correctif depuis cette date est **absent de la prod**.
   C'est un risque produit majeur, supérieur au risque de disponibilité.
3. **Render free + ping permanent = quota épuisé par construction.** Toute
   stratégie « on garde le gratuit » doit accepter de **ne pas pinger 24/7**.
4. **Deux stacks sur la même base prod partagent le transfert Neon.** La queue
   en base (`QUEUE_CONNECTION=database`) est le premier poste à surveiller.
5. **Aucune page de statut** pour les bêta-testeurs : en cas d'incident, le
   seul canal est l'équipe.
6. **Le domaine `leopardo-rh.com` résout** alors que le fondateur déclare ne
   pas l'avoir acheté : à clarifier avant toute communication publique.
7. **Mailgun non fourni** : si Resend tombe, plus d'e-mails transactionnels.

---

_Réf. : épic #8670, issues #8671/#8672/#8673/#8674. Tableau « valeur / statut »
condensé livré dans le rapport fondateur (et en fin de PR)._
