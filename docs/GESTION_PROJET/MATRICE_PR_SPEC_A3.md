# Matrice des PR par spec — réconciliation A-3 (#1681)

> Créé le 2026-08-10. Session de réconciliation : fermeture des implémentations
> parallèles (agent de session + bot « leopardo-hr bot »), une seule PR par
> spec, branches orphelines supprimées après fusion.

## Matrice

| Spec / Issue | PR fermée (redondante) | Branche fermée | PR retenue | Branche retenue | Raison du choix |
|---|---|---|---|---|---|
| S-1 biométrie (#1661) | #1670 | spec/s1-biometric-retention | **#1668** | feat/S1-biometric-retention | + complète : schedule hebdo (`routes/console.php`), pattern `run()` A-1, fixes openapi/CHANGELOG ; enrichie (config `security.biometric.retention_months` + suppression fichiers physiques) |
| S-2 accès sensibles (#1662) | #1671, #1674 | feat/S2-sensitive-access-audit, feat/S2-sensitive-access | **#1672** | spec/s2-sensitive-access-logging | Couverture ressource la plus large (6 contrôleurs : PaySlip, PayrollRun, BankExport, EndOfContract, Export HR, SocialDeclaration) ; config `security.sensitive_access_logging` (enabled + sampling + whitelist) ; #1674 contenait un artefact `api/storage/storage` |
| S-3 durcissement paie (#1663) | #1673 | feat/S3-payroll-hardening | **#1669** | spec/s3-payroll-hardening | Scope propre (14 fichiers, S-3 uniquement) ; #1673 mélangeait les 43-tests (#1684) + coverage-chunks.sh |
| S-4 couverture paie (#1664) | #1676 | spec/s4-payroll-coverage | **#1685** | feat/S4-payroll-coverage | Mesure enrichie (15 fichiers de tests paie) + tests PayrollCalculator (18) + PayrollJournalGenerator (5) + gate bloquante ; #1676 intégré (PayrollCalculatorEdgeCasesTest, PayrollCalculatorRunEdgeTest) |
| S-5 i18n (#1665) | — | — | **#1677** | spec/s5-i18n | Unique ; l10n Flutter régénéré (généré committé était obsolète) |
| S-6 a11y (#1666) | — | — | **#1675** | spec/s6-admin-a11y | Unique ; E2E a11y vérifié 4/4 localement |
| S-7 backlog (#1667) | — | — | #1678 ✅ **mergée** | spec/s7-backlog-reconciliation | Docs-only, verte |
| A-1 PendingCommand (#1679) | — | — | **#1686** | feat/A1-pendingcommand-audit | Unique |
| A-2 secrets historiques (#1680) | #1688 | feat/A2-secret-history-scan | **#1687** | feat/A2-history-secrets | Commente l'issue tracker #1472 (suivi exigé par la spec) |
| A-4 coverage 65 % (#1682) | — | — | **#1689** | feat/A4-coverage-ratchet | Unique |
| Audit docs | — | — | #1683 ✅ **mergée** | docs/audit-agent-2026-08-10 | Docs-only, verte |
| CI main vert | — | — | **#1684** | fix/ci-main-red-2026-08-09 | Unique (43 tests + Dart + CHANGELOG) |

## Branches orphelines (à supprimer après fusion de la PR retenue)

- `spec/s1-biometric-retention` (après merge #1668)
- `feat/S2-sensitive-access-audit`, `feat/S2-sensitive-access` (après merge #1672)
- `feat/S3-payroll-hardening` (après merge #1669)
- `spec/s4-payroll-coverage` (après merge #1685)
- `feat/A2-secret-history-scan` (après merge #1687)
- `spec/s7-backlog-reconciliation`, `docs/audit-agent-2026-08-10` (déjà mergées — supprimées)
- `feat/7-travaux-leopardo` (branche non liée, sans PR — à confirmer/archiver)

## Critères d'acceptation A-3

- [x] Matrice des PR par spec (ci-dessus)
- [x] PR redondantes fermées avec commentaire de référence
- [ ] 0 branche orpheline (après fusions + suppression)
- [ ] main propre
