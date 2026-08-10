# 🗂️ Politique de rétention des documents RH

> Version 2 — 2026-08-09 | Issue #1474 + Spec S-1 (#1661)
> Comble le point **PARTIEL** de `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md`
> (« Limitation de conservation », case `retention_policy` et G1 « Politique de
> retention automatique par type de document »), et implémente la rétention des
> **templates biométriques** (Spec S-1, #1661) restée sans durée ni purge
> automatique dans la v1.
>
> ⚠️ Les durées ci-dessous sont des **références indicatives** construites à partir
> des durées légales usuelles (FR : Code du travail / Code de la Sécurité sociale ;
> DZ : Loi 90-11 et textes dérivés, Loi 18-07 protection des données personnelles ;
> MA : Code du travail). Elles doivent être **validées par le conseil juridique /
> DPO** du déploiement concerné avant mise en production définitive.

## 1. Principes

1. **Minimisation** : ne conserver que les données strictement nécessaires à la
   finalité déclarée du traitement (cf. `REGISTRE_TRAITEMENTS_DONNEES_RH.md`).
2. **Durées différenciées** par catégorie de document (tableau §2), pas de
   rétention uniforme « à vie ».
3. **Purge automatisée** : les logs d'audit sont purgés par la commande
   `audit:purge --older-than=24` (planifiée hebdomadairement, cf.
   `api/routes/console.php`) ; les templates biométriques par
   `biometric:purge-expired` (planifiée hebdomadairement, Spec S-1/#1661).
4. **Responsabilité partagée** : chaque tenant reste responsable de ses documents
   métier (contrats, paie) ; la plateforme fournit les outils de purge et
   d'export, et applique les durées qu'elle maîtrise (logs, données biométriques
   de plateforme).

## 2. Durées de conservation par catégorie

| Catégorie | Durée indicative | Base légale usuelle | Mécanisme de purge |
|---|---|---|---|
| Logs d'audit (`audit_logs`) | **24 mois** | Matrice RGPD existante (art. 30 RGPD / Loi 18-07) | `audit:purge --older-than=24` (hebdo, implémentée issue #1474) |
| **Templates biométriques** (empreintes, visage — `biometric_*_reference_path`, `biometric_enrollment_requests.*_reference_path`) | **24 mois après la fin du contrat** ; si aucune fin de contrat renseignée : **24 mois après le consentement** (proxy « dernier usage ») ; suppression immédiate à la demande (droit d'effacement) | **Consentement explicite** (art. 9 RGPD / Loi 18-07) + finalité pointage | **`biometric:purge-expired --months=24`** (hebdo, Spec S-1/#1661) : nullifie les références expirées tenant par tenant, tracée dans `audit_logs`, `--dry-run` disponible ; `privacy/delete` et `gdpr:anonymize-employee` pour la suppression à la demande |
| Bulletins de paie / données de paie | **10 ans** (FR : obligation employeur) ; DZ : 10 ans ; MA : 5 ans | FR : C. trav. L.3243-4 ; DZ : Loi 90-11 | Export + archivage tenant ; purge au-delà de la durée locale |
| Contrats de travail | **5 ans** après la fin du contrat (FR) ; DZ : 2 ans après fin ; MA : 5 ans | FR : C. trav. L.1221-13 / L.1234-8 | Archivage ; purge selon durée locale |
| Demandes de congés / absences / avances | **5 ans** | Usuel (litiges prud'hommaux 5 ans FR) | Purge tenant |
| Dossiers disciplinaires / évaluations | **5 ans** après clôture | Usuel | Purge tenant |
| Données de recrutement (candidatures non retenues) | **2 ans** après la décision | RGPD / CNIL recommandations ; Loi 18-07 | Purge automatique 24 mois |
| Marketing / clicks de suivi (`partner_clicks`) | **90 jours** | Usuel | `growth:archive-clicks --days=90` (existant, hebdo) |
| Notifications / messages | **24 mois** | Usuel | Purge tenant |

### 2.1 Détail — templates biométriques (Spec S-1, #1661)

- **Point de départ du délai** : date de fin de contrat (`employees.contract_end`)
  si renseignée ; sinon date de consentement biométrique (`biometric_consent_at`).
- **Base légale** : consentement explicite de la personne concernée (art. 9 §2.a
  RGPD ; art. 33 et s. Loi 18-07), finalité : contrôle d'accès / pointage
  (kiosque et mobile). Le consentement est révocable à tout moment ; la
  révocation déclenche la suppression immédiate des templates (cf. `privacy/delete`).
- **Périmètre de la purge** : `employees.biometric_face_reference_path`,
  `employees.biometric_fingerprint_reference_path` (+ flags `*_enabled` remis à
  `false`), `biometric_enrollment_requests.requested_*_reference_path` des
  employés concernés. Les timestamps de consentement sont conservés à titre de
  preuve de la base légale passée ; l'opération elle-même est tracée dans
  `audit_logs` (action `biometric_templates_purged`).
- **Durée configurable** : 24 mois par défaut, via la config
  `security.biometric.retention_months` (env `BIOMETRIC_RETENTION_MONTHS`),
  surchargeable par l'option `--months={N}` de la commande.
- **Fichiers stockés** : la commande nullifie les **chemins** de référence **et**
  supprime physiquement les fichiers correspondants
  (`storage/app/biometrics/...`, disque `local`) après la mise à jour en base.
  Les templates ZKTeco résident côté terminal kiosque/SDK (hors périmètre de la
  base) ; la rotation des fichiers de la plateforme couvre le reste.

## 3. Mise en œuvre technique

- **Commandes existantes** : `audit:purge` (issue #1474), `growth:archive-clicks`,
  `biometric:purge-expired` (Spec S-1/#1661, planifiée hebdomadairement).
- **À construire (backlog)** : commande de purge tenant par catégorie de documents
  (`documents:*`), rétention configurable par tenant (`retention_policy` — point
  G1 de la matrice), intégration avec les exports `privacy/export`.
- **Sauvegardes** : les purges n'affectent pas les backups quotidiens (cf.
  `database-backup.yml`) ; la rétention des backups est gérée par le mécanisme de
  rotation du service de backup.

## 4. Références croisées

- `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` (case « Limitation de conservation » → CONFORME à la v2)
- `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`
- `docs/security/AUDIT_API_2026-07-19.md` (chiffrement champs sensibles)
- `docs/specifications/SPECS_AUDIT_EXPERT_2026-08-09.md` (Spec S-1, #1661)
- Issue #1474 (cette politique), #1661 (rétention biométrique), #1473 (couverture OpenAPI, sans lien direct)
