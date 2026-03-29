# GLOSSAIRE ET DICTIONNAIRE — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. TERMES RH (DOMAINES MÉTIER)

- **Matricule** : Identifiant unique interne d'un employé (ex: EMP-001). Utilisé pour la communication non nominative et le biométrique.
- **CNAS** (Algérie) : Caisse Nationale des Assurances Sociales. Désigne souvent la cotisation de sécurité sociale (9% employé, 26% employeur).
- **IR / IRPP** : Impôt sur le Revenu (des Personnes Physiques). Calculé par tranches sur le salaire imposable.
- **Cotisations** : Prélèvements obligatoires sur le salaire (Salariales = déduites du brut, Patronales = payées par l'entreprise).
- **Brut** : Salaire avant toute déduction de cotisation ou d'impôt.
- **Net à payer** : Somme réellement versée sur le compte de l'employé (Brut - Cotisations - IR - Retenues).
- **Heures Supplémentaires (HS)** : Heures travaillées au-delà du contrat, majorées selon les seuils (ex: 25%, 50%).
- **Split-Shift** : Journée de travail divisée en plusieurs sessions (ex: 8h-12h puis 14h-18h).
- **Délai de prévenance** : Nombre de jours minimum requis entre la demande de congé et la date de début.

---

## 2. TERMES TECHNIQUES (ARCHITECTURE)

- **Tenant** : Une entreprise cliente utilisant la plateforme.
- **Multi-tenancy** : Architecture permettant de servir plusieurs clients avec une seule instance du code.
- **Multi-schéma** : Stratégie de base de données où chaque tenant a son propre espace (schema) PostgreSQL.
- **Shared-table** : Stratégie où les données de plusieurs tenants sont dans la même table, filtrées par `company_id`.
- **Inertia.js** : Pont entre Laravel et Vue.js permettant de créer des SPA sans API complexe pour le web.
- **Sanctum** : Package Laravel pour l'authentification API par tokens légers (opaques).
- **Riverpod** : Gestionnaire d'état pour Flutter utilisé dans ce projet.
- **FCM** : Firebase Cloud Messaging. Service utilisé pour envoyer des notifications push sur mobile.
- **ZKTeco** : Fabricant de lecteurs biométriques dont l'intégration est supportée par Leopardo RH.
- **Search Path** : Commande PostgreSQL (`SET search_path`) utilisée pour switcher dynamiquement entre les schémas des clients.
