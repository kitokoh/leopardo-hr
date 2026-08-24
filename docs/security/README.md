# Sécurité — Leopardo RH

Index de la documentation sécurité. **MAJ : 2026-08-17 (revue PM).**

> Pour la politique de divulgation responsable et les conventions (dont #1614 —
> ne jamais citer un secret réel, même partiel), voir [`SECURITY.md`](../../SECURITY.md) à la racine.

## Gestion des secrets (état 2026-08-11)

| Sujet | Doc |
|---|---|
| Rotation Redis Upstash (#1472, faite 10/08/2026) | `RUNBOOK_ROTATION_REDIS_1472.md` |
| Purge historique git + rotation clés Google (#1467) | `RUNBOOK_SECRET_ROTATION_PURGE.md` · `POST_MORTEM_PURGE_2026-08-11.md` |
| Inventaire & checklist propriétaire | `HISTORIQUE_SECRETS.md` |
| Forks publics à purger | `PLAN_PURGE_FORKS_2026-08-11.md` |

> ⚠️ Reste ouvert (action propriétaire) : rotation console Neon (#1601), révocation
> des 2 clés Google/Firebase (#1467), contacts des 5 forks (plan §3).

## Audits & conformité

- `AUDIT_SECURITE_2026-08-23.md` — audit sécurité consolidé OWASP Top 10 + scan secrets (issue #5281, 0 critique ouvert)


- `AUDIT_API_2026-07-19.md` — audit API (SSRF, Sanctum, CORS)
- `REVUE_SECURITE_MULTI_TENANT_PAIE_2026-08-09.md` — revue multi-tenant paie (F-19, 5 tests adversarial)
- `ADMIN_CSRF_XSS_AUDIT.md` (15/08) · `SQL_INJECTION_AUDIT.md` · `RBAC_ROUTE_MATRIX.md` (16/08)
- `MATRICE_CONFORMITE_RGPD_LOI_18_07.md` · `DPA.md` · `REGISTRE_TRAITEMENTS_DONNEES_RH.md` · `POLITIQUE_RETENTION_DOCUMENTS.md`
- `DATA_AT_REST.md` — chiffrement au repos (choix documentés, exception salaires)

## Runbooks & incidents

- `SECURITY_INCIDENT_REDIS_2026-07.md` — incident Redis (mot de passe committé)

## Posture

La posture marketing (TLS/WAF/AES-256/ISO-27001) est décrite dans `SECURITY.md`.
Le **posture opérationnelle réelle** (scanning, rotation, conventions) est tracée
dans les runbooks ci-dessus + `../../SECURITY.md` (racine, MAJ 09/08/2026).
