# Tickets - alignement documentation infra

Date : 2026-04-25

## Objet

Transformer les ecarts identifies entre `docs/infra/Leopardo_RH_Architecture_Deploiement.pdf`
et l'etat reel du depot en tickets d'execution documentaires concrets.

Reference d'analyse :

- `docs/infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md`

## Regle

Chaque ticket ci-dessous doit aligner la documentation sur l'etat reel du depot
ou expliciter clairement qu'un point releve de la cible future et non de
l'operationnel courant.

## Tickets

| ID | Priorite | Sujet | Probleme actuel | Action attendue | Sources qui font foi | Critere de validation |
|---|---|---|---|---|---|---|
| INFRA-DOC-01 | P0 | Clarifier le statut du PDF | Le PDF peut etre lu comme source de verite autonome | Ajouter dans le PDF une page ou encart "statut documentaire" indiquant qu'il est non canonique | `PILOTAGE.md`, `docs/infra/README.md` | Un lecteur comprend en moins de 30s que le PDF est une reference de vision, pas un runbook |
| INFRA-DOC-02 | P0 | Separer present / cible / futur | Le PDF melange etat courant et cible Oracle | Reorganiser le document en 3 blocs : etat courant, cible proche, cible future | `PILOTAGE.md`, `docs/infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md` | Aucune section Oracle n'est interpretable comme "deja en service" |
| INFRA-DOC-03 | P0 | Corriger le deploiement Render | Le PDF decrit `render.yaml`, alors que le depot tourne avec Docker + workflow GitHub | Reecrire la section deploy Render a partir du flux reel | `api/Dockerfile.prod`, `api/docker-entrypoint.sh`, `.github/workflows/deploy-main.yml`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` | La doc deploy decrit le meme chemin que le repo et le runbook |
| INFRA-DOC-04 | P0 | Corriger le healthcheck | Le healthcheck du PDF n'est pas aligne sur le code actif | Documenter `/api/v1/health` comme endpoint canonique | `api/routes/api.php`, `api/app/Http/Controllers/Api/V1/HealthController.php`, `.github/workflows/deploy-main.yml` | L'URL documentee est exactement celle utilisee par le workflow de deploy |
| INFRA-DOC-05 | P1 | Corriger le runtime API | Le PDF parle de Laravel sur Render de facon generique et sous-entend un demarrage plus simple qu'en vrai | Documenter l'image Docker et FrankenPHP comme implementation reelle | `api/Dockerfile.prod`, `api/docker-entrypoint.sh` | La doc mentionne Docker + FrankenPHP + startup migrations |
| INFRA-DOC-06 | P1 | Corriger la stack locale | Le PDF laisse penser que Redis fait partie du local par defaut | Aligner la section local avec `api/docker-compose.yml` actuel | `api/docker-compose.yml`, `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` | La stack locale documentee correspond aux services reels demarrables depuis le repo |
| INFRA-DOC-07 | P1 | Corriger la partie Flutter | Le PDF documente des flavors absents du code principal | Reecrire la section mobile autour de `main.dart` + `dart-define` + workflows CI | `mobile/lib/main.dart`, `.github/workflows/tests.yml`, `.github/workflows/mobile-distribute.yml` | La doc mobile peut etre suivie sans chercher des fichiers `main_dev.dart` inexistants |
| INFRA-DOC-08 | P1 | Distinguer Android actif et iOS en preparation | Le PDF sur-vend un pipeline iOS equivalent a Android | Refaire la section mobile release en distinguant Android actif, iOS supporte mais non aligne au meme niveau d'automatisation | `.github/workflows/mobile-distribute.yml`, arborescence `mobile/ios/` | Un lecteur ne croit plus qu'un pipeline iOS complet est deja actif |
| INFRA-DOC-09 | P1 | Rebaser backup / restore | Le PDF reference des scripts de backup differents du runbook en vigueur | Remplacer la section sauvegarde par le processus reel de drill et de restauration | `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`, `scripts/backup_drill.sh` | La procedure documentee renvoie vers le script et le runbook actifs |
| INFRA-DOC-10 | P2 | Requalifier Oracle VPS | Oracle est presente comme trajectoire avancee | Transformer cette partie en roadmap infra avec hypotheses, dependances et preconditions | `PILOTAGE.md`, `CHANGELOG.md`, `docs/infra/ALIGNEMENT_PDF_REALITE_2026-04-25.md` | Oracle apparait clairement comme cible future conditionnelle |
| INFRA-DOC-11 | P2 | Aligner la terminologie multitenancy | Le PDF simplifie encore certains points alors que le code a evolue | Ajouter une note de cadrage sur l'evolution du scope et le fait que le code a depasse l'ancien MVP simplifie | `PILOTAGE.md`, `docs/AUDIT_v2_v3_COMPLIANCE.md` | Le lecteur ne repart pas avec une vision obsolete du mode multi-tenant |
| INFRA-DOC-12 | P2 | Ajouter les references canoniques dans le PDF | Le PDF ne redirige pas assez vers les runbooks actifs | Ajouter une section "pour operer aujourd'hui, voir..." | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `docs/GESTION_PROJET/RENDER_SETUP.md`, `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` | Le PDF devient un point d'entree qui redirige correctement vers les docs maitres |

## Ordre recommande

1. INFRA-DOC-01 a INFRA-DOC-04
2. INFRA-DOC-05 a INFRA-DOC-09
3. INFRA-DOC-10 a INFRA-DOC-12

## Etat d'avancement

Execution du 2026-04-25 :

- INFRA-DOC-01 : couvert dans `docs/infra/README.md` et `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-02 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-03 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-04 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-05 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-06 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-07 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-08 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-09 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-10 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-11 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`
- INFRA-DOC-12 : couvert dans `docs/infra/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md`

Note :

Le PDF binaire lui-meme n'a pas encore ete reedite dans ce depot. Les tickets P0
sont donc implementes a travers une reference markdown canonique de remplacement
pour l'etat courant.

## Livrable attendu

Quand cette serie est terminee, on doit avoir :

- un PDF mis a jour ou remplace par une version markdown canonisee,
- une distinction nette entre operationnel courant et cible future,
- et aucune contradiction majeure entre le depot, les runbooks et la doc infra.
