# Décisions ops — session du 2026-10-29

Session : implémentation des issues ouvertes du dépôt. Ce document trace le statut
**vérifié** du lot « actions propriétaire » (#7657, #7994, #7967, #8285, #8191, #8232,
#7924, #7941, #8880) : ce qui est déjà livré dans le dépôt, ce qui reste à faire, et
pourquoi ces issues restent ouvertes.

Chaque affirmation ci-dessous a été vérifiée dans le dépôt ou via l'API, pas supposée.
Les vérifications sont indiquées, parce qu'un audit qui recopie l'énoncé d'une issue
vieille de six semaines documente l'état d'il y a six semaines.

## Le point qui compte

**Le volet code de ces issues est déjà livré.** Ce qui bloque n'est pas du code : c'est
un secret, un moyen de paiement, un accès dashboard ou une signature humaine. Un agent
ne peut pas créer un bucket S3, saisir un token dans les secrets Actions, régler un
abonnement Render, ni décider quel expert-comptable engage sa responsabilité.

Conséquence pratique : **ces issues ne peuvent pas être fermées par du travail de
dépôt**, et les fermer reviendrait à affirmer que la sauvegarde existe, que le worker
paie tourne et que les fronts sont déployés. Une issue ouverte avec une action
propriétaire explicite est l'état honnête.

## Statut par issue

### #7657 — `BRANCH_PROTECTION_TOKEN` + secrets backup S3

**MISE À JOUR (session du 2026-10-29, seconde passe) — le volet code est désormais
livré, et il ne dépend plus d'un secret propriétaire.**

Cette section affirmait « action propriétaire intégralement ». C'était vrai de la
rédaction initiale, plus de l'état actuel : deux gardes pouvaient être rendues
utiles **sans aucun secret**.

- **Branch Protection Guard** — la garde exigeait un PAT admin et échouait en
  fail-loud chaque jour. Elle lit maintenant `GET /branches/{branch}` avec le
  `GITHUB_TOKEN` par défaut (mode PARTIEL : branche protégée + liste des checks
  requis + invariant #7480) et bascule automatiquement en mode COMPLET dès que
  `BRANCH_PROTECTION_TOKEN` est posé. Le mode partiel **annonce** les champs
  qu'il ne voit pas (`strict`, `enforce_admins`, reviews, force-push, deletions) —
  il ne les présente jamais comme conformes.
  → Le job passe au vert, et il vérifie réellement quelque chose.
- **Database Backup** — les secrets AWS S3 n'ont jamais existé, mais les
  identifiants **Backblaze B2** (`B2_KEY_ID`, `B2_APP_KEY`, `B2_BUCKET_NAME`) sont
  posés depuis longtemps et déjà utilisés par `db-standby-failover.yml`. La
  sauvegarde s'appuie désormais dessus via `dev-hub/scripts/backup-store.sh`
  (interface `put`/`latest`/`provider`, S3 prioritaire si configuré, B2 natif en
  repli, échec bruyant si aucun des deux).
  → La première sauvegarde réelle de la base de production devient possible.

**Décision — ce n'est PAS un désarmement.** La doctrine « pas de check vert qui ne
vérifie rien » reste respectée : le mode partiel vérifie un sous-ensemble réel et
le dit ; il ne simule jamais la conformité complète. Le mode complet reprend seul
dès que le secret est posé. Poser `BRANCH_PROTECTION_TOKEN` reste souhaitable (il
couvre les 6 champs), mais il n'est plus un prérequis pour que la garde serve.

### #7994 — fronts Vercel prod jamais redéployés

**Reste ouverte, pour la seule partie « secrets ». Le volet code est livré.**

Vérifié dans le dépôt : `.github/workflows/marketplace-deploy.yml` existe et applique
déjà les deux durcissements attendus — skip **bloquant** sur push `main` quand les
secrets manquent (`::error` + `exit 1`), et enregistrement d'un GitHub Deployment après
une prod réussie pour alimenter la garde anti-dérive `fronts-vercel`. Le workflow
`travel-web-deploy.yml` porte le même traitement.

**Le défaut d'origine est donc corrigé** : un push qui touche le front sans déploiement
possible est désormais **visible en rouge**, plus masqué par un run vert à étapes
`skipped`. Ce qui reste est l'action propriétaire : poser `VERCEL_TRAVEL_PROD_*` et
`VERCEL_MARCHE_PROD_*` (runbook `docs/ops/travel-web-vercel.md`).

### #7967 — Render prod : queue + scheduler sur `plan: free`

**Reste ouverte, pour la seule partie « moyen de paiement ». Le volet code est livré.**

Vérifié : `APP_KEY` est en `sync: false` dans les **deux** blueprints
(`render.prod.yaml` et `render.yaml`), avec le commentaire #7967 qui explique pourquoi —
`generateValue: true` régénérait la clé à chaque re-sync et invalidait sessions et
valeurs chiffrées. Ce risque-là est fermé.

Ce qui reste est hors dépôt, et c'est le vrai sujet : `leopardo-prod` est en `plan: free`
(revérifié ligne 61 de `render.prod.yaml`), donc queue et scheduler s'arrêtent avec le
web service et **toute tâche due pendant le spin-down est perdue**. Le worker cible
`leopardo-queue-worker-prod` est écrit mais **commenté**, faute de moyen de paiement
(refus 402 à la création). Action propriétaire : provisionner le worker `starter`
(~7 $/mois), puis dé-commenter le bloc.

**Décision — ne pas dé-commenter le worker « pour avancer ».** Un blueprint qui déclare
un service que le compte ne peut pas provisionner fait échouer la sync Render entière :
on casserait le déploiement prod pour documenter une intention. Le bloc reste commenté
avec son motif, comme il l'est.

### #8285 — Cloudflare `Workers Builds: gestionemploye`

**Reste ouverte. Action propriétaire, non automatisable.**

Le Worker `gestionemploye` est rattaché au repo avec Workers Builds activé alors
qu'aucune config wrangler n'existe : chaque push produit un échec instantané. Le token
Cloudflare disponible côté agents n'a pas la permission Builds — déjà constaté (#1914,
#4216).

**Décision** : deux sorties possibles, et le choix appartient au propriétaire car il
dépend de l'intention réelle — si ce Worker doit builder ce repo, corriger sa config ;
sinon le déconnecter. Trancher à sa place serait deviner.

### #8191 — audit scheduler-duplicates sur copie prod

**Reste ouverte. Action propriétaire.**

Exige une restauration de copie prod. L'outillage est livré (#8190) et le runbook écrit
(`docs/ops/SCHEDULER_DUPLICATES_AUDIT.md`) : il n'y a rien à coder, seulement à exécuter
avec des accès prod.

### #8232 — pilote Léa mesuré

**Reste ouverte. Décision produit.**

GA / itération / retrait dépend de mesures de pilote réelles. Un agent qui trancherait
inventerait les chiffres — et une décision GA fondée sur des chiffres inventés est pire
qu'un retard.

### #7924 / #7941 — conformité légale multi-marchés, validation experte

**Restent ouvertes. Processus humains.**

Mandater un expert-comptable ou un gestionnaire de paie par marché, faire signer
`docs/payroll/VALIDATION_EXPERTE.md`, puis basculer `confidenceLevel`. Un agent peut
écrire du code de paie ; il ne peut pas engager la responsabilité d'un expert. Fermer
ces issues sans signature reviendrait à affirmer une conformité qui n'existe pas —
c'est précisément le risque que ces issues mesurent.

### #8880 — pilote Dakar, responsable terrain

**Reste ouverte. Décision de direction.**

Désigner un responsable terrain et autoriser un tableur externe sont des actes de
management. Le plan opérationnel est déjà dans `main`.

## Ce que cette session ne fait pas

Aucune de ces issues n'est fermée. C'est un choix, pas un oubli : chacune décrit une
capacité de production qui n'existe pas encore, et une issue fermée sans la capacité
correspondante est une ligne de plus dans un tracker qui ne dit plus la vérité.

Les issues qui décrivent du code réellement corrigeable sont, elles, traitées et
mergées — voir `CHANGELOG.md` et les PR associées.
