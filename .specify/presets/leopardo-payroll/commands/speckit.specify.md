---
description: Create a specification for Leopardo HR with payroll compliance gates automatically injected.
---

## User Input

```text
$ARGUMENTS
```

{CORE_TEMPLATE}

---

## 🔴 Leopardo Payroll Compliance Gate (auto-injected by preset leopardo-payroll)

After generating the spec above, verify and append the following section **before saving**:

### Does this spec touch `api/app/Modules/Payroll/`?

**If YES** — append to the spec:

```markdown
## Conformité Paie (obligatoire)

- **Pays ciblé** : [CODE ISO]
- **Référence** : docs/payroll/{PAYS}_COMPLIANCE.md
- **Référence légale** : [CGI art. XX / Code du travail art. YY]

### Golden tests requis (calculés à la main)
1. SMIG → net exact avec calcul commenté
2. Cadre moyen → IRG/IRPP + cotisations + net
3. Plafond cotisation → cotisation sur cap, pas sur brut

### Tenant isolation requis
- Tout endpoint : test `assert 404 cross-tenant`
- Toutes les requêtes Eloquent : `->where('company_id', ...)`

### CI
- PHPStan strict `[OK] No errors`
- Coverage Payroll ≥ 80 %
- CHANGELOG.md mis à jour
```

**If NO** — no additional section needed.

### Anti-duplicate check

Before finalizing this spec, run:
```bash
gh issue list --state open --label "payroll" --json number,title,assignees | head -20
```

If a similar spec/issue already exists and is assigned → **stop and contribute to that issue instead**.
