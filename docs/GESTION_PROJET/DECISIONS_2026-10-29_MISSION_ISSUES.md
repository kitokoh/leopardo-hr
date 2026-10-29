# Décisions — mission « implémenter toutes les issues ouvertes » (2026-10-29)

Registre des décisions prises en autonomie (rôle fondateur / DevOps / PM) pendant la
session du 2026-10-29. Chaque décision est datée, motivée et rattachée à une issue.

## Principe directeur

Une issue se ferme sur une **preuve**, pas sur une intention. Trois catégories :

| Catégorie | Critère de fermeture |
|---|---|
| **Code** | Le correctif est sur `main`, les tests ciblés passent, la garde CI est verte. |
| **Décision** | La question posée est tranchée et la décision est écrite ici + dans l'issue. |
| **Action propriétaire** | Un humain doit agir hors dépôt (secret, paiement, dashboard, signature). L'issue reste ouverte ou est convertie en ticket de suivi. |

Le piège à éviter est le **faux vert** : fermer une issue parce que le fichier de
configuration contient la bonne ligne, alors que la production ne sert pas le correctif.
C'est exactement ce qui a rendu #7994 et #7967 invisibles pendant des semaines.

---

## D1 — Les 9 issues « actions propriétaire » ne se ferment pas par du code

**Issues :** #7657, #7994, #7967, #8285, #8191, #8232, #7924, #7941, #8880

**Constat vérifié.** Le volet code de ces neuf issues est déjà sur `main` :

- #7657 : les deux gardes planifiées sont en *fail-loud* volontaire ; la protection de
  `main` est conforme au canonique (4 checks requis, `strict`, `enforce_admins`).
- #7994 : `travel-web-deploy.yml` n'a plus de skip permanent, `marketplace-deploy.yml`
  existe et tourne, et une garde de dérive des fronts a été ajoutée.
- #7967 : `APP_KEY: sync: false` sur les 4 blueprints + garde
  `check-render-app-key-pinned.sh` ; worker `type: worker`, `plan: standard`,
  `numInstances: 1` déclaré.
- #8285 : la panne est côté Cloudflare (Worker `gestionemploye` connecté au dépôt sans
  config wrangler) — le token API des agents n'a pas la permission Builds.
- #8191 : l'outillage `audit:scheduler-duplicates` est livré ; l'exécution demande une
  copie des données de production.
- #8232 : le pilote Léa demande des tenants de test et un panel bêta réels.
- #7924 / #7941 : la conformité légale multi-marchés demande des experts-comptables
  mandatés par marché (signature humaine, responsabilité engagée).
- #8880 : le pilote Dakar demande un responsable terrain désigné et un tableur externe.

**Décision.** Ces issues **restent ouvertes** et sont converties en **tickets de suivi
ops**, avec la liste exacte de l'action humaine attendue. Les fermer reviendrait à
affirmer qu'une sauvegarde existe, qu'un worker paie tourne et que les fronts sont
déployés — trois affirmations fausses aujourd'hui.

**Ce qui est livré à la place, et qui a de la valeur :** ce registre, plus une garde
CI qui empêche la réapparition silencieuse du problème (voir D2).

---

## D2 — Une garde de dérive des fronts Vercel, plutôt qu'un énième commentaire

**Issue :** #7994

Le vrai défaut de #7994 n'est pas le skip : c'est que **le retard est resté invisible
pendant des semaines** alors que la CI était verte. Un workflow vert qui ne déploie rien
est un faux positif de pipeline.

**Décision.** La garde `deploy-drift-guard.yml` (jobs `Drift front Vercel (travel-web)`
et `(marketplace)`) compare la version servie au référentiel et échoue au-delà du seuil.
C'est la réponse structurelle : le prochain retard sera bruyant.

---

## D3 — Ne jamais désactiver une garde fail-loud pour « faire vert »

**Issues :** #7657, #8285

Les gardes `branch-protection-guard.yml` et `database-backup.yml` échouent parce que des
secrets manquent. Les couper déplacerait la panne du CI vers le jour de la restauration
de données — c'est-à-dire le pire moment pour la découvrir.

**Décision.** Les gardes restent actives. Le rouge est une **alarme légitime**, pas un
bug. La doctrine est documentée et les deux secrets sont listés comme actions
propriétaire dans ce registre.

**Priorité relative.** Si un seul des deux secrets doit être posé cette semaine, c'est
celui du **backup S3** : c'est le seul item de ce lot avec un risque de perte de données
irréversible.

---

## D4 — `main` doit rester vert : les PR vertes sont mergées avant tout nouveau travail

**Constat.** Douze PR étaient ouvertes, dont dix avec les quatre checks requis verts.
Elles se bloquaient mutuellement : `Backend Security (Composer Audit)` était rouge sur
`main` **et** sur les sept PR simultanément à cause de la CVE-2026-45070 publiée en
amont, et `Route → OpenAPI coverage guard` était rouge sur `main` — donc aucune PR ne
pouvait être fusionnée quel que soit son contenu.

**Décision.** Traiter d'abord les **débloqueurs collectifs**, dans cet ordre :

1. #9006 — bump `symfony/mime` v7.4.22 (CVE-2026-45070) : débloque *Backend Security*
   sur toutes les PR.
2. #8999 — remise au vert du garde OpenAPI (4 routes Travel non documentées + 11 drifts
   de noms de paramètres) : débloque *toutes* les fusions.
3. #9008 — `PageModerationAudit` sans `BelongsToCompany` : débloque *Hygiene Guards* sur
   `main` et sur les sept PR.
4. Puis les PR de contenu, une par une.

**Résultat.** 9 PR mergées le 2026-10-29 (voir le récapitulatif de session).

---

## D5 — Une PR empilée se remplace, elle ne se rebase pas indéfiniment

**Issues :** #8997 → #9003

#8997 portait 36 fichiers et des commits déjà mergés ailleurs (dont
`PageModerationAudit` via #9008). Son diff était illisible et son rebase conflictuel :
un conflit sur un fichier déjà corrigé sur `main` n'est pas un conflit de contenu, c'est
un empilement.

**Décision.** #8997 est **fermée comme superseded** par #9003, qui reprend le même
périmètre sur une base `origin/main` propre. Le contenu utile est conservé, l'historique
est lisible.

**Règle durable.** Une branche = un groupe d'issues. Une branche qui mélange plusieurs
groupes ne peut pas être relue, donc ne peut pas être validée.

---

## D6 — Le contrat d'API prime sur le test, mais le consommateur prime sur le contrat

**Issues :** #8396 (lots Travel, Marketing)

Deux cas rencontrés pendant la résorption :

1. **Marketing** — `kind: image` exige un fichier (`MarketingAssetKind::requiresFile()`).
   Deux tests attendaient `201` pour une ressource binaire sans fichier. La règle produit
   est conservée (une ressource « image » sans image serait fantôme) : ce sont les tests
   qui téléversent un fichier factice.
2. **Travel** — le formulaire authentifié TRAVEL-416 expose `name` + email **ou**
   téléphone + `consent`, alors que le formulaire public TRAVEL-913 expose
   `first_name`/`last_name` + `consent_email`. Le FormRequest accepte les deux surfaces ;
   le consentement reste obligatoire via `withValidator()` quel que soit le nom du champ.

**Décision sur le payload d'événement.** `travel.contact.submitted.v1` émet
**`consent_email` ET `consent_given`**. Le premier est le nom historique lu par le BC CRM,
le second le nom générique introduit par TRAVEL-416. Émettre les deux ne casse aucun
consommateur existant — c'est la seule façon de faire évoluer un contrat d'événement sans
fenêtre de migration.

---

## D7 — « Livré dans le code » ≠ « livré en prod »

**Issues :** #7994, #7967, #7657

Un workflow vert ne prouve pas qu'un déploiement a eu lieu. Un `plan: standard` dans un
YAML ne prouve pas qu'un job a été traité. Une protection de branche conforme dans l'API
ne prouve pas que la garde qui la vérifie fonctionne.

**Décision.** Ces issues ne se ferment que sur une **preuve d'exécution** : URL de
déploiement promue, capture d'un job traité, deux jours de gardes vertes consécutifs.
Tant que la preuve manque, l'issue reste ouverte — c'est le rappel qui fait agir.

---

## Actions propriétaire restantes (à traiter hors dépôt)

| # | Action | Où | Risque si non fait |
|---|---|---|---|
| 1 | Bucket S3 `eu-west-3` + IAM écriture, secrets `BACKUP_S3_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Console AWS + secrets Actions | **Aucune sauvegarde de la base de production** |
| 2 | PAT fine-grained, permission *Administration : Read-only*, secret `BRANCH_PROTECTION_TOKEN` | Settings → Secrets → Actions | Garde de protection aveugle (la protection elle-même est conforme) |
| 3 | Déconnecter `kitokoh/leopardo-hr` du Worker Cloudflare `gestionemploye` (ou corriger sa config de build) | Dashboard Cloudflare → Workers & Pages | Check rouge récurrent sur `main` |
| 4 | Vérifier branche de production + `Ignored Build Step` + variables d'env des projets Vercel `leopardo-travel-prod` et `leopardo-marche`, puis promouvoir un déploiement | Dashboard Vercel | Correctif de sécurité #7963 non servi sur 2 fronts |
| 5 | Moyen de paiement Render + provisionner le worker starter (~7 $/mois) | Console Render | Jobs paie/notifications perdus pendant le spin-down |
| 6 | Restaurer un snapshot prod, exécuter `audit:scheduler-duplicates` puis les corrections validées | Accès données prod | Doublons de congés/travel non mesurés |
| 7 | Mandater un expert-comptable par marché et signer `docs/payroll/VALIDATION_EXPERTE.md` | Process humain | Aucune promesse de conformité commerciale permise |
| 8 | Désigner le responsable terrain Dakar + tableur externe | Process humain | Pilote Dakar non démarrable |
| 9 | Tenants de test + panel bêta pour le pilote Léa | Process humain | Décision GA/itération/retrait non fondée |

---

## Récapitulatif de session

Voir `docs/GESTION_PROJET/RECAP_2026-10-29_MISSION_ISSUES.md`.
