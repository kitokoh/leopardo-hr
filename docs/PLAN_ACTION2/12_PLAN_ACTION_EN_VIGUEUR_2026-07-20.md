# Plan d'action en vigueur — 2026-07-20

Statut : plan actif, remplace la lecture "a la volee" des audits precedents par une liste unique et priorisee.
Auteur : audit + plan d'action KiloClaw, a la demande de kitokoh (via chat).
Perimetre couvert : etat reel du depot `main` (commit `fbb19ab`), CI/CD live, deploiements live (Render, Vercel), issues/PR GitHub ouvertes, croisement avec `08_*`/`09_*`/`10_*`/`11_*`/`11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md` et PR #905 (audit mobile, non mergee).

Ce document ne repete pas le contenu des audits precedents. Il fait trois choses :
1. Confirme ce qui est **reellement** fait vs pas fait a la date du jour, avec verification live (pas seulement lecture de doc).
2. Signale 3 problemes **non captures** par les audits precedents (production cassee, garde CI actionlint rouge, gouvernance CI insuffisante sur `main`).
3. Fournit un plan d'action unique, ordonne, avec nouveaux tickets `PA2-OPS-*` a ajouter au backlog.

---

## 1. Ce qui fonctionne reellement (verifie en direct, pas seulement lu dans les docs)

- **API production up** : `GET https://gestionemployerbackend.onrender.com/api/v1/health` repond `200 {"status":"ok","version":"4.16.253", ...}`, DB et queue OK. Redis marque `"status":"skipped"` (pas branche en prod actuellement, a ne pas confondre avec l'incident de secret expose, cf section 2.1).
- **Vitrine Next.js reellement en ligne et fonctionnelle** a l'URL reelle `https://gestionemployer-backend.vercel.app/` (nom trompeur, ressemble a un backend, c'est le frontend marketing) : page d'accueil et `/pricing` repondent `200`, contenu produit reel, pas de `noindex`, pas de SSO bloquant contrairement a ce que documentait `11_AUDIT_VITRINE_ACQUISITION.md` (ce constat semble corrige depuis, ou concernait une URL de preview differente de la production actuelle).
- **PA2-ARCH-001 (TaxSlab jamais branche dans PayrollCalculator) est desormais reellement corrige** : PR #903 mergee le 2026-07-20 02:16, verifie dans le code (`AbstractCountryRules.php:62` appelle bien `TaxSlab::query()->forCountry(...)->effective()`). C'etait le point P0 le plus critique de `11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md` — **traite depuis la publication de cet audit**, a rayer des priorites actives.
- **Discipline CI/qualite reelle** : `PHPStan strict (level 8)`, `Module Structure Validator`, `CodeQL`, `TruffleHog`, `Frontend ESLint/TypeScript`, `Validate PLAN_ACTION2 backlog` sont tous verts sur `main` au moment de cet audit.
- **Rythme de correction eleve** confirme une nouvelle fois : la quasi-totalite des findings "ouverts" listes dans `08_*/09_*/10_*` et meme dans `11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md` (publie hier) sont deja corriges aujourd'hui.

## 2. Ce qui n'est PAS fait — verifie en direct aujourd'hui

### 2.1 🔴 P0 — Deploiement de production cassé sur `main` (nouveau, non capture par les audits precedents)

`GET /repos/kitokoh/leopardo-hr/commits/main/status` renvoie `"state": "failure"` : le dernier deploiement Vercel du commit `fbb19ab` (HEAD de `main`) a **echoue** (`dpl_7KBuu8SF3T62PVQ89ZApAFF6towQ`, statut `failure`, puis un second essai reste `pending`). Concretement : le dernier merge sur `main` (#908, reorganisation de fichiers `.md` racine — un changement purement documentaire) n'a **pas** ete deploye avec succes en production. La version live actuellement servie sur `gestionemployer-backend.vercel.app` peut donc etre en retard sur `main`, ou le prochain vrai changement de code risque de deployer sur une base deja en echec.
**Action** : inspecter le log Vercel (`npx vercel inspect dpl_7KBuu8SF3T62PVQ89ZApAFF6towQ --logs`) avant tout nouveau merge sur `main` ; ne pas empiler de nouveaux merges sans avoir confirme qu'un deploiement reussit.

### 2.2 🔴 P0 — Garde CI `actionlint` rouge sur `main` (nouveau, non capture par les audits precedents)

Le check `actionlint (+ shellcheck)` echoue sur `main` (`fail_level: error`) a cause de `.github/workflows/mobile-distribute.yml` :
- ligne ~109 : `SC2129` (multiples redirections `>>` au lieu d'un bloc groupe) — style, non bloquant fonctionnellement.
- ligne ~276 : `SC2016` — une expression `'...'` en single-quote qui ne s'interpole pas comme attendu dans un message affiche, a verifier si c'est voulu (texte litteral) ou un bug (variable qui devait s'interpoler et ne le fait pas).
**Impact** : un garde CI cense empecher la reapparition de problemes de workflow est actuellement rouge sur la branche par defaut elle-meme, ce qui degrade la confiance dans tous les autres garde-fous verts.

### 2.3 🟠 P1 — Gouvernance CI insuffisante : un seul check obligatoire sur `main` (nouveau, non capture par les audits precedents)

`branches/main/protection` montre `required_status_checks.contexts = ["Backend Coverage (PHP 8.4 + PostgreSQL 16)"]` **uniquement**. Tous les autres checks verts constates (PHPStan strict, Module Structure Validator, CodeQL, ESLint/TypeScript, TruffleHog, Validate PLAN_ACTION2 backlog, actionlint) sont **informatifs, pas bloquants** pour un merge sur `main`. `enforce_admins` est aussi desactive, donc un admin peut pousser directement sans review ni check. Consequence concrete deja observee : PR #904 (fermee sans merge, heureusement) contenait des fichiers de debug committes par erreur (`backend_log.txt`, `ci-report.md`) qui n'auraient pas ete bloques par la seule regle actuelle si elle avait ete mergee.
**Action recommandee** : ajouter au minimum `PHPStan — Strict`, `Module Structure Validator`, `Frontend — ESLint + TypeScript` et `actionlint` a `required_status_checks.contexts` une fois qu'`actionlint` repasse vert (2.2).

### 2.4 🔴 P0 confirme — Rotation secret Redis Upstash + purge historique git toujours non faites

`docs/security/SECURITY_INCIDENT_REDIS_2026-07.md` documente l'incident depuis le 2026-07-01, "nettoyage doc" fait le 2026-07-19, mais **la rotation reelle du mot de passe Upstash et la purge d'historique git restent non faites** (actions manuelles hors perimetre agent, necessitent acces dashboard Upstash/Render). Le secret reste valide et recuperable dans l'historique git public tant que la rotation n'est pas faite — **c'est le point le plus urgent de tout ce document du point de vue securite**, il ne peut pas etre traite par un agent, seulement par kitokoh.

### 2.5 🔴 P0 confirme — RBAC "superviseur" vendu mais non implemente

Confirme par lecture directe du code aujourd'hui : aucune logique de scoping "assigned-only" pour `manager_role = 'superviseur'` dans `api/app/Policies/*.php`. Le role se comporte comme `principal`/`rh` (perimetre entreprise complet), alors que `RBAC_SYSTEM.md` le documente comme "Assigned-only" — argument de vente potentiel pour le secteur securite privee (priorite #1 du GTM). Deja trackee comme `PA2-SEC-003`/`PA2-SEC-004`, toujours ouverte.

### 2.6 🟠 P1 confirme — Emails transactionnels : localisation tres partielle

Verification directe aujourd'hui sur les fichiers reels : `api/resources/views/emails/trial/{day_one,day_three,day_seven,day3,expired,expiring}.blade.php` ont chacun **0 occurrence** de `__(`. Seul `cabinet-share.blade.php` utilise le catalogue i18n. `PA2-I18N-006` (deja dans le backlog, P0) reste donc non avance malgre le volume de PR mergees cette semaine sur d'autres sujets.

### 2.7 🟡 P1 confirme — Vitrine : avatars temoignages casses et marques "trusted by" non prouvees

Verifie directement aujourd'hui : `front/web/public/avatars/` ne contient que des SVG nommes (`ahmed.svg`, `dubois.svg`, `fatima.svg`, ...), **aucun** `avatar-1.webp` a `avatar-4.webp` alors que `data/testimonials.ts` reference ces 4 fichiers a repetition (8+ occurrences) → icones cassees garanties en prod. `TrustedBrands.tsx` liste 22 marques reelles et connues (Arcelik, Sonatrach, SAP, Aramco, Emirates, Turkish Airlines, Dangote, MTN, ...) sous le badge implicite "Ils nous font confiance" sans aucune preuve de relation client — risque reputationnel et potentiellement legal (utilisation non autorisee du nom/de la reputation d'entreprises tierces) plus serieux qu'un simple defaut visuel. Deja trackees `PA2-MKT-010`/`PA2-MKT-011`, toujours ouvertes.

### 2.8 🟡 P1 confirme — Dette design/UX mobile (audit PR #905, pas encore mergee)

PR #905 (ouverte, mergeable, non revue) documente 4 nouveaux constats chiffres non contestes par ce present audit : 106+36+37 litteraux de couleur hex dupliques dans les ecrans de pointage (coeur produit) des 3 apps employee/manager/hr, mode sombre force en dur dans les 4 apps sans possibilite de choix utilisateur (contredit le commentaire du code lui-meme), `leopardo_platform_admin` a 0 usage des composants partages (`PulseButton`, `LeopardoBadge`, `LeopardoQrCard`), et aucune preuve CHANGELOG pour les tickets `PA2-MOB-006` a `010` (bloque le risque de refaire un travail deja livre, notamment `PA2-MOB-009` qui semble deja code sans avoir ete trace/ferme).

### 2.9 🟡 P2 confirme — Domaine de production incoherent entre les docs

3 sources internes se contredisent : `PILOTAGE.md` dit `leopardo-hr.vercel.app` (verifie aujourd'hui : **404**) ; `docs/DEPLOYMENT_PRODUCTION.md` dit `leopardo.com` (verifie aujourd'hui : sert le site d'une entreprise americaine de construction sans rapport, domaine jamais achete pour ce produit) ; `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` dit `gestionemployer-backend.vercel.app` (verifie aujourd'hui : **c'est la bonne URL, reellement en ligne**). Un prospect qui suit `PILOTAGE.md` ou `docs/DEPLOYMENT_PRODUCTION.md` littéralement n'arrive jamais sur le vrai produit.

### 2.10 🟡 P2 — Issue GitHub #761 jamais triee (plus d'un mois)

Issue `[FEATURE]: Ajouter option de pointage kiosque par clic ou photo (arrivee/depart)`, ouverte le 2026-06-14 (36 jours), labelisee `enhancement` seulement, jamais liee a un ticket `PA2-*` ni au backlog. Demande client potentiellement pertinente (controle visuel de presence) qui risque de se perdre.

### 2.11 🟡 P2 — CI Mobile Flutter instable sur `main`

`Mobile Apps CI - Flutter` : 4 echecs sur les 5 derniers runs sur `main` (seul le plus recent est vert). Pas un audit de code ici (pas d'environnement Flutter local), mais un signal de fragilite a surveiller avant de vendre les 3 apps mobiles a un premier client pilote.

---

## 3. Plan d'action priorise

### 🔴 Immediat (aujourd'hui, avant tout nouveau merge sur `main`)

1. **PA2-OPS-001** — Diagnostiquer et corriger l'echec de deploiement Vercel sur `main` (`dpl_7KBuu8SF3T62PVQ89ZApAFF6towQ`). Ne pas merger de nouvelle PR sur `main` avant confirmation qu'un deploiement reussit a nouveau.
2. **PA2-OPS-002** — Corriger les 2 findings `actionlint`/`shellcheck` sur `.github/workflows/mobile-distribute.yml` (grouper les redirections `SC2129` ligne ~109 ; clarifier l'intention de la single-quote `SC2016` ligne ~276 — variable a interpoler ou texte litteral voulu).
3. **[Action humaine, hors agent]** — kitokoh : rotation du mot de passe Upstash + mise a jour Render, PUIS purge d'historique git (`git filter-repo`) coordonnee avec toute PR ouverte. Voir procedure complete dans `docs/security/SECURITY_INCIDENT_REDIS_2026-07.md` section 3. C'est la seule action de ce document qui ne peut pas etre faite par un agent.

### 🔴 P0 — Cette semaine

4. **PA2-OPS-003** — Elargir `required_status_checks` sur `main` pour inclure au minimum `PHPStan — Strict (Core/Modules/Shared, level 8)`, `Module Structure Validator`, `Frontend — ESLint + TypeScript` et `actionlint` (une fois vert), en plus du check existant. Objectif : rendre les garde-fous CI deja construits reellement bloquants, pas seulement informatifs.
5. **PA2-SEC-003** (deja backlog, reformuler DoD) — Trancher explicitement le role "superviseur" avant tout pilote secteur securite privee : soit implementer le scoping assigned-only reel, soit le retirer de `RBAC_SYSTEM.md` et de toute doc commerciale. Ne pas laisser une fonctionnalite RBAC vendue-mais-fictive face a un premier client.
6. **PA2-I18N-006** (deja backlog, confirme non avance) — Localiser au moins les 6 templates trial (`day_one`, `day_three`, `day_seven`, `day3`, `expired`, `expiring`) en priorite, avant les 10 autres — ce sont les emails qu'un prospect trial recoit en premier, donc les plus visibles a l'international.
7. **PA2-MKT-010** (deja backlog) — Corriger les 4 avatars temoignages casses (`avatar-1..4.webp` inexistants) : soit fournir de vrais fichiers, soit repointer vers les SVG nommes existants (`public/avatars/*.svg`), soit fallback initiales.
8. **PA2-MKT-011** (deja backlog, escalader la severite) — Trancher le statut de `TrustedBrands.tsx` : ce n'est plus seulement un probleme de credibilite marketing mais un risque potentiel d'usage non autorise de marques tierces reelles (Aramco, SAP, Turkish Airlines, etc.). Retirer ou requalifier en "secteurs adresses" avant toute mise en avant commerciale externe.

### 🟠 P1 — Ce mois

9. **PA2-OPS-004** — Corriger la documentation d'URL de production incoherente (`PILOTAGE.md`, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md`) pour converger sur l'URL reellement en ligne (`gestionemployer-backend.vercel.app` ou un futur domaine achete) ; retirer `leopardo.com` de toute doc tant qu'il n'est pas achete (deja `PA2-MKT-008`, a executer).
10. **Revue et merge des PR ouvertes existantes** (pas de nouveau ticket, discipline d'execution) :
    - PR #909 (`PA2-ARCH-009` strict_types retrofit) : mergeable, la seule des 3 PR de code ouvertes prete techniquement — a revoir/merger en premier.
    - PR #906 (fix vitrine OTP) : `behind main`, necessite un rebase avant merge.
    - PR #907 (Marketing Phase 4 UI) : `mergeable=false/dirty` (conflits reels), a rebaser par l'auteur avant toute revue.
    - PR #905 (audit mobile design/UX) : documentaire seule, mergeable, aucun risque — a merger rapidement pour ne pas perdre les 4 tickets `PA2-MOB-011` a `014` qu'elle introduit.
11. **PA2-MOB-014** (introduit par PR #905) — Auditer et clore explicitement `PA2-MOB-006` a `009` avant de coder quoi que ce soit dessus, `PA2-MOB-009` en priorite (code deja present, jamais trace).
12. **PA2-OPS-005** — Trier l'issue GitHub #761 (pointage kiosque par photo) : l'affecter a un ticket `PA2-KIO-*` existant ou creer `PA2-KIO-005` dedie, avec decision produit explicite (mode photo optionnel par entreprise) plutot que de la laisser sans suite plus d'un mois.

### 🟡 P2 — Suivi continu

13. **PA2-OPS-006** — Stabiliser `Mobile Apps CI - Flutter` sur `main` (4 echecs / 5 derniers runs) avant tout engagement de date de livraison mobile a un pilote.
14. Confirmer, une fois `AUDIT-P0-2`/`AUDIT-P0-3` de `11_AUDIT_CONSOLIDE_TECHCOMMERCIAL_2026-07-19.md` traites et un premier client pilote signe, la recommandation deja ecrite dans ce meme document : geler la production de nouveaux audits internes tant qu'aucune traction commerciale reelle n'existe. Ce document en fait volontairement un usage cible (deploiement casse + garde CI rouge, deux points factuels et actionnables) plutot qu'un nouvel audit exhaustif redondant.

---

## 4. Recapitulatif executif

| Domaine | Etat reel au 2026-07-20 (verifie en direct) | Severite |
|---|---|---|
| API production (Render) | En ligne, health check OK | ✅ OK |
| Vitrine production (Vercel) | Contenu reel en ligne a la bonne URL, mais **dernier deploiement `main` en echec** | 🔴 P0 |
| Garde CI `actionlint` | Rouge sur `main` (2 findings shellcheck reels) | 🔴 P0 |
| Gouvernance branche `main` | 1 seul check obligatoire sur ~10 checks verts disponibles | 🟠 P1 |
| Secret Redis expose | Doc nettoyee, rotation + purge historique **toujours non faites** | 🔴 P0 — action humaine uniquement |
| RBAC superviseur assigned-only | Toujours non implemente, vendu dans la doc | 🔴 P0 |
| TaxSlab branche dans PayrollCalculator | ✅ **Corrige depuis hier** (PR #903 mergee) | ✅ Resolu |
| Emails transactionnels localises | 1/17 gabarits (confirme, aucun progres depuis hier) | 🟠 P1 |
| Avatars temoignages / marques "trusted by" | Toujours casses / toujours non prouvees | 🟠 P1 |
| Audit mobile design/UX (PR #905) | Documente, pas encore merge, pas encore code | 🟡 P2 |
| Coherence doc domaine de production | 3 URLs differentes citees dans 3 docs, 1 seule correcte | 🟡 P2 |
| Issue #761 (kiosque photo) | Jamais triee, 36 jours | 🟡 P2 |
| CI Mobile Flutter | Instable (4 echecs / 5 derniers runs) | 🟡 P2 |
