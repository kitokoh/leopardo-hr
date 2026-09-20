# Kit exécutable — awesome-selfhosted : éligibilité & PR (#7872)

> Objectif : entrée dans https://github.com/awesome-selfhosted/awesome-selfhosted-data
> (soumission = fichier `software/leopardo.yml` + PR). Fork déjà en place :
> `kitokoh/awesome-selfhosted-data`. Cadre : `README.md` de ce dossier.

## ⚠️ 0. Contrainte upstream bloquante — soumission 100 % humaine

Le `CONTRIBUTING.md` d'awesome-selfhosted-data (vérifié le 2026-09-20) **interdit
explicitement** qu'un agent IA :
- ouvre la PR ou l'issue (même « pour le compte » du fondateur) ;
- **rédige le texte de l'entrée** (`software/*.yml`) qu'une personne soumettrait ensuite ;
- rédige le corps de la PR ;
- coche l'attestation de la PR template : *« The submission was done by a human, not a machine/LLM »*.

Conséquence pour ce kit : il fournit la **grille d'éligibilité vérifiée**, le **schéma des
champs avec les valeurs factuelles objectives** (URLs, licence, plateformes — données
vérifiables, explicitement autorisées par leur politique) et la **procédure pas-à-pas** —
mais la **description libre et le texte de la PR doivent être écrits personnellement par
le fondateur**, sinon l'attestation serait fausse et la PR risquerait un rejet définitif
(et une réputation grillée sur un canal à forte valeur, non renouvelable).

> Le brouillon d'entrée pré-rédigé dans l'ancien kit `../platform-submissions/awesome-selfhosted.md`
> ne doit **pas** être copié-collé tel quel : outre la contrainte ci-dessus, il viole leurs
> règles de style (« avoid redundant terms such as _open-source_, _self-hosted_ » ; format
> liste Markdown obsolète — le dépôt actuel utilise des fichiers YAML).

## 1. Grille d'éligibilité (go/no-go) — vérifiée le 2026-09-20

| # | Critère upstream | État Leopardo | Verdict |
|---|---|---|---|
| 1 | Licence libre/open source (identifiant SPDX) | MIT (`LICENSE` racine, détectée par GitHub) ; `MIT` figure dans leur `licenses.yml` | ✅ |
| 2 | Self-hostable avec instructions d'installation fonctionnelles | `docker-compose.yml` racine + `api/` + `edge/`, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md` | ⚠️ à auditer (§2) |
| 3 | **Première release il y a plus de 4 mois** | Premier tag `v1.0-staging` : **2026-04-16** (5 mois) ; mais première *release GitHub* publiée : `v4.24.0` — **2026-08-11** (~6 semaines) | ⚠️ ambigu (§3) |
| 4 | Projet activement maintenu | Releases hebdomadaires (v4.24→v4.32 entre 2026-08-11 et 2026-09-13), commits quotidiens | ✅ |
| 5 | Pas déjà listé (awesome-selfhosted, awesome-sysadmin, staticgen, dbdb.io) | Recherche « Leopardo » : absent (vérifié 2026-09-20) | ✅ |
| 6 | Pas de dépendance à un service tiers hors contrôle utilisateur | Cœur auto-hébergeable ; si un service externe est requis par défaut (mail, IA), le déclarer via `depends_3rdparty: true` | ⚠️ à confirmer |
| 7 | Un seul item par PR ; fichier kebab-case `software/leopardo.yml` ; commentaires/champs optionnels vides supprimés | Procédural | ✅ (procédure §4) |
| 8 | Soumission faite par un humain (attestation PR) | Fondateur | 🔴 bloquant si non respecté (§0) |
| 9 | `demo_url` = démo interactive uniquement (pas de vidéo ; credentials liés directement) | Démo publique à confirmer | ⚠️ omettre le champ si non stable |

**Verdict global : NO-GO immédiat, GO différé.** Le critère 3 est le point dur : les
mainteneurs vérifient en pratique la page *Releases* GitHub. Lecture prudente = première
release publiée 2026-08-11 → éligible à partir du **2026-12-11**.
**Date cible recommandée : semaine du 2026-12-14** (marge incluse). Si le fondateur veut
défendre la lecture « premier tag 2026-04-16 », le mentionner explicitement dans la PR —
mais le risque de rejet est réel et la cartouche est unique.

## 2. Audit docs self-host à finir AVANT la PR (critère d'acceptation #7872)

- [ ] `docker-compose.yml` racine : démarrage from-scratch testé sur machine vierge
      (`docker compose up` → app utilisable), variables d'env documentées.
- [ ] Procédure d'**upgrade** documentée (migration DB comprise) — exigence implicite
      « working installation instructions » + curation long terme.
- [ ] Chemin doc unique et lisible en EN : un `docs/self-hosting.md` (ou section README)
      qui pointe vers compose, config, upgrade, backup.
- [ ] Le README ne doit plus signaler de domaine principal indisponible au moment de la PR.
- [ ] Healthcheck : leur bot surveille les liens morts et l'activité — `website_url` et
      `source_code_url` doivent rester stables des années.

## 3. Valeurs factuelles pour `software/leopardo.yml` (données objectives, vérifiées)

Schéma officiel : `.github/ISSUE_TEMPLATE/addition.md` du dépôt upstream. Valeurs
factuelles ci-dessous ; **le champ `description` (texte libre, ≤ 250 car., sentence case)
est à rédiger par le fondateur** — rappels de style upstream : pas de « open-source » /
« self-hosted » / « free » (redondants sur cette liste), forme courte sans « Leopardo is »,
suffixe autorisé « (alternative to Odoo, OrangeHRM) », catégorie « business suite »
conforme à MESSAGE.md, statut pilot de la paie non sur-vendu.

| Champ | Valeur factuelle | Note |
|---|---|---|
| `name` | `"Leopardo"` | |
| `website_url` | URL canonique stable (fallback : `https://kitokoh.github.io/leopardo-hr`) | **sans UTM** (lien mort-surveillé, propre) |
| `source_code_url` | `https://github.com/kitokoh/leopardo-hr` | |
| `licenses` | `- MIT` | présent dans leur `licenses.yml` |
| `platforms` | `- PHP` et `- Docker` | = plateformes requises pour installer/exécuter (leur règle) ; Flutter/Nodejs ne sont pas des plateformes d'exécution serveur |
| `tags` | 1er tag = catégorie d'affichage (mode single-page). Candidats existants upstream à vérifier au moment de la PR : `Human Resources Management (HRM)`, `Enterprise Resource Planning (ERP)`, `Customer Relationship Management (CRM)`, `Time Tracking` | choisir le 1er tag avec soin ; max pertinents, pas exhaustif |
| `depends_3rdparty` | `true` seulement si un service externe est requis par défaut ; sinon omettre | à confirmer à l'audit §2 |
| `demo_url` | démo interactive stable uniquement, credentials liés ; sinon **omettre** | jamais une vidéo |
| `related_software_url` | omettre | |

## 4. Procédure de soumission (fondateur, à la date cible)

1. Relire `CONTRIBUTING.md` et la PR template upstream **le jour J** (les règles bougent).
2. Synchroniser le fork `kitokoh/awesome-selfhosted-data` avec `master` upstream.
3. Créer `software/leopardo.yml` sur une branche du fork : champs §3 + description
   **écrite par le fondateur** ; supprimer commentaires et champs optionnels inutilisés.
4. Vérifier les tags choisis contre `tags/*.yml` upstream (noms exacts).
5. Ouvrir la PR vers upstream : titre `Add Leopardo`, corps rédigé par le fondateur,
   **toutes les cases de la template cochées honnêtement** (dont l'attestation humaine).
6. Répondre aux retours mainteneurs sous quelques jours (réactivité = crédibilité) ;
   merge attendu ≥ ~1 semaine après approbation.
7. Après merge : ligne `../REGISTRE_CANAUX.md` (URL de l'entrée publiée, date, compte).

## 5. Définition de fait (critères d'acceptation #7872)

- [x] Grille de critères remplie, go/no-go documenté (§1 : **no-go avant 2026-12-11, date cible 2026-12-14**).
- [ ] Audit docs self-host (§2) terminé et coché.
- [ ] PR upstream ouverte par le fondateur à la date cible (ou re-décision documentée ici).
- [ ] Registre mis à jour après merge.
