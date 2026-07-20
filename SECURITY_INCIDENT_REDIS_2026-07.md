# 🔴 Incident de sécurité — Mot de passe Redis Upstash exposé en clair

Statut : **ROTATION UPSTASH + PURGE HISTORIQUE GIT NON EFFECTUÉES — action manuelle requise**
Sévérité : Critique
Ouvert depuis : au moins 2026-07-01 (première mention dans `AUDIT.md`)
Documentation nettoyée le : 2026-07-19

---

## 1. Résumé

Un mot de passe Redis Upstash réel a été committé en clair dans plusieurs fichiers Markdown
du dépôt (`AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/08_SCALABILITE_REDIS.md`,
`docs/PLAN_ACTION/POST_AUDIT_2026/01_ROADMAP_30J.md`, `docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md`).
Le dépôt GitHub `kitokoh/leopardo-hr` est **public** (5 étoiles, 1 fork au moment de cet audit) :
n'importe qui pouvant lire l'historique git a accès à ce mot de passe depuis sa date de commit.

Ce document a été détecté lors de plusieurs audits précédents (`AUDIT.md` section 2.3,
`docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md` section 4) mais la case de remédiation
restait **non cochée** sans preuve de rotation effective.

## 2. Ce qui a été fait aujourd'hui (2026-07-19)

- Retrait de la valeur en clair (mot de passe + hostname réel `noted-tomcat-92597.upstash.io`)
  de tous les fichiers Markdown suivis dans le dépôt, remplacée par des placeholders génériques.
- Ce fichier créé comme référence centrale de l'incident.
- **Aucune modification de code applicatif** — `api/.env.example` utilisait déjà des placeholders
  (`VOTRE_HOST.upstash.io` / `VOTRE_PASSWORD_UPSTASH`), il n'a pas eu besoin de correction.

## 3. Ce qui N'A PAS été fait et reste requis (actions manuelles, hors périmètre agent/code)

### 3.1 Rotation immédiate du mot de passe Upstash — 🔴 P0, à faire en premier
1. Se connecter à https://console.upstash.com
2. Sélectionner la base Redis concernée (ex-hostname `noted-tomcat-92597`)
3. Settings → Reset Password (ou régénérer les credentials complets si l'option existe)
4. Mettre à jour immédiatement sur Render : `REDIS_URL` / `REDIS_HOST` / `REDIS_PASSWORD`
   dans Environment Variables du service API + du service Background Worker
5. Redéployer le service API et le worker de queue pour appliquer les nouvelles credentials
6. Vérifier `/api/v1/health` → `checks.redis.ok == true` après redéploiement

**Tant que cette rotation n'est pas faite, le nettoyage de la documentation ci-dessus est
cosmétique : l'ancien secret reste valide et exploitable via l'historique git.**

### 3.2 Purge de l'historique git — 🔴 P0, après la rotation
Nettoyer la doc actuelle (fait aujourd'hui) ne supprime PAS le secret des commits passés.
Il reste récupérable via `git log -p` ou `git clone` par quiconque.

Deux options, à faire **après** confirmation de la rotation (sinon le nouveau secret risque
d'être exposé au même titre si mal manipulé) :

```bash
# Option BFG Repo-Cleaner (plus simple)
bfg --replace-text redis-secrets.txt leopardo-hr.git
cd leopardo-hr.git
git reflog expire --expire=now --all && git gc --prune=now --aggressive
git push --force

# Option git filter-repo (recommandée par GitHub)
git filter-repo --replace-text redis-secrets.txt
git push --force --all
git push --force --tags
```
`redis-secrets.txt` doit contenir le mot de passe réel (récupérable via `git log -p -- AUDIT.md`
sur une copie locale) à remplacer par `***REMOVED***`.

⚠️ Un `push --force` sur `main` réécrit l'historique partagé : **coordonner avec toute l'équipe/
les autres agents actifs sur le repo avant de l'exécuter** (rebase requis pour toutes les branches
ouvertes, y compris les PRs #888-#897 actuellement ouvertes). Prévenir tous les contributeurs
de re-cloner après la purge.

### 3.3 Vérification post-incident
- [ ] Confirmer sur Upstash que l'ancien mot de passe est bien révoqué (test de connexion avec
      l'ancienne valeur → doit échouer)
- [ ] Confirmer que `secret-scan.yml` (TruffleHog) ne référence plus l'ancien secret comme "connu"
- [ ] Vérifier qu'aucun autre fichier (logs Render, configuration locale de dev, `.env` non commité
      de contributeurs) ne contient encore l'ancienne valeur
- [ ] Cocher définitivement ce point dans `AUDIT.md` avec la date et la preuve de rotation

## 4. Pourquoi ce n'a pas été fait automatiquement dans ce même passage

La rotation du mot de passe nécessite un accès au dashboard Upstash et à Render (comptes tiers,
hors périmètre du dépôt git et des credentials disponibles ici). La purge d'historique nécessite
un `push --force` destructif sur la branche par défaut d'un repo partagé actif — décision qui doit
rester **entre les mains du/de la propriétaire du repo**, coordonnée avec l'équipe, et non exécutée
unilatéralement par un agent d'audit.
