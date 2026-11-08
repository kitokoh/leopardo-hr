#!/usr/bin/env python3
"""Cree les 6 issues BC-10 sur GitHub depuis le contenu de
docs/specifications/BC10_PORTAL_PUBLIC_ISSUES.md.

Usage: python3 scripts/create_bc10_issues.py
"""
import json
import os
import sys
import urllib.request
import urllib.error

TOKEN = os.environ.get("GH_TOKEN", "")
REPO = "kitokoh/leopardo-hr"
API = f"https://api.github.com/repos/{REPO}/issues"

COMMON_LABELS = ["BC-10 RECRUITMENT", "type/feature"]

ISSUES = [
    {
        "title": "BC-10 — Publication d'une offre par le tenant vers le portail public",
        "labels": COMMON_LABELS + ["backend", "public-api"],
        "body": """## Contexte

Le module ATS (BC-10) savait créer une offre (`draft`) mais le cycle de vie
`draft → published → closed → archived` n'était pas exposé de bout en bout :
rien ne garantissait qu'une offre `draft` ne fuite pas sur le portail public, et
`published_at` / `closes_at` n'étaient pas posés.

## Travail

- `PublishJobPostingAction` : transition gardée, `published_at` posé, refus
  explicite d'une transition invalide (`JobPostingStateTransitionException`).
- `CloseJobPostingAction` / `ReopenJobPostingAction` / `ArchiveJobPostingAction`.
- Scope `published()` sur `JobPosting` : le portail public ne lit QUE ce scope.
- Migration tenant : `published_at`, `closes_at`, index de lecture publique.

## Critères d'acceptation

- [x] Une offre `draft` n'apparaît jamais sur le portail public (404 sur le détail).
- [x] `publish` pose `published_at` et rend l'offre visible.
- [x] `close` la retire immédiatement du portail et du flux XML.
- [x] Une transition invalide renvoie 409, pas 500.
- [x] Une offre dont `closes_at` est passé n'est plus listée.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
    {
        "title": "BC-10 — Publication d'une offre depuis l'espace super admin",
        "labels": COMMON_LABELS + ["backend", "admin"],
        "body": """## Contexte

La plateforme doit pouvoir publier une offre pour le compte d'une entreprise
cliente (onboarding, campagne, offre interne) sans se connecter au tenant.

## Travail

- `PlatformJobPostingController` (`index`, `store`, `publish`, `close`, `offers`).
- `PublishPlatformJobPostingAction` : résout le tenant cible par `companyId`,
  exécute la publication DANS le contexte du tenant (`withinTenant`).
- Routes `platform/recruitment/*` sous `auth:super_admin_api` +
  `platform.permission:recruitment.view|manage`.
- Audit de chaque geste plateforme.

## Critères d'acceptation

- [x] Un super admin publie une offre pour un tenant donné.
- [x] Un token employé (guard `employee`) reçoit 401 sur ces routes.
- [x] `recruitment.view` suffit pour lire, `recruitment.manage` requis pour écrire.
- [x] L'offre publiée apparaît sur le portail public du tenant.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
    {
        "title": "BC-10 — Intégration d'API externes d'offres (agrégateurs)",
        "labels": COMMON_LABELS + ["backend", "public-api", "infrastructure"],
        "body": """## Contexte

Les agrégateurs (Indeed, LinkedIn, Glassdoor, Adzuna, Jooble, flux personnalisé)
doivent alimenter notre portail : la plateforme configure un connecteur,
synchronise, et les offres importées apparaissent sur le portail.

## Travail

- Tables PUBLIQUES `job_sources` et `external_job_offers` (une offre importée
  n'appartient à aucun tenant) + modèles `JobSource`, `ExternalJobOffer`.
- `JobSourceLifecycle` : `configure` / `test` / `revoke`, secrets chiffrés
  (`credentials_encrypted`), audit, statuts `not_connected|connected|error`.
- `JobBoardClient` (interface) + `JobBoardClientRegistry` + implémentations :
  `CustomFeedJobBoardClient` (flux XML/RSS réellement câblé) et
  `NotWiredJobBoardClient` (fournisseurs sans API publique ouverte).
- `SyncExternalJobOffers` : upsert idempotent sur `(source_id, external_id)`,
  clôture des offres disparues, comptage, capture d'erreur non bloquante.
- `SyncExternalJobOffersJob` pour les gros volumes.
- `PlatformJobSourceController` : `index`, `configure`, `test`, `sync`,
  `queueSync`, `revoke`.

## Critères d'acceptation

- [x] Configurer un connecteur enregistre les secrets chiffrés, jamais en clair.
- [x] `test` ne prétend PAS avoir vérifié l'accès fournisseur quand il ne l'a pas fait.
- [x] Une synchronisation répétée n'importe pas deux fois la même offre (idempotence).
- [x] Une offre disparue de la source est clôturée, pas supprimée.
- [x] Une panne fournisseur met le connecteur en `error` sans casser le portail.
- [x] Les offres importées apparaissent sur le portail public.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
    {
        "title": "BC-10 — Portail public : liste, détail, candidature, flux XML, branding",
        "labels": COMMON_LABELS + ["backend", "frontend", "public-api", "public"],
        "body": """## Contexte

Le portail existait mais n'était pas exploitable : pas d'offres agrégées, pas de
filtres, pas de consentement, et le flux XML ne portait pas les champs exigés par
Google Jobs / Indeed.

## Travail

- `PublicJobCatalog` : fusion explicite des offres tenant (schéma tenant) et des
  offres agrégées (schéma public), rattachées par PAYS — jamais de jointure
  cross-schéma implicite.
- `PublicCareerController` : `index` (filtres `q`, `location`, `contract_type`,
  `remote_policy`), `show`, `feed`.
- Flux XML : `title`, `description`, `date`, `expiration_date`, `location`,
  `company`, `job_type`, `salary`, `apply_url`, échappement XML correct.
- Front : liste séparant offres du tenant et offres partenaires, filtres GET
  sans JS, détail, formulaire de candidature, branding par tenant, i18n fr/en/tr/ar.

## Critères d'acceptation

- [x] `GET /api/v1/public/careers/{slug}` liste les offres publiées, sans auth.
- [x] Les filtres restreignent réellement la liste.
- [x] Le détail d'une offre `draft`/`closed` renvoie 404.
- [x] Le flux XML est bien formé et porte les champs Google Jobs.
- [x] Le branding du tenant (logo, couleur) est appliqué.
- [x] Une offre agrégée renvoie vers le partenaire, pas vers notre formulaire.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
    {
        "title": "BC-10 — Durcissement : RGPD, rate limiting, anti-spam, erreurs",
        "labels": COMMON_LABELS + ["backend", "hardening", "legal"],
        "body": """## Contexte

Le portail est public et non authentifié : il collecte des données personnelles
(CV, coordonnées) et constitue une surface d'abus.

## Travail

- Consentement RGPD obligatoire à la candidature (422 sans consentement),
  horodaté, avec finalité et durée annoncées.
- Champ piège anti-spam (`website`, `prohibited`) : refus silencieux, sans
  révéler le mécanisme.
- Rate limiting à deux buckets : `public-careers` (lecture) et
  `public-careers-apply` (écriture, plus strict).
- Champs candidat exposés limités au strict nécessaire ; pas de fuite de
  `raw_payload` ni des secrets de connecteur.
- Gestion d'erreurs : 404 pour une ressource absente, 409 pour une transition
  invalide, 422 pour une validation, jamais de 500 sur un cas métier.

## Critères d'acceptation

- [x] Une candidature sans consentement est refusée en 422.
- [x] Un bot qui remplit le champ piège est refusé.
- [x] Le bucket d'écriture est plus strict que celui de lecture.
- [x] Aucun secret de connecteur n'est renvoyé par l'API.
- [x] Les cas métier ne produisent pas de 500.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
    {
        "title": "BC-10 — Écrans super admin (connecteurs + offres importées)",
        "labels": COMMON_LABELS + ["frontend", "admin", "ui"],
        "body": """## Travail

- `JobSourcesView.vue` : configurer / tester / synchroniser / révoquer, avec
  l'état affiché honnêtement (`not_connected` est un état de premier rang).
- `ImportedOffersView.vue` : les offres réellement importées, avec provenance —
  le contrôle qui rend visible un connecteur « connecté » sans offre.
- Routes sous `recruitment.view`.

## Critères d'acceptation

- [x] Aucun secret n'est pré-rempli dans le formulaire.
- [x] Le message de test relaie le verdict du serveur.
- [x] Un connecteur sans offre importée est visiblement distinguable.

## Références

- Spec : `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`
- Branche : `bc/bc10-portail-public-offres`
""",
    },
]


def api(method, url, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Authorization", f"token {TOKEN}")
    req.add_header("Accept", "application/vnd.github+json")
    if data:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode() or "{}")


def main():
    if not TOKEN:
        print("GH_TOKEN manquant", file=sys.stderr)
        return 1
    created = []
    for spec in ISSUES:
        status, body = api("POST", API, spec)
        if status == 201:
            print(f"OK   #{body['number']}  {body['title']}")
            print(f"     {body['html_url']}")
            created.append((body["number"], body["html_url"], body["title"]))
        else:
            print(f"FAIL {status}  {spec['title']}")
            print(f"     {body.get('message')}")
    print(f"\n{len(created)}/{len(ISSUES)} issues creees")
    with open("bc10_issues_created.json", "w") as f:
        json.dump(created, f, indent=2, ensure_ascii=False)
    return 0 if len(created) == len(ISSUES) else 1


if __name__ == "__main__":
    sys.exit(main())
