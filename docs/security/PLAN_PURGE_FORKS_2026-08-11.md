# 🍴 Plan de purge des forks publics — historique avec secrets (issue #1723)

Statut : **PLAN DOCUMENTÉ** le 2026-08-11 — action de purge à exécuter par le propriétaire
Issues liées : #1723, #1601 (Neon), #1467 (Google), #1472/#1693 (purge historique)
Risque : les forks conservent l'**ancien historique** (pré-purge 2026-08-11) contenant
les valeurs réelles des secrets (Redis, Neon, clés Google) — la purge du dépôt principal
ne les a **pas** nettoyés.

---

## 1. Pourquoi

Le 2026-08-11, la purge historique du dépôt principal (`git filter-repo --replace-text`,
voir `POST_MORTEM_PURGE_2026-08-11.md`) a retiré 11 valeurs réelles de l'historique de
`kitokoh/leopardo-hr`. **Les forks existants conservent l'ancien historique** (GitHub ne
propage pas les réécritures d'historique aux forks) : les secrets restent récupérables
via `git log -p` sur ces dépôts publics.

## 2. Inventaire des 5 forks (vérifié API GitHub le 2026-08-11)

| Fork | Propriétaire | Dernier push | Risque |
|---|---|---|---|
| `heartshare/leopardo-hr` | heartshare | 2026-08-09 | 🔴 Historique pré-purge |
| `emelaslan/leopardo-hr` | emelaslan | 2026-08-08 | 🔴 Historique pré-purge |
| `mirkosalvato1-ctrl/leopardo-hr` | mirkosalvato1-ctrl | 2026-07-29 | 🔴 Historique pré-purge |
| `Ahmedmaped/hr` | Ahmedmaped | 2026-07-24 | 🔴 Historique pré-purge (nom différent) |
| `dipit-s/leopardo-hr` | dipit-s | 2026-05-31 | 🟠 Historique pré-purge, inactif |

## 3. Plan d'action (propriétaire)

1. **Contacter chaque propriétaire de fork** (issue/commentaire sur le fork ou email via
   le profil GitHub) avec le message type §4, en demandant la **suppression du fork**
   (l'historique réécrit du dépôt principal reste disponible pour tout re-fork).
2. **Après 7 jours sans réponse**, ouvrir une demande auprès du
   [GitHub Support](https://support.github.com/contact/dmca-takedown) (takedown pour
   secret compromis — les secrets de type API key/password relèvent de la politique
   DMCA/security takedown de GitHub) en joignant l'issue #1723 et le post-mortem.
3. **Vérification finale** : confirmer via l'API que les 5 forks sont supprimés ou
   vidés ; re-scanner `trufflehog git <fork_url>` pour attester l'absence de valeurs réelles.
4. **Attestation** : reporter le statut de chaque fork dans cette issue (#1723) et
   mettre à jour `HISTORIQUE_SECRETS.md`.

## 4. Message type aux propriétaires de forks

> Bonjour,
>
> Le dépôt `kitokoh/leopardo-hr` a fait l'objet d'une **purge d'historique de sécurité**
> le 2026-08-11 (secrets compromis retirés de tout l'historique git — voir
> `docs/security/POST_MORTEM_PURGE_2026-08-11.md`). Votre fork conserve l'**ancien
> historique** contenant ces secrets.
>
> Pour la sécurité de tous, merci de **supprimer votre fork** (ou au minimum de
> forcer la synchronisation depuis l'historique réécrit). L'historique propre reste
> disponible sur le dépôt principal.
>
> Merci — Kitokoh.com

## 5. Impact de la migration Git LFS (issue #1727)

La migration Git LFS prévue (#1727) réécrira une nouvelle fois l'historique de `main`.
Les forks conservant l'ancien historique seront alors **définitivement orphelins**
(incompatibles avec le nouveau `main`), ce qui réduit mécaniquement le risque de
re-synchronisation de secrets : la purge des forks (§3) reste néanmoins requise.
