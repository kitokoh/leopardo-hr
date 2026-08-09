# Security Policy — Leopardo RH

Leopardo RH prend la sécurité au sérieux : données RH sensibles (paie, biométrie, identifiants nationaux), multi-tenant, repo public.

> ⚠️ **Document détaillé : [`docs/security/SECURITY.md`](docs/security/SECURITY.md)** — architecture de sécurité, gestion des secrets, conformité, runbooks d'incident (12 documents).

## Versions supportées

Le projet suit un modèle de release continu (trunk-based, tags `v*`). Seule la dernière release `main` et le dernier tag stable sont supportés pour les correctifs de sécurité.

## 🔒 Signaler une vulnérabilité

**Ne publiez jamais une vulnérabilité dans une issue publique.**

1. **Signalez-la en privé** : [security@leopardo-rh.com](mailto:security@leopardo-rh.com) — ou utilisez l'onglet **Security → Private vulnerability reporting** du repo GitHub.
2. N'exploitez pas la vulnérabilité et ne la divulguez pas avant correction.
3. Réponse attendue sous **72 h** (accusé de réception), correctif ciblé selon la sévérité (SLA : Critique < 7 j, Élevée < 30 j).

## 📋 Attentes pour les chercheurs

- Inclure : version concernée, chemin/endpoint, reproduction minimale, impact estimé.
- Vérifier sur un environnement de test, pas sur une instance réelle.
- Les récompenses ne sont pas monétaires ; le crédit (cheers, mention dans le CHANGELOG) est accordé si demandé.

## 🛡 Garde-fous en place

- Secrets gérés via GitHub Secrets / variables d'environnement ; `.env.example` documenté.
- CI : CodeQL, TruffleHog (secret-scan), OWASP ZAP, `composer audit` + dependabot.
- Multi-tenant : isolation par `search_path` Postgres + tests d'isolation dédiés.

## 🚫 Règle absolue : ne jamais citer un secret réel (convention #1614)

**Aucun secret réel ne doit apparaître dans ce dépôt — y compris dans les rapports
d'audit, issues, commit messages, logs ou fichiers de documentation.** Le repo est
**public** : tout secret committé (même « pour mémoire », même partiellement tronqué)
est considéré compromis et impose une rotation + une purge d'historique.

Règles concrètes :

1. **Rapports d'audit** : un rapport qui décrit un incident secret doit utiliser un
   placeholder (`<REDACTED>`, `<secret>`) et référencer l'issue de suivi. **Jamais** la
   valeur réelle, même partiellement (ex. `npg_…`, `ghp_…`, `AKIA…`).
2. **Commit messages** : ne pas coller un secret dans un message de commit.
3. **Logs / issues / PR** : même règle — si une valeur sensible doit être évoquée,
   tronquer à zéro caractère significatif (`<redacted>`).
4. **`git grep` de contrôle** : l'arbre HEAD doit rester exempt de motifs connus
   (`npg_`, `AKIA`, `ghp_`, `sk_live`, `postgresql://user:pass@…`) — garde CI
   (`secret-scan.yml`) en HEAD + historique (TruffleHog).
5. En cas de doute : **ne pas committer** — demander un secret de test/dummy.

Une violation de cette règle = incident de sécurité à traiter selon le runbook
[`docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md`](docs/security/RUNBOOK_SECRET_ROTATION_PURGE.md).
