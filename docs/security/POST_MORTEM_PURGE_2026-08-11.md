# ✅ Post-mortem — Purge de l'historique git des secrets exposés (P0 #1472 / #1693)

Statut : **PURGE EFFECTUÉE** le 2026-08-11 (action humaine déléguée au propriétaire, session agent)
Sévérité initiale : Critique (dépôt public, secrets récupérables via `git log -p`)
Issues liées : #1472 (rotation + purge), #1693 (purge), #1601 (Neon), #1467 (clés Google)
Spec : A-2 (#1680) — `docs/security/HISTORIQUE_SECRETS.md`

---

## 1. Résumé

L'historique git public du dépôt contenait des **secrets réels** malgré le nettoyage
documentaire de l'arbre de travail (placeholders) :

- **Mot de passe Redis Upstash** (`docs/audits/AUDIT.md`, `docs/PLAN_ACTION/POST_AUDIT_2026/*`,
  `.env.example` historique) — rotation déjà faite (2026-08-10, #1472).
- **URL PostgreSQL Neon complète** (mot de passe `npg_…`) — commit `70ca415c` (2026-04-14),
  `docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md` (#1601).
- **Clés API Google** (`AIzaSyCYauGS…`, `AIzaSyAkWnXd…` — projet Firebase `leopardo-rh`)
  dans 4 × `google-services.json` historiques (#1467).

Toutes ces valeurs réelles ont été **retirées de la totalité de l'historique git** et
remplacées par des placeholders `REDACTED_*` via `git filter-repo --replace-text`.

## 2. Exécution (2026-08-11)

1. **Inventaire** : extraction des valeurs réelles depuis l'historique complet
   (`git log --all -p`), croisées avec `docs/security/HISTORIQUE_SECRETS.md` → **11 valeurs uniques**.
2. **Sauvegarde** : bundle complet avant purge (`git bundle create --all`), conservé hors du dépôt.
3. **Réécriture** : clone de travail → `git filter-repo --replace-text replace-text.txt --force`
   (remplacement `valeur_réelle ==> REDACTED_*`, 2685 commits analysés, ~6,5 s).
4. **Force-push coordonné** : `main` + tag `v1.0-staging` (aucune branche ouverte à ce moment —
   coordination avec l'équipe effectuée avant la fenêtre).

## 3. Vérifications (gates)

| Gate | Avant | Après | Statut |
|---|---|---|---|
| Occurrences des 11 valeurs dans `git log --all -p` | 11/11 présentes | **0/11** | ✅ |
| gitleaks v8.18.4 (historique complet) | 44 findings | **12 findings** | ✅ (voir §4) |
| Commits `main` | 2619 | 2619 (SHAs réécrits) | ✅ aucune perte |
| Tag `v1.0-staging` | 71 commits | 71 commits (réécrit) | ✅ aucune perte |
| TruffleHog A-2 (prochain run hebdo) | secrets connus signalés | à confirmer au prochain run | ⏳ |
| Alertes GitHub Secret Scanning (`google_api_key` ×2) | open | à résoudre après push | ⏳ |

## 4. Findings gitleaks résiduels (12) — faux positifs documentaires

Les 12 findings restants sont des **exemples de documentation, aucun secret réel** :

- `pay-sim-<uuid>` / `LEOPARDO-QR-abc123` / `abc123def456` : clés d'exemple dans les
  contrats API (`dossierdeConception/02_API_CONTRATS.md`).
- `-----BEGIN PRIVATE KEY----- MIIE…` : exemple tronqué dans `edge/keys/README.md`
  (le README indique explicitement de générer sa propre clé).
- `leopardo_maintenance_bypass_2026` : exemple de runbook de déploiement
  (`docs/PROMPTS_EXECUTION/v2/backend/CC-08_DEPLOIEMENT.md`), valeur générique non aléatoire.

## 5. Risques résiduels — à connaître

1. **Forks** : 5 forks publics existent (`heartshare`, `emelaslan`, `mirkosalvato1-ctrl`,
   `Ahmedmaped`, `dipit-s`) et **conservent l'ancien historique avec les secrets** — GitHub ne
   propage pas la réécriture aux forks. Action recommandée : contacter les propriétaires des
   forks (ou GitHub Support) pour suppression, et/ou surveiller leur activité.
2. **Vues en cache GitHub** : GitHub peut servir l'ancien historique en cache ; contacter
   GitHub Support pour purger les vues après force-push si nécessaire.
3. **Clones locaux** : tout clone antérieur au 2026-08-11 est **invalide** (historique réécrit) —
   re-cloner obligatoire. Concerne notamment `RepoBirdBot` (collaborateur actif).
4. **Rotation Neon (#1601)** : la rotation du mot de passe Neon doit être confirmée par le
   propriétaire côté console Neon/Render (purge faite, rotation à attester).

## 6. Politique durable (rappel)

- Aucun secret dans le dépôt : `google-services.json` généré en CI depuis les secrets
  Actions ; `.env.example` = placeholders uniquement.
- TruffleHog sur PR/push (secret-scan.yml) + scan hebdo A-2 (secret-history-scan.yml,
  informationnel) + gitleaks si ajouté à la CI.
- Rotation planifiée ≥ 1×/an ; rotation immédiate en cas de suspicion.
