# IMPLEMENTATION_PLAN — Méthode et plan de livraison

**Statut :** canonique · **Base :** `main` au 2026-10-13 · **Issue :** #8444

## 1. Méthode de travail (rappel)

1. **S'organiser** — tout travail commence par une issue GitHub claire
   (contexte, livrables, critères d'acceptation).
2. **Une branche** — on évite de multiplier les branches : une branche de
   travail, alimentée par des **commits au fil de l'eau** (un commit par
   livrable cohérent, message conventionnel `type(scope): …`).
3. **PR → main** — la branche est fusionnée via PR ; `main` protégé :
   checks CI verts obligatoires, CODEOWNERS, entrée `CHANGELOG.md`.
4. **Main propre et vert** — pas de merge avec des checks rouges ; en cas de
   régression, fix forward plutôt que revert sauvage.

Conventions détaillées : `CONVENTIONS.md`, `CONTRIBUTING.md`, `AGENTS.md`.

## 2. État des lieux mission (2026-10-13)

| Demande | État sur `main` | Suite |
|---|---|---|
| Onboarding tenant → espace restaurateur complet (resto, employés, rôles/tâches, produits, rotations, app serveur) | ✅ livré (#8430) | généraliser la checklist (#8451) |
| Espace station-service (hub, rotations, stock, ventes) | ✅ livré (#8436) | rotations généralisées (#8450) |
| Apps métier (serveur) | ✅ accès app serveur livré | KDS cuisine 🔜 (#8448), POS offline 🔜 (#8449) |
| Offline / sync | ✅ socle Edge + PWA + ZKTeco | étendre à POS/KDS (#8448, #8449) |
| Espace « Mon Logiciel » + 3D | ⚠️ page vitrine `/download` seulement | hub tenant 🔜 (#8447) |
| Branding (favicon, logo animé, 404) | ⚠️ fonctionnel mais basique | assets proposés (#8446) |
| 6 documents de référence | ❌ éclatés | ✅ ce dossier (#8444) |
| Pack marketing lancement | ⚠️ stratégie sans pack opérationnel | ✅ `docs/GOTO_MARKET/PACK_LANCEMENT/` (#8445) |

## 3. Lots de livraison

### Lot A — Références & message (cette branche, #8444 #8445 #8446)
- [x] 6 documents canoniques `docs/00_REFERENCE/`
- [x] Pack marketing `docs/GOTO_MARKET/PACK_LANCEMENT/`
- [x] Assets branding proposés `assets/branding/`

### Lot B — Distribution des apps (#8447, #8451)
- [ ] Page « Mon Logiciel » filtrée par solutions actives + 3D
- [ ] Checklists de mise en service par profil métier
- [ ] Parcours E2E « configurer mon espace → installer mes apps »

### Lot C — Offline terrain (#8448, #8449)
- [ ] KDS cuisine offline-first + sync
- [ ] POS offline : catalogue cache, file de tickets, rejeu idempotent
- [ ] Indicateurs connexion/sync dans les UIs

### Lot D — Généralisation (#8450)
- [ ] Socle rotations transversal (Planning) pour toute verticale multi-sites
- [ ] Vues gérant multi-sites unifiées

## 4. Règles de tenue du plan

- Ce plan est mis à jour dans la PR qui change l'état d'un lot.
- Toute nouvelle exigence passe d'abord par une issue, puis est rattachée à
  un lot.
- Les documents `docs/00_REFERENCE/` restent la cible de vérité synthétique ;
  le détail vit dans les dossiers spécialisés.
