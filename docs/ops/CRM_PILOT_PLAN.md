# Plan Pilote — CRM Client (feature-flagged, réversible)

- **Statut :** actif — livrable #5731 (CRM-V1-15)
- **Date :** 2026-08-28
- **Références :** ADR-CRM-004 (flux d'activation tenant), `FeatureFlag` (`companies.features`), `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`

---

## 1. Principe

Le CRM client est livré **opt-in par tenant** derrière le feature flag
`crm` (ADR-CRM-004) : désactivé par défaut pour toute entreprise, activé
explicitement par le super-admin plateforme, coupé à tout moment.

```
Platform admin ──PUT /platform/companies/{company}/features──▶ features['crm'] = true
                                                                      │
                                                                      ▼
                                Menus CRM (web/mobile) + routes /api/v1/crm/* accessibles
```

- Désactivation = les routes répondent 403/404, les menus disparaissent,
  **aucune donnée n'est supprimée** (réversibilité totale).
- L'activation/désactivation est auditée (`crm.feature.*`).

## 2. Critères de sélection du pilote

| Critère | Exigence |
|---|---|
| Taille | 1 tenant réel ≤ 25 employés (cible PME terrain) |
| Volume CRM | ≤ 5 000 comptes/contacts/leads cumulés au départ |
| Engagement | Accord écrit du client, canal de remontée dédié |
| Sauvegarde | Backup PostgreSQL quotidien vérifié (RUNBOOK_BACKUP_RESTORE) |
| Support | Fenêtre de recette contrôlée (pas de week-end seul) |

## 3. Phases

### Phase 0 — Préparation (avant activation)
- [x] Socle V0 mergé (migrations, modèles, Policies, API) + CI verte.
- [x] Threat model documenté (CRM_THREAT_MODEL.md) + tests négatifs verts.
- [ ] Exercice de restauration réalisé (CRM_RESTORATION_EXERCISE.md) — RPO/RTO mesurés.
- [ ] Registre RGPD complété pour les données CRM (PII + consentements).
- [ ] Feature flag `crm` présent dans `Company::KNOWN_MODULES`.

### Phase 1 — Activation pilote (J0)
1. Sauvegarde de référence du tenant (pg_dump) AVANT activation.
2. Activation via le mécanisme plateforme existant (`PATCH /platform/companies/{company}/features`).
3. Vérification : menus web visibles, routes `/api/v1/crm/*` accessibles au
   manager principal/rh, employés non-CRM sans accès (403).
4. Smoke E2E : import CSV de test → conversion lead → timeline → recherche.

### Phase 2 — Recette terrain (J0 → J14)
- Suivi quotidien : erreurs 5xx, latences p95, jobs queue, audit `crm.*`.
- Revue hebdo avec le client (remontées produit).
- Aucun changement de schéma pendant la recette sans PR + sauvegarde.

### Phase 3 — Bilan et généralisation (J14 → J30)
- Bilan pilote : incidents, retours UX, coût, charge.
- Gate de généralisation : 0 incident bloquant, tests négatifs verts,
  exercice restauration validé, observabilité en place.
- Activation par vague (5 → 20 → 100 tenants) avec surveillance.

## 4. Rollback (désactivation d'urgence)

| Seuil | Action |
|---|---|
| 500 récurrents sur `/api/v1/crm/*` | Désactiver le flag → routes fermées (le code reste) |
| Fuite PII constatée | Désactivation IMMÉDIATE + incident security (revue registre) |
| Corruption de données CRM | Restauration depuis la sauvegarde de référence (RPO cible ≤ 24 h) |

Procédure de rollback :
1. `PATCH /platform/companies/{company}/features` → `crm=false` (audité).
2. Vérifier 403 sur `/api/v1/crm/*` et disparition des menus.
3. Si corruption : restaurer le schéma tenant depuis le backup de référence
   (cf. RUNBOOK_BACKUP_RESTORE + CRM_RESTORATION_EXERCISE.md).
4. Post-mortem court en cas de récidive.

## 5. Critères de sortie (Definition of Done #5731)

- [ ] Threat model + tests négatifs livrés et verts.
- [ ] Exercice restauration exécuté (RPO/RTO mesurés, preuve jointe).
- [ ] Plan de charge (pic webhook/queue) documenté et testé.
- [ ] Pilote feature-flagged documenté avec rollback testé.
- [ ] CRM commercial Platform non régressé (#5716, #5758).
