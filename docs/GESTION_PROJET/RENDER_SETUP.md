# Guide de Déploiement : Neon + Render (100% Gratuit)

Ce guide vous explique comment mettre en ligne votre API sans carte bancaire.

## 1. Préparer la Base de Données (Neon)
Vous avez déjà créé votre projet. Voici ce dont nous avons besoin :
1. Allez sur votre dashboard [Neon.tech](https://neon.tech).
2. Dans la section **"Connection Details"**, assurez-vous que le mode est sur **"Parameters"** ou **"Connection string"**.
3. Copiez l'URL qui ressemble à ceci : `postgresql://neondb_owner:PASSWORD@ep-odd-morning-abt600ow.eu-west-2.aws.neon.tech/neondb?sslmode=require`

## 2. Déployer sur Render
1. Connectez-vous sur [Render.com](https://render.com) avec votre compte GitHub.
2. Cliquez sur **"New +"** > **"Web Service"**.
3. Sélectionnez votre dépôt `gestionemployerBackend`.
4. **Configuration du service** :
   - **Name** : `leopardo-api`
   - **Environment** : `Docker`
   - **Docker Context** : `api/` (Très important)
   - **Dockerfile Path** : `Dockerfile.prod`
   - **Instance Type** : `Free`
5. Cliquez sur **"Advanced"** pour ajouter les **Environment Variables**.

## 3. Variables d'Environnement (À copier sur Render)

Ajoutez ces variables une par une dans l'interface de Render :

| Clé | Valeur | Note |
| :--- | :--- | :--- |
| `APP_KEY` | *(Copiez celle de votre .env local)* | Indispensable pour le chiffrement |
| `DB_CONNECTION` | `pgsql` | |
| `DB_URL` | *(L'URL complète copiée de Neon)* | Inclure le mot de passe |
| `DB_SEARCH_PATH` | `shared_tenants,public` | Requis pour le mode shared multi-tenant |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `RUN_MIGRATIONS` | `true` | Exécute les tables au premier démarrage |

## 4. Finalisation
- Cliquez sur **"Create Web Service"**.
- Render va construire l'image (cela prend 2-3 minutes).
- Une fois terminé, vous aurez une URL comme `https://leopardo-api.onrender.com`.

> [!TIP]
> Votre API sera accessible à cette adresse. Vous pourrez alors mettre à jour l'URL de l'API dans votre application mobile pour qu'elle pointe vers ce serveur réel.
