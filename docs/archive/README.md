# 📦 ARCHIVE — LEOPARDO RH

Contenu **non actif** conservé pour traçabilité. Rien ici ne doit être utilisé
comme référence opérationnelle : la source de vérité est le reste du repo
(`AGENTS.md`, `PILOTAGE.md`, `docs/`, GitHub Issues).

Règle d'archivage (issue #1729) : à chaque release, ce qui sort de `CHANGELOG.md`
et ce qui n'est plus référencé est déplacé ici — ou supprimé (l'historique git
conserve tout).

## Contenu

| Dossier | Contenu | Statut |
|---|---|---|
| `PLAN_ACTION/` | Ancien processus de planification (72 plans clos, 2025 → 2026-07) | Seuls les fichiers encore référencés (AGENTS.md, docs sécurité) sont conservés ; les autres plans sont supprimés — récupérables via l'historique git (`git log -- docs/PLAN_ACTION/`). |
| `PLAN_ACTION2/` | Ancien backlog du processus multi-agents (avant bascule GitHub Issues, PA2-OPS-008 #1279) | Intégral, référence de traçabilité — voir `docs/PLAN_ACTION2/README.md`. |

## Recherche d'un fichier supprimé

```bash
git log --all --oneline -- 'docs/archive/PLAN_ACTION/*' | head
git show <sha>:docs/archive/PLAN_ACTION/<fichier>.md
```
