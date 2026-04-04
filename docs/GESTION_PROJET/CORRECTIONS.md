# CORRECTIONS DOCUMENTAIRES — Sprint 0
# Version 5.0 | 04 Avril 2026
# Ces corrections doivent être appliquées AVANT de commencer le Sprint 1

---

## LISTE DES CORRECTIONS (7 items)

### C-1 — Supprimer `/auth/refresh` de l'OpenAPI

**Fichier :** `api/openapi.yaml`
**Action :** Supprimer le bloc `POST /auth/refresh` (lignes ~155-178)
**Raison :** Supprimé dans le CHANGELOG v4.0.3 (Sanctum = stratégie 401 + relogin). L'OpenAPI n'a jamais été corrigé.

---

### C-2 — Corriger `is_active` → `status` dans le modèle exemple multitenancy

**Fichier :** `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md`
**Action :** Dans le bloc PHP du modèle Employee (lignes ~170-183) :
- Remplacer `'is_active' => 'boolean'` par `'status' => 'string'` dans `$casts`
- Remplacer `is_active` par `status` dans `$fillable`
**Raison :** Le SQL utilise `status VARCHAR('active','suspended','archived')` depuis v3.1.0

---

### C-3 — Aligner `user_lookups` PK = email

**Fichier :** `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md`
**Action :** Dans la section user_lookups (lignes ~344-356) :
- Supprimer `id SERIAL PRIMARY KEY`
- Mettre `email VARCHAR(150) PRIMARY KEY`
- Ajouter `employee_id INT NOT NULL`
**Raison :** Le SQL fait loi, et le SQL dit `email` comme PK (ligne 154 du fichier SQL)

---

### C-4 — Corriger "Starter Gratuit" → "Starter 29€/mois"

**Fichier :** `docs/dossierdeConception/03_modele_economique/18_MARKETING_ET_VENTES.md`
**Action :** Ligne 14, remplacer "Starter (Gratuit)" par "Starter (29€/mois)"
**Raison :** Le modèle économique dit 29€. Le doc marketing dit gratuit. Incohérence.

---

### C-5 — Corriger le trait `HasCompanyScope` (bug double boot)

**Fichier :** `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md`
**Action :** Remplacer les 2 traits (`HasCompanyScope` + `HasCompanyId`) par UN SEUL trait `BelongsToCompany` avec UNE SEULE méthode `bootBelongsToCompany()` qui fait les deux choses :
1. Ajouter le Global Scope (WHERE company_id = ...)
2. Auto-set company_id à la création

Utiliser `app('current_company')` au lieu d'une variable `static` (risque de fuite en workers concurrents).

**Raison :** Laravel ne boot automatiquement qu'une seule méthode `boot{TraitName}` par trait. Avoir 2 traits avec 2 boot = le deuxième n'est jamais appelé automatiquement.

---

### C-6 — Déplacer `AUDIT_COMPLET_MANQUES.md` vers les archives

**Action :** `move AUDIT_COMPLET_MANQUES.md → docs/notes/archive/AUDIT_COMPLET_MANQUES.md`
**Raison :** Le document est marqué "ARCHIVÉ" en haut mais traîne à la racine, ce qui confond les agents IA.

---

### C-7 — Supprimer le répertoire `bon-fixed/`

**Action :** Supprimer le répertoire `bon-fixed/` (il est vide)
**Raison :** Répertoire vide verrouillé par un process OS, déjà retiré du Git tracking. Source de pollution.

---

## STATUT

| # | Correction | Appliquée ? |
|---|-----------|:-----------:|
| C-1 | `/auth/refresh` OpenAPI | ⬜ |
| C-2 | `is_active` → `status` | ⬜ |
| C-3 | `user_lookups` PK | ⬜ |
| C-4 | Starter prix | ⬜ |
| C-5 | Trait double boot | ⬜ |
| C-6 | Audit archive | ⬜ |
| C-7 | `bon-fixed/` supprimé | ⬜ |
