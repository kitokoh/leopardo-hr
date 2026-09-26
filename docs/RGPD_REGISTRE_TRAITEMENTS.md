# Registre des traitements de données (RGPD)

> ⚠️ Document de pointe créé le 2026-08-17 — le registre opérationnel canonique est
> `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` (Version 1.0, 2026-05-14).
> Ce chemin (`docs/RGPD_REGISTRE_TRAITEMENTS.md`) était référencé par
> `docs/archive/PLAN_ACTION/POST_AUDIT_2026/07_SECURITE_RGPD.md` sans jamais avoir
> été créé ; il pointe désormais vers le registre réel.

## Contenu attendu (plan d'audit Entreprise 2026)

Le registre couvre : données collectées (pointage, paie, biométrie, fichiers RH),
base légale (contrat, obligation légale, intérêt légitime), durée de conservation
(5 ans paie DZ/MA), destinataires (manager, RH, auditeurs) — voir
`docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` et
`docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` pour l'état détaillé par exigence.

## Références

- Registre opérationnel : `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`
- Matrice de conformité RGPD / loi 18-07 (DZ) / 09-08 (MA) : `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md`
- Politique de rétention : `docs/security/POLITIQUE_RETENTION_DOCUMENTS.md`

## Rétention des logs d'IA (assistant conversationnel) — #8144

- **`ai_audit_logs`** (prompt + réponse en clair, jusqu'à 10 000 caractères) :
  rétention **90 jours** par défaut, configurable via
  `AI_AUDIT_LOG_RETENTION_DAYS` (`config/ai.php` → `ai.audit_log_retention_days`).
  Purge planifiée **quotidienne** `ai:purge-audit-logs` (idempotente,
  `--older-than`, `--company`, `--dry-run`) — l'appel planifié dépend de la
  fiabilité du scheduler (BOS-003).
- **Minimisation à la transmission** : tous les payloads sortants vers les
  fournisseurs LLM cloud (Claude/OpenAI/Groq) sont nettoyés (PII masquées)
  avant envoi (#8141, BOS-001).
- **Logs applicatifs du parcours trial** : aucun email de prospect en clair —
  les événements sont journalisés avec un identifiant haché (`email_hash`,
  #8144).
- Aucun chiffrement au repos spécifique sur `ai_audit_logs` à ce stade
  (hors périmètre de #8144).
