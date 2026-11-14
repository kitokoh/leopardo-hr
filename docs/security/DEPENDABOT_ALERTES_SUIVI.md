# Suivi des alertes Dependabot — Leopardo

**MAJ : 2026-11-14.** Ce document trace les alertes Dependabot **ouvertes** et, pour
celles qui ne peuvent pas être corrigées, la **décision de risque accepté** avec sa
justification. Il est la référence pour répondre à la question « pourquoi reste-t-il
une alerte ouverte ? » sans avoir à rouvrir l'historique des PR.

> Convention #1614 : aucun secret réel n'apparaît ici. Les identifiants cités sont
> des identifiants publics d'avis (GHSA/CVE) et de paquets.

## État courant

| Sévérité | Ouvertes |
|---|---|
| Critique | 0 |
| Élevée | 0 |
| Moyenne | 0 |
| **Faible** | **1** |

Historique : 8 alertes ouvertes au 2026-11-11 (2 élevées, 5 moyennes, 1 faible),
réduites à 1 par la PR #9169 (`follow-redirects` 1.16.1 → 1.16.2 pour les deux
élevées, `minimatch` 10.2.6 → 11.0.5 pour les cinq moyennes).

## Alerte #159 — `deepmerge` (faible) — **risque accepté**

| Champ | Valeur |
|---|---|
| Avis | GHSA-gcqx-w48m-w2h4 / CVE-2026-93753 |
| Sévérité | **Faible** (CVSS faible) |
| Paquet | `deepmerge` |
| Version installée | **4.3.1** |
| Plage vulnérable | `>= 4.2.0, <= 4.3.1` |
| Version corrigée | **aucune** (`first_patched_version: null`) |
| Manifeste | `front/web/package-lock.json` |
| Type de dépendance | **`dev: true`** — outillage de test uniquement |
| Chaîne | `jest` → `jest-config` → `deepmerge ^4.3.1` |

### Pourquoi elle n'est pas corrigée

1. **Aucune version corrigée n'existe.** `4.3.1` est la **dernière version publiée**
   sur le registre npm (`dist-tags.latest = 4.3.1`, 48 versions publiées, la plus
   récente étant 4.3.1). L'avis lui-même déclare `first_patched_version: null`.
   Il n'y a donc **rien à monter** : un bump ne ferait que réinstaller la version
   vulnérable.
2. **La dépendance est `dev` uniquement.** Elle n'est pas présente dans le bundle
   applicatif : `front/web/package-lock.json` la marque `dev: true`, et elle n'est
   atteinte que par `jest-config`, c'est-à-dire par la commande `npm test`. Aucun
   chemin d'exécution de production ne la charge.
3. **Le vecteur d'attaque est hors de portée.** La vulnérabilité est une
   pollution de prototype dans `mergeObject()` : elle exige qu'un **objet source
   contrôlé par un attaquant** soit passé à `deepmerge`. Dans `jest-config`, les
   objets fusionnés sont les **fichiers de configuration Jest du dépôt**
   (`jest.config.*`, presets), c'est-à-dire du code déjà versionné et relu — pas
   une entrée utilisateur. Un attaquant devrait donc déjà pouvoir écrire dans le
   dépôt, auquel cas la pollution de prototype n'est pas le problème.
4. **Remplacer `jest-config` n'est pas faisable sans casser les tests.** Il n'est
   pas une dépendance directe : il est tiré par `jest` 30.5.2, qui est le runner
   de test du projet (`"test": "jest"` dans `front/web/package.json`). Le retirer
   imposerait de changer de runner de test — un lot de migration à part entière,
   sans rapport avec une alerte faible sur une dépendance de développement.

### Ce qui est fait à la place

- **Surveillance** : l'alerte reste **ouverte** volontairement. La fermer
  (dismiss) masquerait le fait qu'une version corrigée peut apparaître ; la
  laisser ouverte garantit que Dependabot ouvrira une PR **automatiquement** dès
  qu'un correctif sera publié.
- **Réévaluation** : à chaque revue hebdomadaire de Dependabot, et dès qu'une
  version > 4.3.1 est publiée. Si un correctif sort, la PR Dependabot le
  proposera et il sera mergé sans autre forme de procès.
- **Portée** : si `deepmerge` devenait un jour une dépendance de **production**
  (par exemple via un bundler ou un preset partagé), cette décision devrait être
  **révoquée immédiatement** — le risque passerait de théorique à réel.

### Décision

**Risque accepté**, faible, dev-only, sans correctif disponible, vecteur non
atteignable depuis une entrée non fiable. Aucune action de code. Réévaluation
automatique par Dependabot.

## Comment ajouter une entrée

Toute alerte qui reste ouverte après analyse doit avoir ici : son identifiant
d'avis, la version installée, la raison pour laquelle elle n'est pas corrigée
(pas de correctif / non atteignable / coût de remplacement disproportionné), et
la **condition de réévaluation**. Une alerte élevée ou critique ne peut pas être
« acceptée » sans décision explicite du propriétaire du dépôt.
