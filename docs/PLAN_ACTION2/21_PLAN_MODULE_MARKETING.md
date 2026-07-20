# Plan d'action : Module Marketing (Marketer)

> **Statut :** Planifié (En attente de validation du P0 - Premier client payant)
> **Priorité :** Post-Lancement

## 1. Objectifs du Module
Renforcer la plateforme en permettant à l'équipe marketing de s'intégrer nativement à l'écosystème Leopardo RH.
- Gérer la communication externe de l'entreprise.
- Publier du contenu sur les réseaux sociaux (LinkedIn, Facebook Pages, Groupes Facebook, Twitter/X).
- Planifier les publications.

## 2. Architecture des Rôles
- **Nouveau rôle :** `marketer`
- Sera ajouté à `api/app/Core/Auth/Enums/Role.php`
- Sera ajouté dans `api/app/Core/Auth/Domain/Models/Employee.php` (`isMarketer()`)

## 3. Base de données (Backend)
Création d'un module isolé `api/app/Modules/Marketing/`.
- `social_accounts` : Stockage sécurisé des tokens OAuth (tenant-scoped).
- `social_posts` : Contenu (texte, médias), statut (brouillon, planifié, publié, erreur), date de publication prévue.
- `social_platforms` : Choix des plateformes cibles (pivot).

## 4. Connectivité Réseaux Sociaux
Pour des raisons de complexité de maintenance, l'utilisation d'un agrégateur d'API (comme **Ayrshare** ou **Buffer API**) est recommandée pour éviter de devoir maintenir individuellement les changements constants des APIs Meta, LinkedIn et X.

## 5. Plateformes Clientes

### 5.1 Application Web (Client Web)
Ajout d'une section `src/app/(dashboard)/marketing/`
- Calendrier interactif des publications.
- Éditeur de contenu multi-réseaux.
- Gestion des connexions OAuth.

### 5.2 Application Mobile
Afin de ne pas alourdir la maintenance de 4 applications distinctes sur les stores, l'approche retenue est :
- Intégrer la **"Marketing Zone"** comme une fonctionnalité débloquée par rôle dans l'application existante `leopardo_employee` (ou `leopardo_manager`).
- Si l'utilisateur a le rôle `marketer`, un nouvel onglet apparaît.
- Cas d'usage terrain : Prendre une photo lors d'un événement d'entreprise et la publier/planifier immédiatement depuis le téléphone.

## 6. Exécution des Tâches (Cron)
- Job `PublishScheduledSocialPost` tournant chaque minute.
- Vérifie `social_posts` (status = scheduled) et interagit avec l'API sociale.
- Gère les retours (success/failed) et les alertes Sentry.

---
**Critère de déclenchement :** L'implémentation technique de ce plan d'action ne commencera qu'après la validation du jalon P0 (Acquisition et facturation du premier client réel via Stripe).
