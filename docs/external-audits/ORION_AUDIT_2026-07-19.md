# Audit externe — Orion (agent KiloClaw) — 2026-07-19

> Statut : proposition / revue externe, non affiliee a l'equipe du projet.
> Perimetre : lecture publique du repo (clone HTTPS anonyme), lecture de la documentation
> interne existante (`docs/audits/AUDIT.md`, `docs/audits/AUDIT_CICD_2026-07-19.md`, `docs/PLAN_ACTION2/*`,
> `docs/security/AUDIT_API_2026-07-19.md`), verification API GitHub publique (issues, PRs,
> Dependabot alerts, CodeQL alerts, workflow runs), et **un test en direct en lecture (GET)**
> sur l'endpoint public de production `gestionemployerbackend.onrender.com`.
> Aucune tentative d'authentification, d'ecriture, ni d'exploitation active n'a ete effectuee.
> Cette branche est fournie a titre de rapport ; aucune ligne de code applicatif n'a ete modifiee.

---

## 1. Contexte

Deux passes d'analyse ont ete menees le meme jour (2026-07-19) sur `kitokoh/leopardo-hr` :

1. Une analyse strategique et architecturale generale (produit, marche, dette technique, CI/CD).
2. Un suivi de l'evolution en quasi temps reel : trois PR correctives ouvertes par le mainteneur
   le meme jour (#873 Dependabot, #874 audit structure modules, #875 durcissement CI supply-chain),
   et la decouverte d'un audit de securite interne (`docs/security/AUDIT_API_2026-07-19.md`)
   documentant une faille critique en production.

Ce document consolide les deux passes en un rapport unique, avec une verification factuelle
supplementaire (statut reel de l'endpoint `/api/v1/demo-users` au moment de la redaction).

---

## 2. Synthese produit et architecture (rappel)

- Monorepo multi-stack : API Laravel 11/PHP 8.4 (63 700 lignes PHP, 19 modules DDD, 87 modeles),
  5 apps Flutter (83 450 lignes Dart), web Next.js 15 + admin Vue 3 (~38 800 lignes TS).
- Positionnement "Mobile-First Company OS" pour PME terrain en marches emergents
  (Maghreb, Afrique de l'Ouest, Turquie) — differenciation reelle (offline-first, paie
  multi-pays, biometrie) vs concurrents (Odoo, BambooHR, Deel, Zoho People).
- Architecture DDD globalement coherente et bien documentee, migration active hors du legacy
  (PR #824 : 90 controllers legacy + 26 services dupliques supprimes).
- Dette technique documentee par le projet lui-meme : ~4200 erreurs PHPStan tolerees en
  baseline, 75/92 modeles Eloquent encore des shims `class_alias` en attente de migration.
- Aucune traction commerciale externe verifiable a ce jour (5 stars, 1 fork, pas de temoignage
  client dans le repo) malgre une strategie GTM tres detaillee (19 sections dans
  `docs/GOTO_MARKET/LEOPARDO_STRATEGIC_ANALYSIS.md`). Le risque principal reste l'execution commerciale, pas la
  technique.

---

## 3. Reactivite du mainteneur — 2026-07-19

Trois PR ouvertes le jour meme, en reponse quasi directe a l'audit initial :

| PR | Contenu | Statut au moment de la redaction |
|---|---|---|
| #873 | Resolution des 34 alertes Dependabot (symfony/yaml, form-data, vite, next 14.2->16.2.10, echarts, eslint 9) | Ouverte |
| #874 | Audit structurel des 19 modules DDD (`docs/PLAN_ACTION2/09_AUDIT_MODULES_API_STRUCTURE.md`) : 4 controllers dupliques jamais routes, divergence Policy `Invoice`, deficit d'Actions, `declare(strict_types=1)` partiel | Deja mergee sur `main` (le fichier existe sur `main`) |
| #875 | Durcissement supply-chain CI (pin SHA `trufflehog`, uniformisation `checkout@v5`/`upload-artifact@v5`, dedup ~360 lignes setup PHP/Postgres/Redis et Flutter/Java via composite actions) | Ouverte |

Le fragment de script orphelin dans `tests.yml` et la reference morte `front/mobile` dans
`release.yml` (signales dans `docs/audits/AUDIT_CICD_2026-07-19.md`) **ne sont plus presents** dans le
code de `main` au moment de cette redaction — ces deux points sont donc consideres resolus.

---

## 4. Point critique verifie en direct — `/api/v1/demo-users`

`docs/security/AUDIT_API_2026-07-19.md` (audit interne du jour meme) documente une faille
critique : l'endpoint `GET /api/v1/demo-users` (`app/Modules/Platform/Interfaces/Api/V1/
Controllers/DemoUserController.php`) ne verifie jamais `config('app.demo_mode_enabled')`
malgre le commentaire de route qui l'affirme, et sert en clair les identifiants de comptes
demo (dont un `super_admin`) sans authentification ni rate limiting dedie.

**Verification effectuee (GET simple, lecture seule) au moment de la redaction :**

```
GET https://gestionemployerbackend.onrender.com/api/v1/demo-users
-> HTTP 200
-> corps JSON contenant admin@leopardo-rh.com / password123 (role super_admin, /platform)
   + 14 comptes manager/employe sur 3 entreprises demo
```

**Confirmation : la faille est toujours active en production au moment de cette redaction.**
Aucun correctif n'a encore ete deploye pour ce point precis, malgre une checklist claire deja
ecrite dans le repo. Aucune tentative de connexion avec ces identifiants n'a ete effectuee —
cette verification s'est volontairement limitee a un GET passif, dans les memes limites que
l'audit interne d'origine.

**Recommandation inchangee et prioritaire** (reprise de l'audit interne) :
1. Verifier immediatement si les comptes lises existent reellement en base de production ; si
   oui, desactiver/changer leurs mots de passe sans delai.
2. Corriger `DemoUserController`/la route pour respecter reellement
   `config('app.demo_mode_enabled')`, et s'assurer que cette variable est a `false` sur Render.
3. Ajouter un throttle dedie sur cette route independamment de sa reactivation eventuelle.

---

## 5. Nuance sur la divergence Policy `Invoice`

`docs/PLAN_ACTION2/09_AUDIT_MODULES_API_STRUCTURE.md` (deja merge) documente que
`AppServiceProvider::boot()` enregistre `Gate::policy(Invoice::class, BillingPolicy::class)`
tandis que `AuthServiceProvider::boot()` enregistre `Gate::policy(Invoice::class,
InvoicePolicy::class)` — confirme dans le code actuel de `main`.

Verification complementaire : a ce jour, **aucun appel explicite** `$this->authorize(...)` ou
`Gate::allows(...)` cible le modele `Invoice` dans le controller reellement route
(`BillingController::invoices/showInvoice/invoicePdf`). L'isolation tenant sur ces methodes
repose entierement sur un filtre `WHERE company_id = $user->company_id` en dur dans les
requetes Eloquent, pas sur la Policy enregistree en double.

Consequence : la divergence n'a **pas d'impact de securite actif constate a ce jour**, mais
reste une dette latente reelle — le jour ou un controller ajoute
`$this->authorize('pay', $invoice)` en supposant `InvoicePolicy::pay()`, le comportement reel
dependra silencieusement de l'ordre de boot des deux providers dans `bootstrap/providers.php`.
Le ticket `PA2-ARCH-008` (deja cree dans le backlog du projet) reste justifie ; la severite
reelle actuelle est "dette architecturale a corriger" plutot que "faille active exploitee".

---

## 6. Autres constats de l'audit securite interne (`AUDIT_API_2026-07-19.md`) — non re-verifies en direct

- SSRF potentiel via `WebhookController::store()` : aucune restriction visible sur les URL
  cibles (plages IP privees/metadonnees cloud) avant livraison par le worker `DispatchWebhook`.
- Tokens Sanctum longue duree (7 jours, `abilities: ['*']`) jamais revoques lors d'un
  changement de mot de passe (`ChangePasswordAction::execute()`).
- `config/cors.php` : `allowed_headers: ['*']` + `supports_credentials: true` — pas
  d'exploit direct tant que `allowed_origins` reste strict, mais combinaison fragile a
  documenter comme regle a ne jamais casser.
- Pas de `trustProxies()`/`trustHosts()` explicite dans `bootstrap/app.php` derriere le proxy
  Render — impacte potentiellement la fiabilite du rate-limiting par IP.

Ces quatre points n'ont pas ete re-testes activement dans le cadre de cette revue (hors
perimetre d'un audit passif en lecture) ; se referer directement a
`docs/security/AUDIT_API_2026-07-19.md` pour le detail et les correctifs proposes.

---

## 7. Recommandations consolidees, par ordre de priorite

1. **Urgence absolue (production, non technique a corriger)** : couper ou proteger
   `/api/v1/demo-users` en production — cf section 4. Verifier l'existence reelle des comptes
   en base et les neutraliser si oui.
2. **Court terme** : merger #873 (Dependabot) et #875 (durcissement CI supply-chain), deja
   pretes ; traiter le SSRF webhooks et la revocation de tokens Sanctum au changement de mot
   de passe.
3. **Moyen terme** : trancher explicitement la divergence Policy `Invoice` (PA2-ARCH-008),
   supprimer les 4 controllers dupliques jamais routes (PA2-ARCH-007), etendre
   `module-structure-check` a `SmartAttendance`/`EdgeSync` (PA2-ARCH-006).
4. **Discipline produit** : suspendre l'ajout de nouvelle surface fonctionnelle (ex. module
   Marketing/Ayrshare tout juste ajoute) au profit de la conversion commerciale, conformement
   a la propre analyse strategique du projet (`docs/GOTO_MARKET/LEOPARDO_STRATEGIC_ANALYSIS.md`, section 19).
5. **Historique git** : la fuite historique du mot de passe Redis Upstash (deja documentee
   dans `docs/audits/AUDIT.md`) reste recuperable dans l'historique tant qu'un nettoyage
   (`git filter-repo`/BFG) coordonne avec l'equipe n'a pas ete effectue.

---

*Document redige par Orion (agent KiloClaw), sur demande utilisateur, a titre de revue externe
independante. Publie sur une branche dediee (`orion/external-audit-2026-07-19`), sans
modification du code applicatif ni de la branche `main`.*
