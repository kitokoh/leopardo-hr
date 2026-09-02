# assets/ — Zone d'archives visuelles (non déployée)

> Statut : **archive**. Rien sous `assets/` n'est déployé par les pipelines
> (vitrine = `front/web/public/`). Les seules références vivantes sont
> `README.md:21` (`branding/og-banner.png`) et
> `docs/design/REFONTE_PREMIUM_STATUT.md` (4 mockups `design/mockups/`).

## Nettoyage #6605 (audit Vague 3, 2026-09-01)

- **86 pointeurs LFS dupliqués supprimés** : les fichiers `screenshots/{admin,web_dashboard,mobile_employee,mobile_manager}/*`
  pointaient vers 4 images uniques (mêmes `oid sha256:`) sous des dizaines de noms différents.
  Un représentant par groupe est conservé :
  - `screenshots/admin/analytics.png` (23 fichiers identiques → 1)
  - `screenshots/mobile_manager/absences.png` (31 → 1)
  - `screenshots/mobile_employee/absences.png` (20 → 1)
  - `screenshots/web_dashboard/absences.png` (11 → 1)
  - + 6 doublons croisés (company-request, register, user-home, user-register, settings/guides) → 1 chacun.
- `branding/logo-240.png` et `branding/og-banner.png` sont les **2 seuls vrais binaires** (hors LFS) — conservés.
- `videos/*` (3 captures) : conservées (références historiques README/archives), non déployées.

## Zones canoniques

| Zone | Rôle | Déployée |
|---|---|---|
| `front/web/public/` | Visuels de la vitrine (binaires réels, hors LFS) | ✅ Vercel/Render |
| `assets/branding/` | Logos/OG (LFS) référencés par README | ❌ |
| `assets/design/mockups/` | Maquettes de référence (LFS) | ❌ |
| `assets/screenshots/` | Captures d'archive (LFS, dédupées) | ❌ |
| `marketing/` + `shared/mediaForMarketing/` | **Zones concurrentes historiques** — décision : ne plus y ajouter de visuel ; la vitrine est la source unique des assets marketing déployés. `marketing/` racine est migré vers `docs/GOTO_MARKET` (PR #6657). |

## Garde recommandée (suivi)

Un garde CI (type `check-lfs-pointer-consistency.sh`) doit échouer si un
nouveau pointeur LFS duplique un oid existant sous `assets/screenshots/`
(anti-réintroduction des 86 doublons). Non câblé à ce jour — voir issue #6605.
