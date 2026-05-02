# Module Placard (Cabinet Personnel)

> Espace de stockage personnel pour chaque utilisateur de la plateforme Leopardo RH.

## Objectif

Offrir a chaque utilisateur (employe, manager, RH, principal) un **placard virtuel** dans lequel il peut :

- **Ranger** ses documents importants (CV, diplomes, contrats, documents d'entreprise, etc.)
- **Organiser** ses fichiers dans des dossiers imbriques (arborescence libre)
- **Partager** un document ou un dossier via email ou lien public (avec expiration optionnelle)
- **Telecharger** ses documents a tout moment depuis le web ou le mobile

## Architecture

```
api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── CabinetFolderController.php
│   │   │   ├── CabinetDocumentController.php
│   │   │   └── CabinetShareController.php
│   │   └── Requests/Api/V1/Cabinet/
│   │       ├── StoreFolderRequest.php
│   │       ├── UpdateFolderRequest.php
│   │       ├── StoreDocumentRequest.php
│   │       ├── UpdateDocumentRequest.php
│   │       ├── MoveDocumentRequest.php
│   │       └── ShareRequest.php
│   ├── Mail/
│   │   └── CabinetShareMail.php
│   ├── Models/
│   │   ├── CabinetFolder.php
│   │   ├── CabinetDocument.php
│   │   └── CabinetShare.php
│   └── Services/
│       └── CabinetService.php
├── database/migrations/tenant/
│   └── 2026_05_02_000001_create_cabinet_tables.php
├── lang/{fr,en,tr,ar}/
│   └── cabinet.php
├── resources/views/emails/
│   └── cabinet-share.blade.php
└── routes/modules/
    └── cabinet.php
```

## Base de donnees

### `cabinet_folders`

| Colonne       | Type         | Description                          |
|---------------|--------------|--------------------------------------|
| `id`          | bigint PK    | Identifiant unique                   |
| `company_id`  | bigint FK    | Tenant (scope global BelongsToCompany) |
| `employee_id` | bigint FK    | Proprietaire du dossier              |
| `parent_id`   | bigint FK?   | Dossier parent (null = racine)       |
| `name`        | varchar(255) | Nom du dossier                       |
| `color`       | varchar(30)  | Couleur (hex ou token CSS)           |
| `icon`        | varchar(50)  | Icone (nom Material/Lucide)          |

### `cabinet_documents`

| Colonne         | Type         | Description                      |
|-----------------|--------------|----------------------------------|
| `id`            | bigint PK    | Identifiant unique               |
| `company_id`    | bigint FK    | Tenant                           |
| `employee_id`   | bigint FK    | Proprietaire                     |
| `folder_id`     | bigint FK?   | Dossier parent (null = racine)   |
| `name`          | varchar(255) | Nom affiche                      |
| `original_name` | varchar(255) | Nom original du fichier uploade  |
| `mime_type`     | varchar(127) | Type MIME                        |
| `size`          | bigint       | Taille en octets                 |
| `disk`          | varchar(50)  | Disque Laravel (local, s3, etc.) |
| `path`          | varchar(500) | Chemin relatif sur le disque     |
| `notes`         | text         | Notes libres                     |

### `cabinet_shares`

| Colonne            | Type         | Description                                |
|--------------------|--------------|--------------------------------------------|
| `id`               | bigint PK    | Identifiant unique                         |
| `company_id`       | bigint FK    | Tenant                                     |
| `employee_id`      | bigint FK    | Employe qui a partage                      |
| `shareable_type`   | varchar      | Classe Eloquent (polymorphe)               |
| `shareable_id`     | bigint       | ID de l'objet partage                      |
| `share_token`      | varchar(64)  | Token unique pour acces public             |
| `shared_via`       | varchar(30)  | Canal : `email` ou `link`                  |
| `shared_with_email`| varchar(255) | Adresse email du destinataire              |
| `expires_at`       | timestamp    | Date d'expiration (null = pas d'expiration)|

## Endpoints API

Tous les endpoints authentifies sont sous `POST /api/v1/cabinet/...` avec les middlewares `auth:sanctum` et `tenant`.

### Dossiers

| Methode  | URI                             | Description              |
|----------|---------------------------------|--------------------------|
| `GET`    | `/cabinet/folders`              | Lister les dossiers      |
| `POST`   | `/cabinet/folders`              | Creer un dossier         |
| `GET`    | `/cabinet/folders/{id}`         | Detail d'un dossier      |
| `PUT`    | `/cabinet/folders/{id}`         | Modifier un dossier      |
| `DELETE` | `/cabinet/folders/{id}`         | Supprimer un dossier     |

### Documents

| Methode  | URI                                    | Description                |
|----------|----------------------------------------|----------------------------|
| `GET`    | `/cabinet/documents`                   | Lister les documents       |
| `POST`   | `/cabinet/documents`                   | Uploader un document       |
| `GET`    | `/cabinet/documents/{id}`              | Detail d'un document       |
| `PUT`    | `/cabinet/documents/{id}`              | Modifier un document       |
| `DELETE` | `/cabinet/documents/{id}`              | Supprimer un document      |
| `GET`    | `/cabinet/documents/{id}/download`     | Telecharger le fichier     |
| `PATCH`  | `/cabinet/documents/{id}/move`         | Deplacer dans un dossier   |

### Partages

| Methode  | URI                               | Description                |
|----------|-----------------------------------|----------------------------|
| `GET`    | `/cabinet/shares`                 | Lister mes partages        |
| `POST`   | `/cabinet/shares`                 | Creer un partage           |
| `DELETE` | `/cabinet/shares/{id}`            | Revoquer un partage        |
| `GET`    | `/cabinet/shared/{token}`         | Acces public via token     |

### Statistiques

| Methode | URI              | Description                            |
|---------|------------------|----------------------------------------|
| `GET`   | `/cabinet/stats` | Nombre de docs, dossiers, espace total |

## Securite

- **Isolation tenant** : le trait `BelongsToCompany` et le middleware `tenant` garantissent l'isolation multi-tenant.
- **Propriete** : chaque endpoint verifie que `employee_id === actor->id` ; aucun utilisateur ne peut acceder au placard d'un autre.
- **Partage** : le lien public utilise un token aleatoire de 64 caracteres. Le partage peut avoir une date d'expiration.
- **Upload** : taille max 20 Mo par fichier (configurable via `StoreDocumentRequest`).
- **Stockage** : les fichiers sont stockes dans le disque Laravel `local` par defaut. Le chemin inclut `company_id/employee_id` pour eviter les collisions.

## i18n

Traductions disponibles dans `lang/{fr,en,tr,ar}/cabinet.php` pour tous les libelles, messages et emails.
