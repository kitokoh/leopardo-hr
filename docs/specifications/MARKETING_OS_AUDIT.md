# Marketing OS — Audit obligatoire du dépôt (ÉTAPE 1)

> **Périmètre** : module « Marketing OS & Content Studio » dans le Super Admin de
> Leopardo Business OS.
> **Méthode** : audit du dépôt `kitokoh/leopardo-hr` (branche `main`, commit
> `6d20d4f56`) — lecture du code réel, pas des intentions documentaires.
> **Règle appliquée** : aucun service existant n'est dupliqué. Chaque brique est
> classée en **existante et réutilisable**, **à améliorer**, **à réparer** ou
> **réellement absente**.

---

## 1. Synthèse exécutive

Le dépôt **contient déjà un socle marketing réel et testé**, mais il s'arrête à la
**publication sur réseaux sociaux**. Tout ce qui fait la chaîne
« Idée → Stratégie → Brief → Production → Validation → Publication → Mesure → Réutilisation »
en amont et en aval **n'existe pas**.

| Bloc fonctionnel du brief | État réel | Verdict |
|---|---|---|
| Comptes sociaux + publication multi-plateformes | `Modules/Marketing` — Ayrshare, publishers, cron | **Réutilisable** |
| État de publication par plateforme | table `post_publications` + `PostPublication` | **Réutilisable** |
| Génération de contenu par IA | `MarketingAiService` (posts, réponses, bilan hebdo) | **Existante, à étendre** |
| Bibliothèque de médias | `Modules/Showcase` (`showcase_media`) — mais liée à la vitrine | **Réutilisable, à étendre** |
| Visibilité SEO / SEA / AEO / GEO | `Modules/Geo` = **stub vide** (distance/PostGIS seulement) | **Réellement absente** |
| Stratégie & calendrier éditorial (idées, briefs, campagnes) | aucune table, aucun modèle | **Réellement absente** |
| Studio de préparation (script, storyboard, prompts, versions) | aucune fiche de production | **Réellement absente** |
| Publication assistée (hors API) | non implémentée (les posts supposent l'agrégateur) | **Réellement absente** |
| Espace Marketing OS dans le Super Admin | 1 écran `MarketingOAuthView.vue` (OAuth uniquement) | **À construire** |

**Conclusion** : le chantier est une **extension**, pas une réécriture. Il faut
brancher un modèle « Idée / Campagne / Brief / Ressource / Publication » sur les
services existants, et non recréer la publication sociale.

---

## 2. Classe A — Existant et réutilisable (ne rien dupliquer)

### A.1 Module `api/app/Modules/Marketing/` (DDD complet)

- **Modèles** : `SocialAccount`, `SocialPost`, `PostPublication`, `MarketingLead`.
- **Statuts `SocialPost`** : `draft`, `scheduled`, `publishing`, `published`, `failed`.
- **Actions** : `ConnectSocialAccount`, `DisconnectSocialAccount`, `CreateSocialPost`
  (toujours en brouillon — jamais de publication automatique), `SchedulePost`,
  `CaptureMarketingLead`.
- **Publication** : `SocialPublishingService` (orchestration), `AyrshareClient`
  (REST direct, pas de SDK), `SocialPublisherResolver` + publishers
  `LinkedInPublisher`, `MetaPublisher`, `TwitterPublisher` derrière
  `SocialPublisherInterface`.
- **Détail par plateforme** : `PostPublication` (`pending|success|failed`,
  `external_post_id`, `error_message`, `published_at`) — un post peut réussir sur
  LinkedIn et échouer sur X sans perdre l'historique.
- **Planification** : `PublishScheduledSocialPosts` (`everyMinute`,
  `withoutOverlapping`, `onOneServer`) + `PublishScheduledPostJob`.
- **Commentaires** : `SocialCommentController` + `getComments`/`postComment`
  (Ayrshare) + `suggestReply` assisté IA.
- **Leads** : `MarketingLead` + qualification → contact comptabilité.

→ **Réutilisable tel quel** pour les sections E, F, G du brief.

### A.2 Sécurité et cloisonnement déjà en place

- `BelongsToCompany` (scope tenant + auto-fill `company_id`) sur tous les modèles
  marketing ; **aucun token OAuth brut stocké** : uniquement
  `provider_profile_ref` **chiffré** (`encrypted`) et masqué (`$hidden`).
- Policies `SocialAccountPolicy` / `SocialPostPolicy`, middleware
  `api.manager:marketing,principal,module:marketing`.
- `Auditable` (`app/Shared/Traits/`) disponible pour la journalisation.

→ **Réutilisable** pour la section J du brief.

### A.3 Médiathèque — `api/app/Modules/Showcase/`

- Table `showcase_media` : `company_id`, `showcase_id`, `section_id`, `kind`,
  `original_name`, `file_name`, `mime_type`, `extension`, `size`, `disk`, `path`.
- `ShowcaseMediaService` : validation d'extension par type, plafond de poids,
  nom de stockage aléatoire, **sanitisation du nom d'origine**, refus du SVG actif.
- Stockage sur le disk `local` (privé, **hors webroot**), servi par une route
  publique contrôlée ; **le binaire n'est jamais en base**.

→ **Réutilisable comme socle de la médiathèque** (section C). Ses garde-fous
sécurité sont exactement ceux qu'exige la section J.

### A.4 Front existant

- `front/admin-dashboard` (Vue 3) : `navigation/navigation.js` = **source de
  vérité unique** de la navigation (permission + groupe + sous-menu `children`),
  router `src/router/index.js`, client `services/api.js`, i18n fr/en/ar/tr.
- `front/web` (Next.js) : module marketing réel (`PostEditor.tsx`,
  `PostInteractions.tsx`, `WeeklyReportCard.tsx`, `MediaUrlsInput.tsx`,
  `types.ts`) + page `(dashboard)/(marketing)/social` (calendrier mensuel) et
  `/social-marketing`.

→ **Réutilisable comme modèle d'UI** ; le nouvel espace doit suivre ces conventions.

---

## 3. Classe B — Existant à améliorer

| Brique | État actuel | Amélioration requise |
|---|---|---|
| `MarketingAiService` | 3 usages : `suggestPost`, `suggestReply`, `weeklyReport` | Étendre aux **briefs** (script, storyboard, prompts vidéo/image) et au **résumé de campagne**. Garder la règle « humain dans la boucle », **jamais de publication automatique**. |
| `showcase_media` | Concentré sur la vitrine (`showcase_id` obligatoire) | Extraire une **médiathèque marketing** générique (campagne, objectif, produit, plateformes, langue, tags, version, statut de validation, droits/expiration). Ne pas détruire l'existant : nouvelles colonnes + rattachement optionnel. |
| `SocialPost` | Contenu + plateformes + dates, sans notion d'idée/campagne | Relier un post à une **idée/campagne** et à une **ressource** de la médiathèque sans casser les routes existantes. |
| Calendrier | Vue mensuelle côté `front/web` seulement | Fournir les mêmes vues (calendrier / tableau / campagne) dans le **Super Admin**. |
| Aperçus médias | Aucun redimensionnement (pas de GD/Imagick dans `composer.json`) | Génération de **miniatures** à évaluer séparément (dépendance absente — cf. risques). |
| Bilan hebdo | Agrégats 7 jours glissants | Ajouter les **métriques par publication** lorsque la plateforme les fournit, et marquer explicitement le reste comme estimation. |

---

## 4. Classe C — Existant à réparer / risques identifiés

1. **`Modules/Geo` est un leurre de nommage.** Il ne traite **que** la
   géolocalisation (Haversine/PostGIS, `NearestSearch`, `DistanceCalculator`).
   Le brief parle de **GEO = Generative Engine Optimization**. Risque de
   confusion majeur → **ne pas** réutiliser `Modules/Geo` pour la visibilité IA.
2. **Deux migrations `social_posts` / `showcase_media` = schéma figé.** La garde
   « une table, une migration » (#7452) interdit un second `Schema::create` :
   toute colonne additionnelle passe par une migration `Schema::table`
   **idempotente** (`schemaHasColumn`).
3. **`down()` destructeur** : la garde #8207 exige qu'un `down()` ne détruise que
   ce que le `up()` du **même fichier** a créé.
4. **Aucune publication assistée** : le modèle actuel suppose un agrégateur
   configuré. En l'absence de clé Ayrshare, `SocialPublishingService` lève
   `SocialAccountNotFoundException` — il n'existe **aucun** parcours
   « préparer / copier / ouvrir / confirmer manuellement ». C'est un manque
   fonctionnel, pas un bug.
5. **`api.manager:marketing,principal,module:marketing`** restreint les routes
   marketing au **rôle manager**, alors que le brief demande un espace
   **Super Admin**. Les routes plateforme vivent sous
   `auth:super_admin_api` + `platform.permission:*`. → Deux surfaces distinctes,
   à ne pas mélanger.
6. **Isolation inter-modules (#5584)** : tout `use App\Modules\X` depuis
   `Modules\Y` (ou depuis `app/Http`) fait **échouer la CI**. Le nouveau code
   doit rester **dans** `Modules/Marketing` ou passer par des contrats
   partagés.
7. **Pas de GD/Imagick** dans `api/composer.json` → pas de génération de
   vignettes sans nouvelle dépendance (vérifier le budget).

---

## 5. Classe D — Réellement absent (à construire)

| Manque | Détail | Section du brief |
|---|---|---|
| **Stratégie / idées / campagnes** | Aucune table `content_ideas` / `marketing_campaigns` : objectif, cible, secteur/verticale, produit, message, canal, format, langue, dates, responsable, budget, statut | B, I |
| **Transformation idée → tâche de production** | Aucune action liée ; `Planning/Task` existe mais sans pont marketing | B |
| **Studio / fiche de production** | Concept, script, storyboard, scènes, narration, montage, prompt vidéo, prompts image, ressources, format/durée, versions linguistiques | D |
| **Versionner une ressource** | Aucune notion de version ni de statut de validation sur un média | C, D |
| **Publication assistée** | Préparer texte + hashtags, copier la description, ouvrir le média, ouvrir la page officielle, marquer « publié » **après confirmation**, enregistrer URL + date réelles | F |
| **Publication ↔ ressource** | Aucun lien : impossible de répondre « cette vidéo a-t-elle déjà été publiée, où, quand, avec quel résultat ? » | G |
| **Statuts de publication par plateforme côté Super Admin** | Les statuts existent (`SocialPost`, `PostPublication`) mais aucune vue d'ensemble idée → validation → planifié → publié → échec | E |
| **Moteur de visibilité SEO/SEA/AEO/GEO** | Aucune table, aucun service. Existant réel : `robots.ts`, `JsonLd.tsx`, `ai-search.ts`, sitemap Next.js (vitrine) — **aucune donnée exploitable côté admin** | H |
| **Tableau de bord marketing** | Aucun agrégat « à imaginer / à produire / à valider / à publier / en retard » | A |
| **Rôles fines (stratégie / création / validation / publication)** | Le registre `PlatformPermission` n'a **aucune** permission marketing ; seul existe `ShowcaseManage` | J |
| **Recherche médiathèque** (filtres date, format, campagne, langue, plateforme, statut, produit, mots-clés) | Aucune. Le `GET /showcase/media` ne filtre pas sur ces axes | C |

---

## 6. Décisions d'architecture retenues (issues de l'audit)

1. **Étendre `Modules/Marketing`**, pas de nouveau module : le domaine est déjà
   le bon, et un module « MarketingOS » séparé créerait un import croisé
   interdit par #5584.
2. **Réutiliser `ShowcaseMediaService`** pour le stockage (disk privé `local`,
   noms aléatoires, garde-fous SVG/poids) : la nouvelle table de médiathèque
   référence une ressource, elle ne réimplémente pas l'upload.
3. **Ne pas toucher au schéma de `social_posts` / `showcase_media`** : nouvelles
   tables + migrations `Schema::table` idempotentes uniquement.
4. **Deux surfaces d'authentification distinctes** :
   - routes **tenant** existantes (`auth:sanctum` + `api.manager:...`) → inchangées ;
   - nouvelles routes **Super Admin** (`auth:super_admin_api` +
     `platform.permission:marketing.*`) → le nouvel espace.
5. **Aucune automatisation mensongère** : le mode direct n'est activé que si un
   agrégateur est configuré ; sinon l'API expose explicitement
   `mode: assisted` et n'écrit jamais `published` sans confirmation humaine.
6. **Coût** : l'existant est sur **Ayrshare** (agrégateur tiers payant) ; le
   stockage est **local privé** (pas de S3 facturé) ; Postgres = Neon (palier
   gratuit), hosting Render/Vercel (gratuit). Aucune nouvelle dépendance
   payante n'est introduite par ce lot.

---

## 7. Portée retenue pour la première livraison (slice fonctionnel)

Livré maintenant, réellement branché sur la base :

- Migration + modèles + API **Campagne / Idée de contenu** (section B).
- Migration + modèles + API **Ressource de médiathèque** avec métadonnées et
  recherche/filtres (section C).
- **Lien ressource ↔ publication** (section G — « déjà publiée ? où ? quand ? »).
- Endpoint d'**agrégation tableau de bord** (section A).
- Espace **Marketing OS** dans le Super Admin : Tableau de bord, Stratégie &
  calendrier, Médiathèque.

Documentés comme **feuille de route par phases** (non codés dans ce lot) :
studio de production complet, publication assistée pas-à-pas, connecteurs
directs supplémentaires, moteur AEO/GEO, mesure d'efficacité avancée.

---

## 8. Points nécessitant une intégration ou une autorisation externe

- **Clé API Ayrshare** (`AYRSHARE_API_KEY`) : sans elle, seule la publication
  assistée est possible. Les comptes réseaux sociaux doivent être rattachés au
  profil Ayrshare du tenant (parcours OAuth externe).
- **API de mesure** : YouTube/Instagram/LinkedIn exposent des statistiques
  d'engagement ; leur récupération dépend des quotas et autorisations de chaque
  plateforme. Tant qu'aucune donnée n'est remontée, l'UI affiche
  explicitement « non disponible », jamais une estimation présentée comme un fait.
- **Génération de miniatures** : dépendance GD/Imagick absente du projet.

---

## 9. Risques et parades

| Risque | Parade |
|---|---|
| Import croisé inter-modules → CI rouge | Tout le code reste sous `Modules/Marketing` ; contrats partagés si besoin |
| Collision de migration | `bash dev-hub/tools/check-migration-basename-collisions.sh` avant push |
| Table redéclarée | `python3 dev-hub/tools/check-duplicate-schema-create.py --strict` |
| `down()` destructeur | Le `down()` ne droppe que ce que le `up()` du même fichier crée |
| Fuite cross-tenant | `company_id` non nullable + `BelongsToCompany` + test d'isolation |
| Publication annoncée à tort | Statut `published` écrit **uniquement** après succès API **ou** confirmation utilisateur explicite |
