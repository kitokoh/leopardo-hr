# 🗂️ Politique de rétention des documents RH

> Version 1 — 2026-08-07 | Issue #1474
> Comble le point **PARTIEL** de `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md`
> (« Limitation de conservation », case `retention_policy` et G1 « Politique de
> retention automatique par type de document »).
>
> ⚠️ Les durées ci-dessous sont des **références indicatives** construites à partir
> des durées légales usuelles (FR : Code du travail / Code de la Sécurité sociale ;
> DZ : Loi 90-11 et textes dérivés ; MA : Code du travail). Elles doivent être
> **validées par le conseil juridique / DPO** du déploiement concerné avant mise en
> production définitive.

## 1. Principes

1. **Minimisation** : ne conserver que les données strictement nécessaires à la
   finalité déclarée du traitement (cf. `REGISTRE_TRAITEMENTS_DONNEES_RH.md`).
2. **Durées différenciées** par catégorie de document (tableau §2), pas de
   rétention uniforme « à vie ».
3. **Purge automatisée** : les logs d'audit sont purgés par la commande
   `audit:purge --older-than=24` (planifiée hebdomadairement, cf.
   `api/routes/console.php`).
4. **Responsabilité partagée** : chaque tenant reste responsable de ses documents
   métier (contrats, paie) ; la plateforme fournit les outils de purge et
   d'export, et applique les durées qu'elle maîtrise (logs, données biométriques
   de plateforme).

## 2. Durées de conservation par catégorie

| Catégorie | Durée indicative | Base légale usuelle | Mécanisme de purge |
|---|---|---|---|
| Logs d'audit (`audit_logs`) | **24 mois** | Matrice RGPD existante (art. 30 RGPD / Loi 18-07) | `audit:purge --older-than=24` (hebdo, implémentée issue #1474) |
| Données biométriques (empreintes, templates) | Jusqu'à la **fin de l'emploi** + suppression à la demande (consentement art. 9 RGPD) | RGPD art. 9, Loi 18-07 | `privacy/delete` (existant) ; revue par employé désactivé |
| Bulletins de paie / données de paie | **10 ans** (FR : obligation employeur) ; DZ : 10 ans ; MA : 5 ans | FR : C. trav. L.3243-4 ; DZ : Loi 90-11 | Export + archivage tenant ; purge au-delà de la durée locale |
| Contrats de travail | **5 ans** après la fin du contrat (FR) ; DZ : 2 ans après fin ; MA : 5 ans | FR : C. trav. L.1221-13 / L.1234-8 | Archivage ; purge selon durée locale |
| Demandes de congés / absences / avances | **5 ans** | Usuel (litiges prud'hommaux 5 ans FR) | Purge tenant |
| Dossiers disciplinaires / évaluations | **5 ans** après clôture | Usuel | Purge tenant |
| Données de recrutement (candidatures non retenues) | **2 ans** après la décision | RGPD / CNIL recommandations ; Loi 18-07 | Purge automatique 24 mois |
| Marketing / clicks de suivi (`partner_clicks`) | **90 jours** | Usuel | `growth:archive-clicks --days=90` (existant, hebdo) |
| Notifications / messages | **24 mois** | Usuel | Purge tenant |

## 3. Mise en œuvre technique

- **Commandes existantes** : `audit:purge` (nouvelle, issue #1474), `growth:archive-clicks` (existant).
- **À construire (backlog)** : commande de purge tenant par catégorie de documents
  (`documents:*`), rétention configurable par tenant (`retention_policy` — point
  G1 de la matrice), intégration avec les exports `privacy/export`.
- **Sauvegardes** : les purges n'affectent pas les backups quotidiens (cf.
  `database-backup.yml`) ; la rétention des backups est gérée par le mécanisme de
  rotation du service de backup.

## 4. Références croisées

- `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` (case « Limitation de conservation » → CONFORME à la v1)
- `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`
- `docs/security/AUDIT_API_2026-07-19.md` (chiffrement champs sensibles)
- Issue #1474 (cette politique) et #1473 (couverture OpenAPI, sans lien direct)
