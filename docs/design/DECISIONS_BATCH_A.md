# Décisions d'architecture — Lot A (arbitrages fondateur)

**Date :** 2026-10-26
**Statut :** **tranché** — ces décisions sont exécutoires. Elles closent les issues
`decision-required` du lot A et débloquent les chantiers qui en dépendaient.
**Portée :** identité client, registre des bounded contexts, rail PSP, app mobile
grand public, périmètre du wallet, statut du check `Mobile smoke`.
**Méthode :** chaque arbitrage est rendu « comme un fondateur / DevOps / PM » —
on tranche dans l'intérêt du projet, on documente le raisonnement, et on
n'engage **aucune** ligne de code irréversible sans preuve.

> Règle de conduite appliquée : **une décision qui détruit des données n'est
> jamais la première étape.** On préfère systématiquement l'option additive et
> réversible, quitte à viser une cible plus ambitieuse en phase ultérieure.

---

## D1 — Identité client : `users` vs `travel_customers` (issues #8797, #8804, #8817)

**Décision : Option B — liaison additive par table `identity_links`.**

Le dépôt porte **trois** identités (`employees` en schéma tenant, `users` en
schéma public, `travel_customer_accounts` en schéma public) et le téléphone
n'est unique nulle part. Les trois options étaient :

| Option | Verdict |
|---|---|
| A — statu quo | Rejetée : BC-03 (cumul de rôles) reste impossible et une **quatrième** identité naîtra avec l'app mobile. |
| **B — liaison additive** | **Retenue.** Table `identity_links` + identifiant de personne stable, **guards inchangés**. Réversible d'un `DROP`. |
| C — compte unique | Cible à terme, mais mauvaise **première** étape : réécrit les trois guards et exige une réconciliation de doublons sur des tables vivantes. |

**Sous-questions tranchées :**

- **Q1 — deux lignes revendiquent le même téléphone : qui gagne ?**
  Aucune ne « gagne » automatiquement. La liaison est **explicite et
  consentie** : c'est l'utilisateur qui relie ses comptes après vérification
  d'un facteur (OTP sur le téléphone ou l'e-mail). Aucune fusion silencieuse.
- **Q2 — le téléphone est-il un identifiant de connexion ou de liaison ?**
  **De liaison uniquement** dans cette phase. L'authentification reste
  e-mail + mot de passe (+ `google_id` sur `users`). Le téléphone comme
  identifiant de premier rang est un chantier distinct (BC-03, #8736) et ne
  doit pas être couplé à cette décision.

**Conséquence :** l'app mobile grand public (#8807) devra **consommer**
l'identité existante via `identity_links`, jamais en créer une quatrième.

---

## D2 — Attribution de BC-37 et comptage des bounded contexts (issues #8798, #8805)

**Décision : BC-37 = FARM OS. Le registre canonique compte 38 entrées.**

Vérification faite dans le code : `dev-hub/governance/bounded-context-registry.json`
déclare déjà **38** bounded contexts, avec `BC-37 FARM OS` (`status: in_progress`,
`api/app/Modules/FarmOs`) et `BC-38 PAIEMENTS` (`api/app/Modules/Paiements`).
Le module `FarmOs` existe (107 fichiers) et est enregistré aux points exigés
(`ModuleRegistry`, `config/feature-flags.php`, `Company::KNOWN_MODULES`,
`routes/modules/farm_os.php`, `CODEOWNERS`).

- **BC-37** est donc **officiellement attribué à Farm OS** — l'option A de
  #8805. Aucune renumérotation, aucune collision avec #8572.
- **BC-22 ANALYTICS** est déclaré `planned` avec le chemin
  `api/app/Modules/Reporting` : le répertoire n'existe pas encore. Décision :
  **conserver l'entrée en `planned`** (c'est un BC planifié, pas une entrée
  morte) et ne pas le compter comme « actif » dans les décomptes de couverture.
- **Comptage officiel : 38 entrées**, dont 36 `active`, 1 `in_progress`
  (BC-37), 1 `planned` (BC-22).

**Conséquence :** toute nouvelle surface publique se rattache à un BC existant ;
il n'y a **pas** de numéro libre à réattribuer.

---

## D3 — Rail PSP réel pour BC-38 (issues #8799, #8806)

**Décision : rester en simulation (option C) et livrer d'abord le premier
consommateur de BC-38 avec le `SimulatorRail`.**

BC-38 est techniquement solide (50 fichiers, idempotence, ledger, webhooks
HMAC, remboursements) mais a **0 consommateur** et **1 seul rail simulé**.
Écrire une intégration PSP réelle sans réponse aux questions de conformité
(conservation de fonds → statut d'établissement de paiement, clé de production
et rotation, TVA par pays, 3-D Secure) produirait du code à jeter et exposerait
juridiquement.

- **Court terme :** brancher `paiements/internal` sur une commande réelle avec
  le `SimulatorRail`, et **prouver l'idempotence** (rejeu de la même clé ⇒
  **1** transaction). Cela rend BC-38 réellement exercé sans engager le
  fournisseur.
- **Cible :** Stripe Connect (modèle marketplace, le professionnel est marchand
  de référence) comme premier rail réel, puis un PSP local pour la Turquie.
  L'abstraction `PaymentRailInterface` le permet déjà.
- **Prérequis bloquant avant toute ligne de paiement réel :** validation
  juridique (qui porte la TVA, qui conserve les fonds) et secrets de production.

**Conséquence :** aucune ligne de paiement réel n'est écrite dans ce lot.

---

## D4 — Application mobile grand public (issues #8800, #8807)

**Décision : Option A — pas d'app grand public ; `front/web` mobile-first.**

Le parcours public (découverte → fiche → conversation) est **déjà** fonctionnel
sur `front/web` en viewport mobile (vérifié par Playwright). L'absence d'app
n'est donc **pas** un blocage fonctionnel.

- Aucun coût store, un seul parcours à maintenir, cohérent avec la stratégie B
  de l'audit (expérience publique commune dans `front/web`).
- Contrainte de qualité reportée sur `front/web` : **mobile-first** est une
  exigence, pas une option.
- Une app grand public reste possible **après P2**, à condition que le web
  expose déjà la conversation et la découverte de façon stable — sinon l'app
  hériterait de la dette.

---

## D5 — Périmètre d'autorisation du wallet BC-38 (issue #8816)

**Décision : le périmètre par défaut des policies est confirmé, avec deux
précisions.**

| Opération | `principal` | `rh` | employé |
|---|---|---|---|
| Lire le wallet / l'historique | oui | oui | non |
| Demander un retrait | oui | non | non |
| Opérer sur une transaction | oui | non | non |

- **Q1 — le `rh` voit-il le solde ou seulement l'historique ?**
  **Solde + historique.** Le `rh` a besoin du solde pour la paie et le
  pilotage ; lui cacher le solde le forcerait à des contournements.
- **Q2 — la demande de retrait exige-t-elle une double validation ?**
  **Non en phase 1.** Seul le `principal` engage le compte bancaire du tenant ;
  une double validation est une complexité d'UX à ajouter seulement si un
  incident réel la justifie. Le point d'extension est laissé ouvert.
- **Q3 — la surface plateforme (Superadmin) reste-t-elle hors de ces
  policies ?** **Oui.** Le Superadmin passe par ses propres gardes ; ces
  policies couvrent le tenant.

**Conséquence :** les contrôleurs peuvent désormais appeler `authorize()` avec
ce périmètre (la fondation et ses 9 tests existent déjà).

---

## D6 — `Mobile smoke` bloquant sur `main` ? (issue #8815)

**Décision : option (b) — garder informatif, puis rendre requis après une
fenêtre d'observation de ~20 passages verts consécutifs.**

Le coût est faible (1 job, ~4 min) mais le risque réel n'est pas la durée :
c'est la **dépendance au cache Gradle distant**. Un incident de cache ferait
échouer un job *bloquant* pour une raison sans rapport avec la PR, donc
bloquerait **tous** les merges. Le workflow n'existe que depuis le 2026-10-20.

- En cas de flakiness : ajouter un `retry` ciblé sur l'étape Gradle plutôt que
  retirer le check.
- La protection de `main` n'est pas modifiée par cette décision : l'acte reste
  manuel et sera posé au terme de la fenêtre d'observation.

---

## D7 — Nommage du dépôt `leopardo-hr` (issue #7848)

**Décision : conserver le nom `leopardo-hr` à court terme ; le renommage est
un acte de gouvernance planifié, pas un prérequis.**

Le renommage d'un dépôt public casse les URLs, les clones, les webhooks et les
références CI. Il est documenté (ADR de renommage, #7848) et sera exécuté
lors d'une fenêtre dédiée, avec redirections vérifiées. Aucun chantier de code
n'est bloqué par ce point.

---

## Suivi

| Issue | Décision | Statut |
|---|---|---|
| #8797 | D1 — Option B (liaison additive) | tranchée |
| #8804 | D1 — Option B | tranchée |
| #8817 | D1 — Option B + Q1/Q2 | tranchée |
| #8798 | D2 — BC-37 = Farm OS, 38 entrées | tranchée |
| #8805 | D2 — BC-37 = Farm OS, BC-22 `planned` | tranchée |
| #8799 | D3 — simulation + premier consommateur | tranchée |
| #8806 | D3 — Stripe Connect en cible, validation légale préalable | tranchée |
| #8800 | D4 — web mobile-first | tranchée |
| #8807 | D4 — pas d'app grand public | tranchée |
| #8816 | D5 — périmètre wallet confirmé | tranchée |
| #8815 | D6 — informatif puis requis après observation | tranchée |
| #7848 | D7 — renommage planifié, non bloquant | tranchée |
