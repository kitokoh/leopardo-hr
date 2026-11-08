#!/usr/bin/env python3
"""Insere les 13 routes BC-10 dans api/openapi.yaml.

Deux insertions, reperees par leur ancre textuelle (pas par numero de ligne,
qui bouge apres la premiere insertion) :

1. `/recruitment/jobs/{jobPosting}/archive` et `/reopen` juste avant
   `/recruitment/jobs/{jobPosting}/applicants`.
2. Les 11 routes `/v1/platform/recruitment/*` juste avant `/farm/settings`.

Usage: python3 scripts/insert_bc10_openapi.py
"""
import sys

PATH = "api/openapi.yaml"

ARCHIVE_REOPEN = """  /recruitment/jobs/{jobPosting}/archive:
    post:
      operationId: api_post_recruitment_jobs_by_jobPosting_archive
      tags:
      - Recruitment
      summary: Archiver une offre
      description: |
        BC-10 (#9197) - transition terminale du cycle de vie. Une offre archivee
        n'est plus listee sur le portail public ni dans le flux XML, et ne peut
        plus etre republiee directement.
      parameters:
      - name: jobPosting
        in: path
        required: true
        schema:
          type: integer
      responses:
        '200':
          description: Offre archivee
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '409':
          description: Transition invalide depuis le statut courant
  /recruitment/jobs/{jobPosting}/reopen:
    post:
      operationId: api_post_recruitment_jobs_by_jobPosting_reopen
      tags:
      - Recruitment
      summary: Reouvrir une offre cloturee
      description: |
        BC-10 (#9197) - repasse une offre `closed` en `published` et repose
        `published_at`. Refusee en 409 si l'offre est `archived`.
      parameters:
      - name: jobPosting
        in: path
        required: true
        schema:
          type: integer
      responses:
        '200':
          description: Offre reouverte
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '409':
          description: Transition invalide depuis le statut courant
"""

PLATFORM = """  # --- BC-10 (#9198/#9199) - portail public d'offres : gestes plateforme -----
  # Publication d'une offre pour le compte d'un tenant et configuration des
  # connecteurs d'agregateurs. Toutes ces routes sont sous le guard
  # `auth:super_admin_api` : un token employe recoit 401, pas 403.
  /v1/platform/recruitment/jobs:
    get:
      operationId: api_get_v1_platform_recruitment_jobs
      tags: [Recruitment]
      summary: Lister les offres publiees par la plateforme
      description: |
        BC-10 (#9198) - vue plateforme de toutes les offres, tous tenants
        confondus. Permission requise : `recruitment.view`.
      security:
        - BearerAuth: []
      parameters:
        - name: company_id
          in: query
          schema:
            type: string
        - name: status
          in: query
          schema:
            type: string
            enum: [draft, published, closed, archived]
      responses:
        '200':
          description: Liste des offres
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
    post:
      operationId: api_post_v1_platform_recruitment_jobs
      tags: [Recruitment]
      summary: Creer une offre pour le compte d'un tenant
      description: |
        BC-10 (#9198) - la plateforme cree l'offre DANS le contexte du tenant
        cible (`withinTenant`). Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [company_id, title]
              properties:
                company_id:
                  type: string
                title:
                  type: string
                  maxLength: 255
                description:
                  type: string
                  nullable: true
                location:
                  type: string
                  nullable: true
                remote_policy:
                  type: string
                  enum: [onsite, hybrid, remote]
                contract_type:
                  type: string
                  enum: [cdi, cdd, stage, freelance]
                salary_range_min:
                  type: integer
                  nullable: true
                salary_range_max:
                  type: integer
                  nullable: true
                currency:
                  type: string
                  nullable: true
                skills_required:
                  type: array
                  items:
                    type: string
      responses:
        '201':
          description: Offre creee
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '422':
          description: Donnees invalides
  /v1/platform/recruitment/jobs/{companyId}/{jobPosting}/publish:
    post:
      operationId: api_post_v1_platform_recruitment_jobs_publish
      tags: [Recruitment]
      summary: Publier une offre pour le compte d'un tenant
      description: |
        BC-10 (#9198) - pose `published_at` et rend l'offre visible sur le
        portail public du tenant. Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      parameters:
        - name: companyId
          in: path
          required: true
          schema:
            type: string
        - name: jobPosting
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Offre publiee
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '409':
          description: Transition invalide depuis le statut courant
  /v1/platform/recruitment/jobs/{companyId}/{jobPosting}/close:
    post:
      operationId: api_post_v1_platform_recruitment_jobs_close
      tags: [Recruitment]
      summary: Cloturer une offre pour le compte d'un tenant
      description: |
        BC-10 (#9198) - retire immediatement l'offre du portail public et du
        flux XML. Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      parameters:
        - name: companyId
          in: path
          required: true
          schema:
            type: string
        - name: jobPosting
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Offre cloturee
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '409':
          description: Transition invalide depuis le statut courant
  /v1/platform/recruitment/offers:
    get:
      operationId: api_get_v1_platform_recruitment_offers
      tags: [Recruitment]
      summary: Lister les offres importees des agregateurs
      description: |
        BC-10 (#9199) - offres reellement importees, avec leur provenance.
        C'est le controle qui rend visible un connecteur "connecte" qui ne
        remonte aucune offre. Permission requise : `recruitment.view`.
      security:
        - BearerAuth: []
      parameters:
        - name: source_id
          in: query
          schema:
            type: integer
        - name: country
          in: query
          schema:
            type: string
      responses:
        '200':
          description: Liste des offres importees
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
  /v1/platform/recruitment/sources:
    get:
      operationId: api_get_v1_platform_recruitment_sources
      tags: [Recruitment]
      summary: Etat des connecteurs d'agregateurs
      description: |
        BC-10 (#9199) - etat de chaque connecteur (`not_connected`,
        `connected`, `error`). Les secrets ne sont JAMAIS exposes : seul un
        booleen `has_credentials` indique qu'une configuration existe.
        Permission requise : `recruitment.view`.
      security:
        - BearerAuth: []
      responses:
        '200':
          description: Etat des connecteurs
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
  /v1/platform/recruitment/sources/{provider}:
    parameters:
      - name: provider
        in: path
        required: true
        schema:
          type: string
          enum: [indeed, linkedin, glassdoor, adzuna, jooble, custom_feed]
    put:
      operationId: api_put_v1_platform_recruitment_sources
      tags: [Recruitment]
      summary: Configurer un connecteur d'agregateur
      description: |
        BC-10 (#9199) - enregistre les acces du fournisseur. Les secrets sont
        chiffres au repos (`credentials_encrypted`) et ne sont jamais renvoyes
        en clair. Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                label:
                  type: string
                  nullable: true
                feed_url:
                  type: string
                  nullable: true
                  description: Obligatoire pour le fournisseur `custom_feed`.
                credentials:
                  type: object
                  additionalProperties: true
                  description: Chiffre au repos, jamais renvoye par l'API.
                country:
                  type: string
                  nullable: true
      responses:
        '200':
          description: Connecteur configure
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '422':
          description: Donnees invalides
    delete:
      operationId: api_delete_v1_platform_recruitment_sources
      tags: [Recruitment]
      summary: Revoquer un connecteur d'agregateur
      description: |
        BC-10 (#9199) - supprime la configuration et les secrets. Les offres
        deja importees sont conservees mais ne sont plus rafraichies.
        Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      responses:
        '200':
          description: Connecteur revoque
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
  /v1/platform/recruitment/sources/{provider}/test:
    post:
      operationId: api_post_v1_platform_recruitment_sources_test
      tags: [Recruitment]
      summary: Tester l'acces a un connecteur
      description: |
        BC-10 (#9199) - le verdict renvoye est celui du serveur. Un fournisseur
        sans API publique ouverte repond honnetement qu'il n'est pas cable
        plutot que de pretendre avoir verifie l'acces. Permission requise :
        `recruitment.manage`.
      security:
        - BearerAuth: []
      parameters:
        - name: provider
          in: path
          required: true
          schema:
            type: string
            enum: [indeed, linkedin, glassdoor, adzuna, jooble, custom_feed]
      responses:
        '200':
          description: Verdict du test
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
  /v1/platform/recruitment/sources/{provider}/sync:
    post:
      operationId: api_post_v1_platform_recruitment_sources_sync
      tags: [Recruitment]
      summary: Synchroniser un connecteur (synchrone)
      description: |
        BC-10 (#9199) - import synchrone : l'ecran affiche le resultat reel.
        L'upsert est idempotent sur `(source_id, external_id)` : une seconde
        execution n'importe pas deux fois la meme offre. Permission requise :
        `recruitment.manage`.
      security:
        - BearerAuth: []
      parameters:
        - name: provider
          in: path
          required: true
          schema:
            type: string
            enum: [indeed, linkedin, glassdoor, adzuna, jooble, custom_feed]
      responses:
        '200':
          description: Resultat de la synchronisation
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
  /v1/platform/recruitment/sources/{provider}/queue-sync:
    post:
      operationId: api_post_v1_platform_recruitment_sources_queue_sync
      tags: [Recruitment]
      summary: Synchroniser un connecteur (en file)
      description: |
        BC-10 (#9199) - variante asynchrone pour les gros volumes : la
        synchronisation est empilee et le portail n'attend pas le fournisseur.
        Permission requise : `recruitment.manage`.
      security:
        - BearerAuth: []
      parameters:
        - name: provider
          in: path
          required: true
          schema:
            type: string
            enum: [indeed, linkedin, glassdoor, adzuna, jooble, custom_feed]
      responses:
        '202':
          description: Synchronisation empilee
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'

"""


def main() -> int:
    with open(PATH, encoding="utf-8") as fh:
        lines = fh.readlines()

    # --- insertion 1 : archive + reopen avant /recruitment/jobs/{jobPosting}/applicants
    anchor1 = "  /recruitment/jobs/{jobPosting}/applicants:\n"
    idx1 = [i for i, l in enumerate(lines) if l == anchor1]
    if len(idx1) != 1:
        print(f"ancre 1 introuvable ou ambigue ({len(idx1)} occurrences)", file=sys.stderr)
        return 1
    lines[idx1[0]:idx1[0]] = [ARCHIVE_REOPEN]

    # --- insertion 2 : les 11 routes plateforme avant /farm/settings
    anchor2 = "  /farm/settings:\n"
    idx2 = [i for i, l in enumerate(lines) if l == anchor2]
    if len(idx2) != 1:
        print(f"ancre 2 introuvable ou ambigue ({len(idx2)} occurrences)", file=sys.stderr)
        return 1
    lines[idx2[0]:idx2[0]] = [PLATFORM]

    with open(PATH, "w", encoding="utf-8") as fh:
        fh.writelines(lines)

    print(f"OK - {len(lines)} lignes")
    return 0


if __name__ == "__main__":
    sys.exit(main())
