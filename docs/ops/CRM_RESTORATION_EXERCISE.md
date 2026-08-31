# Exercice de Restauration — CRM Client (RPO/RTO)

- **Statut :** actif — livrable #5731 (CRM-V1-15)
- **Date :** 2026-08-28
- **Références :** `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`, `docs/security/CRM_THREAT_MODEL.md`

---

## 1. Objectifs

Valider la capacité à restaurer les données CRM client (schémas tenant :
`crm_accounts`, `crm_contacts`, `crm_leads`, `crm_pipelines`,
`crm_opportunities`, `crm_activities`, `crm_tasks`, `crm_imports`,
`crm_entity_merges`, consentements/campagnes V1) avec des objectifs
mesurables :

| Métrique | Cible | Mesure |
|---|---|---|
| RPO (perte de données max) | ≤ 24 h | Backup PostgreSQL quotidien (workflow `Database Backup & Restore Drill`) |
| RTO (temps de restauration) | ≤ 4 h | Chrono de l'exercice ci-dessous |
| Intégrité | 100 % lignes restaurées | Comparaison comptes par table avant/après |

## 2. Fréquence

- Exercice complet : **mensuel** (aligné sur le drill restore existant).
- Smoke de vérification : à chaque activation pilote (#5731 pilot plan).

## 3. Procédure d'exercice (sur environnement staging)

1. **Snapshot de référence** : enregistrer les compteurs par table CRM du
   tenant pilote (`SELECT count(*) FROM crm_accounts …` + échantillon de 5
   lignes signé par hash).
2. **Simulation d'incident** : supprimer un sous-ensemble de lignes CRM
   (ou renommer le schéma tenant) pour simuler une corruption.
3. **Restauration** :
   - Restaurer le dump PostgreSQL quotidien (procédure du runbook
     `RUNBOOK_BACKUP_RESTORE.md`, y compris les schémas tenant).
   - Rejouer les migrations manquantes si le dump est plus ancien que le
     schéma courant (`php artisan migrate --path=database/migrations/tenant`).
   - Vérifier `public.companies` intact (le CRM client ne touche jamais
     les données plateforme — ADR-CRM-001).
4. **Validation** :
   - Comparer les compteurs post-restauration aux compteurs de référence.
   - Smoke API : login manager pilote → `GET /api/v1/crm/accounts` retourne
     le nombre attendu ; conversion/import refusent les doublons
     (idempotence conservée).
   - Vérifier l'audit (`crm.*`) et le registre RGPD intacts.
5. **Rapport** : consigner RPO effectif, RTO mesuré, écarts constatés dans
   `docs/validation/RELEASE_READINESS_REPORT_*.md` ou un rapport dédié
   `docs/ops/CRM_RESTORATION_REPORT_YYYY_MM_DD.md`.

## 4. Pièges connus

- Les migrations tenant doivent être **idempotentes** (garde
  `schemaTableExists`) : un re-run après restauration ne doit rien casser.
- Le `search_path` doit être explicite lors des vérifications (les tables
  CRM vivent dans le schéma tenant, pas `public`).
- Les clés d'idempotence (`crm_lead_conversions`, `crm_imports.token`)
  doivent être restaurées AVEC les données métier, sinon un rejeu client
  pourrait créer des doublons après incident.
- L'exercice ne doit jamais être exécuté sur un tenant pilote réel sans
  accord (recette contrôlée uniquement).

## 5. Preuve pour le DoD #5731

Un rapport d'exercice daté (RPO/RTO mesurés + validation) sera joint à la
PR #5731 comme preuve du critère « RPO/RTO et exercice restauration ».
