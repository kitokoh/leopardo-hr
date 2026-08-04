# Spécifications : Module Marketing (leopardo_marketing)

Dernière mise à jour : 2026-08-02

## 1. Contexte & Objectifs
Le module Marketing permet aux équipes de gérer leur présence sur les réseaux sociaux directement depuis l'écosystème Leopardo RH.
L'objectif principal est de planifier, valider et publier du contenu (texte, images) en un clic sur de multiples plateformes (LinkedIn, Facebook, X/Twitter).

## 2. Rôles et Permissions
- **Marketeur (marketing_editor)** : Peut créer des brouillons, planifier des posts et voir les statistiques.
- **Manager/Approbateur (marketing_manager)** : Peut valider ou rejeter un post avant sa publication finale.

## 3. Architecture Backend (Laravel)

### 3.1. Modèles & Base de données
- `social_accounts` : Stocke les tokens OAuth pour chaque plateforme (LinkedIn, Meta, X). Colonnes clés : `company_id`, `provider`, `provider_account_id`, `access_token`, `refresh_token`, `expires_at`.
- `marketing_posts` : Stocke le contenu à publier. Colonnes clés : `company_id`, `author_id`, `content`, `media_urls` (JSON), `scheduled_at`, `status` (draft, pending_approval, scheduled, published, failed).
- `post_publications` : Stocke l'état de publication par réseau. `post_id`, `social_account_id`, `external_post_id`, `status`.

### 3.2. Jobs et Services
- **Services de Publication** : `LinkedInPublisher`, `MetaPublisher`, `TwitterPublisher` implémentant une interface commune `SocialPublisherInterface`.
- **CRON Job** : `PublishScheduledPostJob` qui tourne chaque minute via l'ordonnanceur Laravel (`Kernel.php`). Il récupère les posts avec `status = scheduled` et `scheduled_at <= now()`, puis délègue la publication aux services associés.

### 3.3. Endpoints API (`/api/v1/marketing/`)
- `GET /posts` (Liste avec filtres: statut, date)
- `POST /posts` (Création)
- `PATCH /posts/{id}` (Mise à jour)
- `POST /posts/{id}/approve` (Validation)
- `GET /social-accounts` (Comptes liés)

## 4. Application Mobile (Flutter)

L'application `leopardo_marketing` doit être développée de façon modulaire en s'appuyant sur `leopardo_core`.

### 4.1. Écrans Principaux
1. **Écran de Création de Post** :
   - Champ texte multi-lignes.
   - Upload d'images (limité à 4 médias).
   - Sélecteur de réseaux sociaux (Switch ou Checkboxes).
   - DatePicker & TimePicker pour la planification.
2. **Calendrier Éditorial** :
   - Vue mois/semaine (via le widget `table_calendar`).
   - Pastilles de couleur pour le statut (Jaune: En attente, Bleu: Planifié, Vert: Publié).
3. **Dashboard Analytique** :
   - Cartes (Cards) pour les KPIs : Impressions, Likes, Clics.

### 4.2. Contraintes Techniques Mobile
- Respect du thème défini par `TenantTheme` (depuis `leopardo_core`).
- Utilisation stricte de `extractDataList()` et `extractDataMap()` pour parser les réponses API paginées.
- Pagination infinie (Infinite Scroll) pour le calendrier éditorial.

## 5. Workflow de validation (Design-First)
Conformément à la règle "Absolument TOUTES les pages des applications web et mobiles doivent faire l'objet d'une maquette Stitch avant codage" :
- Des wireframes pour `leopardo_marketing` devront être générés et intégrés à Stitch avant de débuter l'implémentation Flutter.
