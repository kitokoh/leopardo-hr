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
