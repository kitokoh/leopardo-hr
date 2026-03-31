# CONFIGURATION SERVEUR ET CI/CD — LEOPARDO RH

Ce dossier contient les **fichiers de configuration serveur opérationnels** (référence).

## Ce que contient ce dossier

| Fichier | Rôle |
|---------|------|
| `.env.example` | Variables d'environnement à copier dans `api/.env` |
| `nginx-api.conf` | Configuration Nginx pour o2switch (proxy vers Laravel) |
| `leopardo-horizon.supervisor.conf` | Configuration Supervisor pour Laravel Horizon (queues) |
| `19_CICD_ET_GIT.md` | Stratégie Git (branches, conventions commits, procédure rollback) |

## Fichiers CI/CD GitHub Actions

Les fichiers `deploy.yml` et `tests.yml` sont dans **`.github/workflows/`** à la racine du repo.
C'est là qu'ils doivent être — GitHub les lit directement depuis ce dossier.
Ne pas les dupliquer ici.

## Déploiement initial sur o2switch

1. Copier `nginx-api.conf` dans `/etc/nginx/sites-available/leopardo-api`
2. Copier `leopardo-horizon.supervisor.conf` dans `/etc/supervisor/conf.d/`
3. Copier `.env.example` vers `api/.env` et remplir les valeurs
4. Lancer : `sudo supervisorctl reread && sudo supervisorctl update`
