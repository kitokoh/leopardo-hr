# Audit Expert — 2026-08-09 (session : implémentation des issues + vérification des audits antérieurs)

> Agent autonome, session du 2026-08-09. Méthode : chaque point vérifié contre l'état réel du code (greps, diff, CI), pas contre les dires des rapports précédents.

## 1. Issues ouvertes — état après cette session

### 1.1 Implémentées et mergées
- **F-12 benchmark** (#1594/#1542) : protocole + garde régression >20 % + consignation auto (PR #1653/#1654). Baseline : **10 000 employés → calculate 90 s** (20× sous la cible < 30 min).
- **F-30 mobile** (#1560) : 15 widget tests critiques + `flutter test` en CI (PR #1645).
- **F-21 géofencing** (partiel, #1551) : tolérance GPS + tests (PR #1652).
- **F-07 indemnité congés** (#1537) : données réelles (PR #1634) — **défauts corrigés par PR #1659** (jours ACQUIS = balance+used+pending ; référence 12 mois normalisée).
- **F-23 kit démo + F-06 fixtures** (#1553/#1536) : seeder + guide + tests (PR #1656).
- **F-19 revue sécurité multi-tenant paie** (#1549) : rapport + 5 tests adversarial, aucune fuite (PR #1640).

### 1.2 Implémentées, revues et corrigées (PR ouvertes à merger)
| PR | Issue(s) | Correctifs apportés en session |
|---|---|---|
| #1624 | #1613 migrations | **Garde inversée corrigée** (`locked_by/locked_at` jamais créés sinon) ; `$schema` hors bloc ; **+17 migrations** converties au helper `resolveTableSchema` — plus aucune garde/ALTER au nom nu |
| #1632 | #1541 clôture API | Audit déverrouillage : `old_values` **avant** update ; transaction bulletins ; test de non-régression |
| #1633 | #1540 journal CSV | **Injection de formule CSV neutralisée** (=+-@ tab CR) + test |
| #1638 | #1550 écarts pointage→paie | APPROVE (suivis : direction « trop payé », N+1, pagination) |
| #1642 | #1538 solde de tout compte | Golden test **figé** (date) ; `position` → `?->name` (crash dompdf) ; `end_date` = `contract_end` ; congés non pris = acquis non pris ; prorata en **jours ouvrés** |
| #1643 | #1539 mentions PDF | APPROVE |
| #1644 | #1593/#1606 F-13b | APPROVE (note : 000205/000003 → additive à prévoir) |
| #1646 | #1548 effacement RGPD | `--force`/`--dry-run` + confirmation ; PII complètes (NID, IBAN, banque, zkteco_id, emails secondaires, gender) ; purge `biometric_enrollment_requests` ; suppression fichier photo ; garde `--company` ; idempotence |
| #1649 | #1551 offline mobile | Purge 4xx **définitifs uniquement** {400,404,409,410,422} ; 8 tests unitaires |
| #1650 | #1594 N+1 | Listener SQL retiré ; BENCHMARK.md complété |
| #1651 | #1543 F-13 | `--report-f13` inclut les tests paie racine |

### 1.3 Action humaine uniquement
- **#1472 (P0)** : rotation Redis Upstash + purge historique git — ouverte depuis 2026-07-01. Runbook prêt : `docs/security/RUNBOOK_ROTATION_REDIS_1472.md`. Rien de codable (console + force-push = décision humaine).
- **#1601** : secret Neon dans l'historique → purge coordonnée avec #1472. **#1467** : rotation clés Google (console Firebase).

## 2. Vérification des audits antérieurs — points non encore implémentés

(vérifié sur main 71fc8cf ; ✅ implémenté · 🟡 partiel · ❌ non implémenté)

| Point | Origine | Statut réel |
|---|---|---|
| Rotation Redis + purge git | AUDIT 07-01 P0 | ❌ action humaine (#1472) |
| Secret Neon historique | #1601 | ❌ action humaine |
| Champs salaires chiffrés au repos (base_salary, pay_slips, runs) | DATA_AT_REST | ❌ exception documentée (agrégats/exports) — réévaluer |
| **Rétention/purge biométrie** | DATA_AT_REST/#1548 | ❌ → **spec S-1** |
| **Audit des accès aux données sensibles** | #1548 | 🟡 seul l'anonymisation est tracé → **spec S-2** |
| `effective_from` nullable (500 potentiel) | N9P4 | ❌ `2026_05_10_100001:63` → **spec S-3** |
| `safeEmployeeBalance` avale les exceptions | N9P4 | ❌ `PayrollCycleService:278` → **spec S-3** |
| `taxSlabs()` sans commentaire national | N9P4 D-01 | ❌ → **spec S-3** (mineur) |
| `.env.testing` DB_HOST=pgsql trompeur | passe 5 | ✅ corrigé (session) |
| PHPStan baseline 652 entrées | PA2-ARCH-005 | 🟡 ratchet en place, réduction à programmer |
| `temp-regen-phpstan-strict.yml` sur main | #1600 | ✅ supprimé (session) |
| Dérive CreatesMvpSchema (189 fichiers) | #1569/#1543 | 🟡 noyau migré (PR #1644/#1651) ; résiduel hors noyau à mesurer |
| Couverture Payroll ≥ 80 % bloquante | #1602 | 🟡 gate advisory (45 %) → **spec S-4** |
| Migrations 000205/000003 modifiées rétroactivement | revue #1644 | 🟡 → **spec S-3** |
| Emails transactionnels non localisés (0 `__()`) | PA2-I18N-006 | ❌ → **spec S-5** |
| Extraction mobile/web/admin i18n | PA2-I18N-009/010/012 | ❌ → **spec S-5** |
| Date/currency par locale (30 fr-FR) | PA2-I18N-008 | ❌ → **spec S-5** |
| Qualité turque vitrine | PA2-I18N-011 | ❌ → **spec S-5** |
| WCAG W6 feedback inline (0 aria-invalid) | WCAG | ❌ → **spec S-6** |
| Parité widgets platform_admin | PA2-MOB-013 | ❌ → **spec S-6** |
| Couplage inter-modules HR | PA2-ARCH-003 | ❌ dette |
| Contrôleurs fat / déficit Actions | PA2-ARCH-009 | ❌ dette assumée |
| Reconciliation backlog (106 tickets) | 27_RECONCILIATION | ❌ → **spec S-7** |
| OpenAPI : 10 routes payroll_engine allowlistées | OPENAPI 07-19 | 🟡 gate verte (0 drift) |

## 3. Audit propre — constats

### 3.1 Solide
- Isolation tenant paie cohérente sur 13 surfaces revues (404 cross-tenant, RBAC principal/comptable, throttle) ; aucun IDOR.
- Pattern `resolveTableSchema` désormais exhaustif sur `migrations/tenant`.
- Chaîne CI requise cohérente ; actions tierces épinglées par SHA ; gouvernance strict_types/.env/mojibake/secret-scan en place.

### 3.2 Défauts trouvés et corrigés en session
1. **Garde inversée** `2026_08_09_000001` (branche #1624) : F-11 cassé au merge sinon.
2. **Injection CSV** sur le journal de paie.
3. **Audit trail de déverrouillage faux** (refresh avant getOriginal).
4. **Golden F-08 dépendant de la date**.
5. **`position` rendu comme modèle** dans le PDF certificat (crash dompdf).
6. **Indemnité congés** : dénominateur = solde restant au lieu des jours acquis (PR #1634 mergée) → #1659.
7. **Anonymisation RGPD incomplète** (banque, NID, zkteco, biométrie, photo).
8. **Purge 4xx trop large** en offline mobile (perte silencieuse de pointages).

### 3.3 Risques résiduels
- **R-1** : salaires en clair au repos (choix documenté ; chiffrement colonnes/tenant-keys = état de l'art pour un produit paie).
- **R-2** : `public/0001` pose `SET search_path TO public` → fragilité `current_schema()`-based (harmonisé par #1644 ; suppression à programmer, spec S-3).
- **R-3** : gate coverage Payroll advisory.
- **R-4** : **l'historique git public contient des secrets réels (Redis, Neon)** — #1472/#1601, priorité absolue.

## 4. Spécifications nouvelles

Cf. `docs/specifications/SPECS_AUDIT_EXPERT_2026-08-09.md` — S-1 (rétention biométrie), S-2 (journalisation accès sensibles), S-3 (durcissement paie), S-4 (coverage ≥ 80 %), S-5 (i18n), S-6 (accessibilité/parité widgets), S-7 (backlog reconciliation). Issues créées en parallèle.
