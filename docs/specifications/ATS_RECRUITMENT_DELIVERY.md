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

## 4. Points d'attention pour la suite

1. **`resume_url` pointe vers une route authentifiée.** C'est volontaire (le
   fichier est sur le disque privé), mais un lien collé dans un email ne
   fonctionnera pas pour un destinataire non connecté. Si ce besoin apparaît,
   il faudra une URL signée à durée limitée.
2. **Le score de correspondance ne lit que la lettre de motivation.** Il ne
   parse pas le CV (pas d'extraction de texte dans le pipeline actuel). Un
   candidat qui ne rédige pas de lettre obtient donc 0 % même avec un CV
   parfaitement aligné — c'est une limite assumée, pas un bug, mais elle doit
   être expliquée aux recruteurs.
3. **`front/admin-dashboard` n'a pas été modifié.** L'écran recruteur vit dans
   `front/web` (le dashboard principal). Si l'admin-dashboard doit aussi
   exposer le pipeline, il faudra y dupliquer la vue — l'API est prête.
