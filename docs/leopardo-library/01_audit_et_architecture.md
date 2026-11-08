# Leopardo Library — Audit de l'existant & architecture

> Bibliothèque numérique publique et professionnelle (BC-40 LIBRARY).
> Branche de travail : `feat/vitrine-leopardo-library`.

## 1. Audit de l'existant (réutilisation maximale)

| Domaine | Existant identifié | Réutilisation pour la Library |
|---|---|---|
| Gestion documentaire | Aucun module documentaire produit. `BIBLIOTHEQUE_ERREURS.md` = doc interne ; `media_library_indexes` = Marketing OS (interne) | **Rien à réutiliser** — module neuf |
| Stockage de fichiers | Stockage objet S3 (Supabase) + URLs signées, utilisé par d'autres modules | `file_key` + URL temporaire signée (jamais d'URL publique permanente) |
| Catalogue / marketplace | **BC-28 Catalog** (`api/app/Modules/Catalog`) : catégories + produits, publication draft/published, DTO public strict, cache TTL, routes publiques isolées | **Patron d'architecture** repris à l'identique (structure DDD, DTO public, cache, routes isolées) |
| Authentification | Sanctum + `Employee` (RBAC `hasManagerRole`) | Surface tenant authentifiée ; surface publique sans auth |
| Paiement | **BC-38 Paiements** (wallets, ledger, providers) | Modèle d'accès `purchase`/`subscription` **prévu** mais non branché (pas de paiement fictif) |
| Recherche | Recherche SQL simple ; SearchIntelligence (AEO, interne) | Recherche déclarative (titre/auteur/mots-clés) ; sémantique = évolution |
| Espace public (vitrine) | **BC-27 Showcase** (`front/web/src/modules/vitrine`) : footer, i18n fr/en/tr/ar, RTL, sitemap | Lien footer « Bibliothèque » + sitemap |
| Composants UI | `front/web/src/components/ui`, Tailwind, lucide-react | Réutilisés (cartes, badges, boutons) |
| Droits d'accès | `BelongsToCompany` (scope tenant), Policies | Isolation tenant stricte + `LibraryAccessPolicy` |
| Lecture / téléchargement | Aucun lecteur existant | Lecteur numérique neuf (navigation, zoom, recherche, reprise) |

**Conclusion** : aucun module « bibliothèque » n'existait. Le module **Catalog** fournit le patron
d'architecture à réutiliser ; le module **Showcase** fournit l'intégration vitrine. Aucune
architecture parallèle n'est créée : la Library suit les mêmes conventions (DDD, DTO public strict,
routes isolées, cache TTL, isolation tenant).

## 2. Architecture proposée

### 2.1 Principes
- **Isolation tenant stricte** : le catalogue public Leopardo (`company_id` NULL) est **distinct**
  des contenus privés d'un tenant (`company_id` non-NULL). Une ressource `private` n'est jamais
  servie hors de son tenant.
- **Vérification d'accès côté serveur** : `LibraryAccessPolicy` décide lecture/téléchargement ;
  l'interface ne fait qu'afficher.
- **Aucun paiement fictif** : les modèles `purchase`/`subscription` sont modélisés et vérifiés,
  mais non branchés tant que l'infrastructure n'est pas prête.
- **Droits d'auteur** : licence, détenteur, restrictions de téléchargement/géo, signalement/retrait.
  Seules des œuvres du **domaine public** ou explicitement libres sont référencées.

### 2.2 Modèle de données (migration tenant)
- `library_categories` — catégories (slug unique par tenant)
- `library_authors` — auteurs / éditeurs
- `library_collections` — collections éditoriales
- `library_resources` — livres/documents (métadonnées, `access_model`, `status`, licence, droits,
  `file_key`, prix minor units + devise ISO)
- `library_reports` — signalements (procédure de retrait)

### 2.3 API & routes
**Publiques** (`throttle:shop-public`, sans auth) :
- `GET /api/v1/public/library` — catalogue public (filtres catégorie/langue/accès/q)
- `GET /api/v1/public/library/{slug}` — fiche publique
- `GET /api/v1/public/library/{slug}/download` — URL temporaire signée (ou 403)
- `POST /api/v1/public/library/{slug}/reports` — signalement

**Tenant** (`auth:sanctum` + `tenant`) :
- `GET|POST /api/v1/library/resources`, `GET|PUT|DELETE /api/v1/library/resources/{id}`
- `POST /api/v1/library/resources/{id}/publish|unpublish|archive`

### 2.4 Frontend public
- `/library` — accueil (recherche, populaires, nouveautés, gratuits, catégories, sélection)
- `/library/[slug]` — fiche (métadonnées, licence, aperçu, lecture, téléchargement)
- `/library/account` — espace utilisateur (favoris, lectures récentes, progression)
- `/library/[slug]/download` — route serveur (garde 403)

### 2.5 Justification des nouveautés
- **Nouvelles tables** : aucune table existante ne couvre livres/documents/licence/accès.
- **Nouvelles routes** : surface publique isolée (comme Catalog) + surface tenant.
- **Nouvelles dépendances** : **aucune** (Tailwind, lucide-react, Next.js déjà présents).
- **Nouveau BC (BC-40)** : le module est autonome, avec sa propre responsabilité et son owner.

## 3. Canaux de publication
- **Vitrine** : lien footer « Bibliothèque » (4 locales) + sitemap.
- **Vente en gros** : les livres non gratuits sont modélisés (`purchase`) pour être exposés
  ultérieurement sur la plateforme de vente en gros (BC-17 marketplace) — non branché ici.
- **Publication par le public** : prévue (workflow éditorial + signalement) ; l'ouverture de la
  publication externe nécessite une modération (hors premier périmètre).
- **Superadmin** : surface plateforme (catalogue public `company_id` NULL) — à brancher.
- **Tenant** : surface `/api/v1/library/resources` (CRUD + workflow).

## 4. Risques & limitations
- Stockage objet non branché : la route download renvoie 501 (pas de faux fichier).
- Paiement/abonnement non branchés (BC-38) : modèles vérifiés mais non facturés.
- Publication externe (public) non ouverte : nécessite modération.
- Lecteur : texte/PDF simple ; EPUB et rendu PDF avancé = évolution.
- Tests backend non exécutés localement (PHP/PostgreSQL absents du sandbox).

## 5. Plan d'évolution
1. Brancher le stockage objet (URLs signées réelles) + upload admin.
2. Brancher BC-38 (achat à l'unité, abonnement) + BC-17 (vente en gros).
3. Lecteur PDF/EPUB (pdf.js / epub.js) + recherche plein document.
4. Recherche sémantique + recommandations (SearchIntelligence).
5. Publication externe modérée + back-office superadmin.
6. Synchronisation serveur de la progression/favoris pour les utilisateurs connectés.
