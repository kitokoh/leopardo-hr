# Strategie anti-hardcode I18N (PA2-I18N-001)

Statut: complete
Auteur: audit/consolidation interne KiloClaw (agent)
Ticket: `PA2-I18N-001` (Issue #1007), P0, surface `docs/tools`.

## Contexte

`PA2-I18N-001` demande une strategie formelle anti-hardcode avec trois livrables explicites dans `02_BACKLOG_ATOMIQUE.md` : **(1)** un guide Jules, **(2)** une mesure de dette par surface, **(3)** une interdiction du nouveau texte dur critique. Les trois briques existaient deja dans le depot, livrees independamment sous d'autres tickets (`PA2-I18N-007`, `PA2-I18N-014`, `PA2-I18N-015`) sans jamais avoir ete reliees entre elles ni formalisees comme *la* strategie anti-hardcode du projet — exactement le meme schema deja rencontre sur `PA2-JOB-002/004/006` (cf. `17_AUDIT_STATUT_PA2_JOB_001_A_006.md`) : du travail reel livre, jamais rattache a son ticket d'origine. Ce document ferme cet ecart : il ne cree pas de nouveaux outils, il documente la strategie qui relie ceux qui existent deja et comble les trous restants (couverture backend manquante dans le guide de garde CI).

## 1. Guide Jules (traducteur/relecteur)

`docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md` est le guide de reference pour le role "Jules" (traducteur/relecteur, pas refactor code) :

- Perimetre autorise : catalogues canoniques (`shared/i18n/locales/*.json`, `glossary.json`), catalogues mobiles compiles (`.arb`), catalogues admin-dashboard.
- Interdiction explicite de toucher aux controllers Laravel, repositories mobiles, widgets/ecrans Flutter, composants React/Vue, routes, tests, workflows CI, fichiers `generated/*`.
- Regle de remontee : si Jules detecte un texte hardcode existant, il **propose la cle** a ajouter plutot que de refactorer lui-meme le composant — la responsabilite technique de brancher la cle reste cote agent technique.

Ce guide reste la source de verite pour ce role ; aucune duplication n'est necessaire ici.

## 2. Dette par surface

La dette de texte hardcode est mesuree en continu par `dev-hub/tools/i18n-debt.js` (`PA2-I18N-015`, remplace l'ancien scanner PowerShell qui comptait a tort les classes utilitaires CSS/Tailwind comme du texte et gonflait le total a 11 642 signaux) :

- Scanne toutes les surfaces a risque : mobile (`leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`), `admin-dashboard`, `web`, `zkteco-kiosk`, vues PDF/email backend.
- Separe le texte utilisateur visible (compte P1/P2 par priorite d'ecran) du texte developpeur/log uniquement (`console.*`, `print`, `debugPrint`, `Log.*`, `// TODO`) — ce dernier n'est **pas** compte comme dette i18n.
- Genere un rapport horodate dans `docs/validation/I18N_DEBT_REPORT_<date>.md` (dernier en date : `I18N_DEBT_REPORT_2026_07_22.md`, 7 604 signaux utilisateur au total, detail par surface et par fichier/ligne).
- Mode `--strict` disponible pour faire echouer un run (CI ou local) tant que la dette P1 (ecrans prioritaires : login/pointage/compte/...) n'est pas resorbee — non active en continu aujourd'hui (la dette existante est trop large pour bloquer tout le pipeline d'un coup), reserve a un usage cible (ex: gate sur un module en cours de nettoyage).

**Cadence recommandee** : regenerer le rapport a chaque fin de sprint ou avant une revue i18n, en commitant le nouveau `I18N_DEBT_REPORT_*.md` (permet de suivre la tendance dans le temps sans dependre d'un dashboard externe).

## 3. Interdiction du nouveau texte dur critique

Deux gardes CI complementaires empechent la dette de **croitre**, sans jamais bloquer sur la dette pre-existante (qui reste trackee separement via le point 2) :

### 3.1 Frontend / mobile / kiosk / vues PDF-email (`PA2-I18N-014`)

`.github/workflows/i18n-enterprise.yml`, job `check-new-hardcoded-strings` (bloquant sur toute pull request touchant les surfaces a risque) execute `dev-hub/tools/check-i18n-diff.js <base_sha> <head_sha>` :

- N'inspecte que les **lignes ajoutees** du diff (la dette pre-existante ne bloque jamais une PR non liee).
- Ignore les lignes qui passent deja par un appel de traduction connu (`__()`, `AppLocalizations`, `t()`, etc.) — pas de faux positif sur l'usage legitime du catalogue.
- Couvre : `leopardo_employee`/`leopardo_manager`/`leopardo_platform_admin` (Dart), `zkteco-kiosk` (JS/HTML), `admin-dashboard` (Vue/JS), `front/web` app/modules (TSX/TS), vues Blade PDF/email de l'API.

### 3.2 Backend API — controllers (`PA2-I18N-007`)

`dev-hub/tools/check-hardcoded-accented-messages.sh`, deja cable en tant que garde bloquante dans `.github/workflows/architecture-check.yml` (`dev-hub/tools/check-hardcoded-accented-messages.sh "${base_sha}" "${head_sha}" api`) :

- Meme principe (diff-only, jamais bloquant sur la dette existante) mais heuristique dediee au backend : detecte une nouvelle chaine litterale contenant un caractere accentue latin a l'interieur d'un fichier `*Controller.php` ajoute/modifie, hors lignes de commentaire et hors lignes utilisant deja `__()`/`trans()`.
- Limite connue et documentee dans le script lui-meme : heuristique grep, pas un parseur PHP complet (faux positifs possibles sur une constante/enum accentuee ; faux negatifs sur du francais sans accent, ex. "Le prix"). Les revues de code restent la seconde ligne de defense.

### 3.3 Couverture combinee

| Surface | Dette mesuree (point 2) | Garde anti-regression (point 3) |
|---|---|---|
| Mobile (employee/manager/platform_admin) | `i18n-debt.js` | `check-i18n-diff.js` (workflow `i18n-enterprise.yml`) |
| `admin-dashboard`, `front/web`, `zkteco-kiosk` | `i18n-debt.js` | `check-i18n-diff.js` (workflow `i18n-enterprise.yml`) |
| Vues PDF/email API | `i18n-debt.js` | `check-i18n-diff.js` (workflow `i18n-enterprise.yml`) |
| Controllers API (`*Controller.php`) | `i18n-debt.js` (couvre aussi le backend) | `check-hardcoded-accented-messages.sh` (workflow `architecture-check.yml`) |

Les deux gardes tournent dans des workflows distincts (`i18n-enterprise.yml` vs `architecture-check.yml`) pour des raisons historiques (livrees sous deux tickets differents, `PA2-I18N-014` puis `PA2-I18N-007`) mais sont toutes les deux bloquantes en pull request et suivent le meme principe diff-only. Aucune fusion n'est necessaire : elles ciblent des types de fichiers disjoints (`*Controller.php` cote backend vs surfaces front/mobile/kiosk/vues cote guard #1) et une fusion ajouterait de la complexite sans reduire la surface de risque.

## Recapitulatif — statut des trois criteres d'acceptation

| Critere (acceptance criteria du ticket) | Statut | Preuve |
|---|---|---|
| Guide Jules | Fait (deja livre) | `docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md` |
| Dette par surface | Fait (deja livre) | `dev-hub/tools/i18n-debt.js` + `docs/validation/I18N_DEBT_REPORT_2026_07_22.md` |
| Interdiction nouveau texte dur critique | Fait (deja livre, deux gardes) | `dev-hub/tools/check-i18n-diff.js` (workflow `i18n-enterprise.yml`) + `dev-hub/tools/check-hardcoded-accented-messages.sh` (workflow `architecture-check.yml`) |

Aucun changement de code applicatif n'a ete necessaire pour ce ticket : le travail restant etait de consolider et documenter la strategie qui relie les livrables deja existants, afin que `PA2-I18N-001` (Issue #1007) puisse etre ferme en connaissance de cause plutot que de rester ouvert indefiniment malgre un travail deja fait.

## Verification

- Lecture directe du code source des trois outils (`i18n-debt.js`, `check-i18n-diff.js`, `check-hardcoded-accented-messages.sh`) et de leurs points d'integration CI (`i18n-enterprise.yml`, `architecture-check.yml`) pour confirmer qu'ils sont bien cables et bloquants, pas simplement presents sur disque.
- Verification que `docs/validation/I18N_DEBT_REPORT_2026_07_22.md` est bien le rapport le plus recent genere par `i18n-debt.js` (pas un rapport de l'ancien scanner PowerShell obsolete).
- Aucun test automatise applicable (document uniquement, pas de code modifie).
