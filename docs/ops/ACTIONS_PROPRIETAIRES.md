# Actions propriétaires — audit & remédiation (état au 2026-09-13)

> **Pourquoi ce document existe.** L'audit du 2026-09-11 a produit 21 issues. **14 ont été corrigées
> par du code** (elles sont tracées dans `CHANGELOG.md`). Les **8 restantes ne peuvent pas l'être** :
> elles exigent un secret, un compte fournisseur ou un plan payant — donc une action humaine.
>
> Ce fichier les centralise **pour ne rien perdre** : les issues correspondantes ont été clôturées
> pour garder un backlog exploitable, mais chaque action reste ici, avec sa commande exacte.
> Décision PM du 2026-09-13.

## Actions à effet immédiat (≈ 12 minutes au total)

| # | Action | Pourquoi c'est bloquant | Commande / geste |
|---|---|---|---|
| 1 | **Secrets de sauvegarde** | Le job quotidien échoue **bruyamment depuis 6+ jours** : aucun dump n'existe, et Neon (plan gratuit) n'a pas de PITR. Le RPO annoncé de 24 h n'est pas tenu. | `gh secret set DATABASE_URL` · `gh secret set BACKUP_S3_BUCKET` · `gh secret set AWS_ACCESS_KEY_ID` · `gh secret set AWS_SECRET_ACCESS_KEY` · `gh secret set BACKUP_AGE_RECIPIENT` (désormais **requis** : chiffrement obligatoire) |
| 2 | **`BRANCH_PROTECTION_TOKEN`** | Sans lui, la garde de protection de branche échoue **chaque jour** (fail-loud assumé) : la protection de `main` n'est vérifiée par personne. | Fine-grained PAT, permission **administration: read** → `gh secret set BRANCH_PROTECTION_TOKEN` |
| 3 | **Transport e-mail** | En production l'envoi est en `MAIL_MAILER=log` : **aucun email ne part** (invitations, OTP, accès). Le SDK `resend/resend-laravel` est déjà dans le repo. | `MAIL_MAILER=resend` + `RESEND_KEY` + expéditeur sur domaine vérifié (Render → API dev, et prod à la prochaine release) |

> ⚠️ **Après l'action 1**, la sauvegarde reprend et le **contrôle de fraîcheur** (issu de #7291)
> alerte automatiquement si la chaîne se rompt à nouveau — plus besoin de surveillance manuelle.

## Décisions à prendre (aucune commande, mais bloquant)

| # | Sujet | Options | Impact |
|---|---|---|---|
| 4 | **Stockage des bulletins de paie** | (a) bucket S3 + migration des documents existants, (b) laisser tel quel | Aujourd'hui les PDF vivent sur le **disque éphémère** Render : perdus à chaque redéploiement. Documents à conservation légale. |
| 5 | **Staging** | (a) provisionner (`vars.STAGING_API_URL` + hook), (b) rester sans staging | **Décision PM 2026-09-13** : le déclenchement automatique a été retiré (#7256), le workflow reste manuel et prêt. Réactiver = 1 ligne + les 2 variables. |
| 6 | **Canal d'alerte** | (a) e-mail, (b) Slack (`SLACK_MONITORING_WEBHOOK_URL`), (c) paging | Aujourd'hui les incidents ne sont visibles que comme des runs rouges : personne n'est notifié. |
| 7 | **Plan Render** | (a) plan payant (worker + scheduler), (b) statu quo | En gratuit : tâches planifiées **non exécutées** (facturation, échéances, purges) et drain de queue assuré par un cron GitHub détenant des accès prod. |
| 8 | **Rotation des secrets** | (a) rotation complète, (b) rotation partielle | Des jetons exposés le 2026-09-09 n'ont pas tous été régénérés (voir `docs/ops/JETONS_ROTATION_RUNBOOK.md`). |

## Ce qui a été corrigé par du code (pour mémoire)

14 issues fermées, dont les plus structurantes :

- **Onboarding** — lien d'activation 404 (#7259), proxy de suivi qui perdait `password_set` (#7265),
  bouton « actualiser » sans effet (#7264), redirection post-activation (#7266), pré-validation du
  lien + société suspendue (#7267), **repli automatique vers le parcours guidé quand l'e-mail est
  indisponible** (#7251), complétion **vérifiée** des étapes mesurables (#7292), persistance serveur
  de « onboarding terminé » (#7262).
- **Exploitation** — `/health/ready` reflète enfin les dépendances critiques (#7255), **chiffrement
  obligatoire + upload vérifié + contrôle de fraîcheur** des sauvegardes (#7290), inventaire complet
  des secrets CI **et garde anti-dérive** (#7271), garde de protection de branche **planifiée et
  fail-loud** (#7270), **un check requis ne peut plus être vert sans verdict du gate** (#7269).
- Divers : message d'erreur localisé (#7268), staging ne se déclenche plus dans le vide (#7256).

## Suivi

Ce document doit être mis à jour quand une action est réalisée (cocher / retirer la ligne) — et il
est la référence en cas de reprise sur incident, au même titre que les runbooks de `docs/ops/`.
