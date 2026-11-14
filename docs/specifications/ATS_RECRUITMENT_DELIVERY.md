# ATS — Recrutement : état des lieux et livraison

> Branche de travail : `main` du dépôt `kitokoh/leopardo-hr` (clone du 2026-11-13).
> Périmètre : parcours candidat, suivi recruteur, moteur ATS.

## 1. Ce qui existait déjà (vérifié, pas supposé)

Le module `Recruitment` était **déjà largement implémenté** côté API et côté
portail public. Le diagnostic a consisté à le prouver par la lecture du code et
par l'exécution des tests, pas à le supposer.

### Parcours candidat — fonctionnel avant intervention

| Élément | Emplacement | État |
|---|---|---|
| Catalogue d'offres publiques | `GET /api/v1/public/careers/{companySlug}` | OK |
| Détail d'une offre | `GET /api/v1/public/careers/{companySlug}/jobs/{id}` | OK |
| Dépôt de candidature | `POST .../jobs/{id}/apply` | OK |
| Flux XML (Google Jobs / Indeed) | `GET .../feed.xml` | OK |
| Annuaire des portails | `GET /api/v1/public/careers` | OK |
| Page liste + recherche + filtres | `front/web/src/app/[companySlug]/careers/page.tsx` | OK |
| Page détail + formulaire | `.../careers/jobs/[jobId]/page.tsx` + `ApplyForm.tsx` | OK |

Le formulaire gère déjà : upload de CV (5 Mo max, PDF/DOC/DOCX), consentement
RGPD **décoché par défaut**, champ piège anti-spam, throttling dédié
(`public-careers-apply`), et anti-doublon `(job_posting_id, email)` avec rattrapage
de la violation d'index unique PostgreSQL (`23505`).

**Conclusion : rien à construire ici.** Le travail a consisté à le vérifier
(23 tests front + tests API publics) plutôt qu'à le réécrire.

### Ce qui manquait réellement

1. **Aucun historique de statut.** `applicants.status` était une colonne
   écrasée. Impossible de savoir qui avait rejeté un candidat, ni quand, ni
   pourquoi — et impossible de mesurer le délai réel par étape.
2. **Aucun moteur de filtrage.** Les candidatures ne pouvaient être listées que
   par offre, sans filtre par statut, note, ancienneté ou recherche.
3. **Aucun écran recruteur.** Le back-office n'avait ni route ni vue pour le
   pipeline : `front/admin-dashboard/src/views/recruitment/` ne contenait que
   l'import d'offres, et `front/web` n'avait aucune page `/recruitment`.
4. **`ApplicantResource` exposait deux champs fantômes** : `stage` et
   `resume_url`, qui n'existent ni en base ni sur le modèle. La clé `stage`
   sortait donc toujours à `null` — un écran branché dessus n'aurait jamais pu
   afficher l'étape d'un dossier.

## 2. Ce qui a été livré

### 2.1 Journal de statuts (append-only)

`applicant_status_events` — une ligne par changement, jamais modifiée.

- `from_status` / `to_status` : la transition exacte.
- `actor_id` / `actor_name` : **qui**. `null` = dépôt public ou tâche
  automatique — on ne fabrique pas un employé fantôme.
- `reason` : **pourquoi** (facultatif, 500 car.).
- `occurred_at` : **quand**.

Un changement de statut identique (`screening → screening`) **n'écrit rien** :
un événement de bruit ferait croire à une action du recruteur.

Le journal est écrit **avant** l'application du statut : si l'écriture échoue,
le statut ne bouge pas. L'inverse laisserait un dossier dans un état que rien
ne documente.

### 2.2 Moteur ATS

`AtsApplicantFilter` traduit des critères déclaratifs en requête :

| Critère | Effet |
|---|---|
| `statuses[]` | filtre multi-statuts |
| `min_rating` | note minimale (1-5) |
| `source` | site / cooptation / LinkedIn / cabinet / autre |
| `q` | recherche nom, prénom, email, lettre |
| `older_than_days` / `newer_than_days` | ancienneté de la candidature |
| `resume_scan_status` | état du scan antivirus du CV |
| `sort` | `recent` (défaut), `oldest`, `rating`, `name` |

Deux garde-fous de sécurité :

- **Liste blanche stricte** : aucun nom de colonne ne vient de l'extérieur. Un
  filtre sauvegardé rejoué plus tard ne peut pas devenir une injection SQL.
- **Échappement des jokers LIKE** : `%` et `_` sont neutralisés. Sans cela, une
  recherche sur `%` renvoyait **tous** les dossiers.

**Score de correspondance** : pourcentage des compétences déclarées sur l'offre
(`job_postings.skills_required`) réellement présentes dans la lettre de
motivation du candidat. Retourne `null` — et non `0` — quand l'offre ne déclare
aucune compétence : « non calculable » n'est pas « mauvais profil ».

### 2.3 API recruteur

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/v1/recruitment/ats/applicants` | pipeline transverse + compteurs |
| GET | `/api/v1/recruitment/ats/jobs/{id}/applicants` | pipeline d'une offre |
| GET | `/api/v1/recruitment/ats/applicants/{id}` | dossier + historique + score |
| PATCH | `/api/v1/recruitment/ats/applicants/{id}/status` | déplacement journalisé |
| GET | `/api/v1/recruitment/applicants/{id}/resume` | téléchargement du CV |
| GET/POST | `/api/v1/recruitment/ats/filters` | filtres sauvegardés |
| DELETE | `/api/v1/recruitment/ats/filters/{id}` | suppression |

Isolation tenant : chaque requête est bornée à `company_id` de l'acteur. Un
identifiant d'une autre entreprise répond **404, jamais 403** — on ne confirme
pas l'existence d'un dossier qu'on n'a pas le droit de voir.

### 2.4 Interface recruteur

`front/web/src/app/(dashboard)/recruitment/page.tsx` — entrée « Recrutement »
dans le groupe RH de la navigation.

- 6 compteurs cliquables (nouveaux, présélection, entretiens, offres, recrutés,
  refusés) qui filtrent la liste.
- Recherche plein texte, sélecteur d'offre, filtres avancés (note minimale,
  ancienneté), réinitialisation.
- Tableau : candidat, offre, source, date, note, étape.
- Panneau latéral : déplacement dans le pipeline, score de correspondance avec
  compétences présentes/manquantes, lettre de motivation, historique complet
  (transition, date, auteur, motif), lien de téléchargement du CV.

**Les filtres sont envoyés au serveur.** Filtrer côté navigateur ne tiendrait
pas au-delà de la première page et donnerait un compteur faux.

## 3. Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| PHPUnit — module recrutement complet | **60 tests, 210 assertions, 0 échec** |
| dont `AtsControllerTest` (nouveau) | 15 tests, 51 assertions |
| Jest — parcours candidat | 23 tests, 0 échec |
| `tsc --noEmit` (front/web) | 0 erreur |
| ESLint sur la nouvelle page | 0 erreur |
| Pint (style PHP) | 71 fichiers, PASS |
| `php -l` sur tous les fichiers créés | 0 erreur de syntaxe |
| `php artisan route:list --path=recruitment` | toutes les routes ATS enregistrées |

### Ce que je n'ai pas pu vérifier

- **Aucun rendu visuel de l'écran recruteur.** Il n'y a pas de base de données
  peuplée ni de session authentifiée dans cet environnement : la page compile,
  typecheck et lint, mais je ne l'ai pas vue s'afficher avec de vraies données.
- **La migration n'a pas été jouée sur une base tenant réelle.** Les tests
  utilisent la fixture `CreatesMvpSchema`, à laquelle j'ai ajouté les deux
  tables en miroir. La migration elle-même est syntaxiquement valide et
  idempotente (`schemaTableExists`), mais son exécution réelle reste à faire en
  recette.
- **Le téléchargement de CV** est couvert par le code mais pas par un test
  bout-en-bout avec un fichier réel sur le disque `local`.

## 3 bis. Second lot — parcours complets et extraction du CV (PR #9461)

Le premier lot laissait trois trous dans le parcours « de A à Z ». Ce second
lot les ferme, et corrige la limite n° 2 de la section suivante.

### 3 bis.1 Extraction du texte des CV (objectif 4)

**Vérifié avant d'écrire : aucune extraction n'existait.** Recherche plein
texte de `pdftotext`, `PdfParser`, `extractText`, `resume_text`, `cv_text` sur
`api/app`, `api/config`, `api/database` → **0 résultat**. `composer.json` ne
contient aucune bibliothèque de parsing PDF. La colonne `resume_text`
n'existait pas.

| Élément | Emplacement |
|---|---|
| Contrat | `Domain/Contracts/ResumeTextExtraction.php` |
| Implémentation | `Infrastructure/Extraction/ShellResumeTextExtractor.php` |
| Migration | `2026_11_14_000001_9461_add_resume_text_to_applicants.php` |
| Branchement | `CandidateApplicationController` (après stockage du fichier) |
| Score ATS | `AtsApplicantFilter::score()` |
| Recherche pipeline | `AtsApplicantFilter::apply()` |

Trois formats, trois stratégies : **PDF** via `pdftotext -layout` (la disposition
en colonnes est conservée — sans elle, un CV sur deux colonnes ressort en mots
entremêlés), **DOCX** par lecture directe de `word/document.xml` dans l'archive
ZIP (`ZipArchive` + `DOMDocument`, aucune dépendance), **DOC** via `antiword`
avec repli `catdoc`.

Trois décisions qui comptent :

1. **`proc_open` avec des arguments en TABLEAU**, jamais une chaîne concaténée.
   Le nom d'origine du CV vient d'un visiteur non authentifié ; c'est la seule
   injection réelle de ce chemin.
2. **Aucune exception, `null` partout.** Un CV scanné (image dans un PDF) ou
   chiffré ne rend aucun texte : c'est un cas normal, pas une erreur. Faire
   échouer la candidature d'un candidat légitime pour cela serait un défaut.
3. **`LIBXML_NONET | LIBXML_NOENT`** au parsing du DOCX : un DOCX piégé ne doit
   pas pouvoir déclencher la lecture d'un fichier local au serveur.

Le score distingue désormais `resume_text = null` (« non extrait ») de vide, et
expose `sources` (`resume`, `cover_letter`, `notes`) pour que le recruteur sache
sur quoi le score a été calculé — un score de 40 % sans CV lisible n'a pas la
même valeur qu'un score de 40 % sur un CV complet.

**Correction de bord : la correspondance se fait sur frontières de mot.** La
version précédente utilisait `str_contains` : « Go » correspondait à « Google »,
« R » à « Recrutement », « C » à n'importe quel mot. Avec une compétence d'un
caractère, tous les candidats obtenaient 100 %. Le test
`test_a_skill_is_matched_on_word_boundaries_only` verrouille ce cas.

### 3 bis.2 Suivi de candidature par le candidat (objectif 2)

Le parcours candidat s'arrêtait au dépôt : aucun moyen de savoir ce que le
dossier était devenu.

| Élément | Emplacement |
|---|---|
| Route | `POST /api/v1/public/careers/{slug}/applications/track` |
| Contrôleur | `CandidateApplicationTrackingController` |
| Throttling | `public-careers-track` (5/min par IP **et** par email) |
| Client | `src/lib/candidate-tracking-api.ts` |
| Page | `/[companySlug]/careers/applications` |

Une route qui rend des dossiers à partir d'un email est par nature une surface
d'énumération. Trois mesures cumulatives :

- **Throttling à double clé** (IP + email) : 5/min par IP bloque le balayage,
  la clé email empêche un attaquant réparti sur plusieurs IP de marteler une
  même victime.
- **Réponse minimale** : intitulé du poste, statut, dates. Jamais la note
  interne, les notes du recruteur, le score ATS, le texte du CV ni l'identité
  des recruteurs. Un test vérifie explicitement l'absence de chacune de ces
  clés.
- **Email inconnu → liste vide, jamais 404.** Un 404 confirmerait « cette
  adresse n'a jamais postulé », ce qui est déjà une information.

`POST` et non `GET` : l'email est une donnée personnelle, un `GET` la ferait
atterrir dans les journaux d'accès, l'historique du navigateur et les en-têtes
`Referer`.

Le tenant est résolu depuis `{companySlug}` : un candidat ne voit que les
dossiers déposés chez cette entreprise, jamais ceux déposés ailleurs avec la
même adresse (test dédié).

### 3 bis.3 Gestion des offres et décision finale (objectif 1)

**Constat :** le backend exposait le CRUD complet (`/recruitment/jobs`,
`/publish`, `/close`, `/archive`, `/reopen`) mais **aucun écran ne l'appelait**.
Un tenant ne pouvait publier une offre qu'en appelant l'API à la main — le
parcours « de A à Z » s'arrêtait à la première étape.

| Élément | Emplacement |
|---|---|
| Panneau offres | `(dashboard)/recruitment/JobManagementPanel.tsx` |
| Décision finale | `(dashboard)/recruitment/page.tsx` |
| Routes utilisées | `POST /recruitment/jobs`, `.../{id}/publish|close|archive|reopen` |

Le panneau crée une offre (titre, lieu, contrat, modalité, **compétences**,
description), affiche son statut avec le cycle de vie explicite, et propose les
transitions valides selon l'état courant. Les compétences saisies sont la seule
source du score ATS : sans elles, le score reste `null` et le tri par
correspondance ne sert à rien — le formulaire le dit à l'écran.

La décision finale (embaucher / refuser) exige un **motif non vide**. C'est un
acte qui doit rester justifié dans le journal append-only : un refus sans motif
écrit est indéfendable devant le candidat comme devant un contrôle.

### 3 bis.4 Éditeur de CV depuis la vitrine (objectif 3)

| Élément | Emplacement |
|---|---|
| Composant | `/[companySlug]/careers/cv/ResumeBuilder.tsx` |
| Page | `/[companySlug]/careers/cv/page.tsx` |
| Libellés | `src/modules/vitrine/data/candidate-tools.ts` (4 locales) |
| Stockage | `localStorage`, clé `leopardo.candidate.cv.v1` |

Sections nom / titre / contact / profil / expériences / formations /
compétences, aperçu mis en forme en direct, et export PDF par l'impression du
navigateur (`@media print`) plutôt qu'une bibliothèque de génération : aucune
dépendance ajoutée, rendu identique à l'aperçu, et le candidat choisit
« Enregistrer au format PDF ».

**Choix assumé, et dit à l'écran :** un candidat sans compte ne peut pas avoir
de CV stocké côté serveur sans qu'on lui impose une inscription — exactement la
friction qu'on veut éviter. Le CV reste donc dans le navigateur et n'est envoyé
à aucune entreprise tant que le candidat ne joint pas le fichier. Conséquence
réelle : il ne suit pas d'un appareil à l'autre. L'écran le dit, il ne le
cache pas.

Un contenu `localStorage` corrompu fait repartir d'un CV vide au lieu de faire
planter la page — test dédié.

### 3 bis.5 Vérifications de ce second lot

| Vérification | Résultat |
|---|---|
| `phpunit --filter=Recruitment` | **101 tests, 296 assertions, 0 échec** |
| `phpunit` — extracteur (vrais binaires + ZipArchive) | 7 tests PASS |
| `phpunit` — score ATS sur CV | 10 tests PASS |
| `phpunit` — suivi candidat | 9 tests PASS |
| `jest` — `careers` + `recruitment` + libellés | **66 tests, 6 suites, 0 échec** |
| `tsc --noEmit` | 0 erreur |
| `eslint --max-warnings 0` (fichiers touchés) | 0 erreur |
| `pint --test` (81 fichiers) | PASS |
| `route:list --path=public/careers` | route de suivi enregistrée |

**Une régression réelle rencontrée et corrigée pendant ce lot :** ajouter
`/recruitment` aux préfixes protégés a cassé quatre sources qui en dérivent
(matcher du proxy, gate `DASHBOARD_PREFIXES`, `sw.js`, test du sous-menu RH).
Les quatre sont alignées et la suite repasse au vert. Corriger sans comprendre
aurait laissé le service worker servir une page protégée depuis le cache.

**Ce qui n'a pas pu être vérifié :** la suite Jest complète (`npx jest`) a été
tuée par l'OOM du sandbox (2 Go) — ce n'est pas un échec de test, c'est le
runner qui meurt. J'ai donc exécuté le périmètre touché (66 tests) plutôt que
de prétendre à une suite complète. Le rendu visuel des trois nouveaux écrans
n'a pas été observé avec de vraies données. La migration n'a pas été jouée sur
une base tenant réelle. Les binaires `pdftotext` / `antiword` sont présents
dans cet environnement, mais leur présence sur l'image de production n'est pas
vérifiée ici — le code rend `null` proprement s'ils manquent, donc une absence
dégradation n'est pas une panne, mais le score retomberait sur la lettre.

## 4. Points d'attention pour la suite

1. **`resume_url` pointe vers une route authentifiée.** C'est volontaire (le
   fichier est sur le disque privé), mais un lien collé dans un email ne
   fonctionnera pas pour un destinataire non connecté. Si ce besoin apparaît,
   il faudra une URL signée à durée limitée.
2. ~~**Le score de correspondance ne lit que la lettre de motivation.**~~ **Corrigé
   au second lot (§3 bis.1)** : le texte du CV est extrait au dépôt et entre dans
   le corpus de scoring. Reste vrai : un CV **scanné en image** ne rend aucun
   texte (`resume_text = null`), et le score retombe alors sur la lettre et les
   notes. L'API expose `sources` pour que l'écran puisse le dire — mais aucune
   OCR n'est faite, volontairement (coût et faux positifs).
3. **`front/admin-dashboard` n'a pas été modifié.** L'écran recruteur vit dans
   `front/web` (le dashboard principal). Si l'admin-dashboard doit aussi
   exposer le pipeline, il faudra y dupliquer la vue — l'API est prête.