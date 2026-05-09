# 🚀 LEOPARDO RH — GUIDE DE DÉMARRAGE RAPIDE

Ce guide explique comment lancer les trois composantes du projet **Leopardo RH** en environnement local.

---

## 🛠️ Pré-requis

- **Docker Desktop** (pour le Backend et la BDD)
- **Flutter SDK** (pour l'application Mobile)
- **Node.js & NPM** (pour les assets Frontend)

---

## 1. 🖥️ Backend (API) & Base de Données

Le backend utilise Laravel 11 et PostgreSQL, orchestrés avec Docker.

1.  Ouvrez un terminal dans le dossier `api/`.
2.  Lancez le script de démarrage (PowerShell) :
    ```powershell
    .\start-local.ps1 -SeedDemo
    ```
    *Ce script va monter les containers, installer les dépendances composer, exécuter les migrations et injecter les données de démo.*
3.  **URL API/Web** : `http://localhost:8000`

---

## 2. 🎨 Frontend (Dashboard Web)

Le dashboard est intégré au projet Laravel (Blade + Tailwind).

1.  Assurez-vous que les containers Docker sont lancés.
2.  Dans le dossier `api/`, installez et lancez le serveur de build Vite :
    ```bash
    npm install
    npm run dev
    ```
3.  Accédez au dashboard via : `http://localhost:8000/login`

---

## 3. 📱 Application Mobile (Flutter)

1.  Ouvrez un terminal dans le dossier `mobile/`.
2.  Installez les dépendances :
    ```bash
    flutter pub get
    ```
3.  Lancez l'application (sur Chrome ou Windows) :
    ```bash
    flutter run -d chrome
    ```

---

## 🔑 Identifiants de Démo

Utilisez ces comptes pour tester les différentes fonctionnalités du MVP. Le mot de passe est le même pour tous les comptes.

| Rôle | Email | Mot de passe |
| :--- | :--- | :--- |
| **Manager Principal** | `ahmed.benali@techcorp-algerie.dz` | `password123` |
| **Manager RH** | `fatima.meziane@techcorp-algerie.dz` | `password123` |
| **Employé** | `karim.aouad@techcorp-algerie.dz` | `password123` |

---

## 📂 Structure du Projet

- `/api` : Backend Laravel 11.
- `/mobile` : Application Flutter.
- `/docs` : Documentation complète du projet (specs, ADR, contrats API).
- `PILOTAGE.md` : **Source de vérité** de l'état actuel du projet.

---

> [!TIP]
> Si vous rencontrez un problème de permissions "symlink" sur Windows lors du lancement Flutter, activez le **Mode Développeur** dans vos paramètres Windows.
