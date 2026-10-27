#!/usr/bin/env python3
"""Insertion idempotente du bloc OpenAPI du locatif (HOSP-005, #7947).

Le fichier `api/openapi.yaml` fait ~72 000 lignes : on n'y écrit pas à la main.
Ce script insère le bloc des 8 routes locatives juste avant
`/public/hospitality/properties/{slug}`, entre deux marqueurs explicites, et
**refuse de doubler** le bloc si les marqueurs sont déjà présents.

Usage : python3 dev-hub/tools/insert-7947-openapi.py [--check]
  --check : n'écrit rien, retourne 1 si le bloc est absent (utilisé en CI)
"""

from __future__ import annotations

import sys
from pathlib import Path

BEGIN = "  # >>> BEGIN HOSP-005 (#7947) rentals — bloc généré, ne pas éditer à la main"
END = "  # <<< END HOSP-005 (#7947) rentals"

ANCHOR = "  /public/hospitality/properties/{slug}:\n"

BLOCK = """\
  # >>> BEGIN HOSP-005 (#7947) rentals — bloc généré, ne pas éditer à la main
  /hospitality/properties/{property}/leases:
    get:
      operationId: api_get_hospitality_properties_by_property_leases
      tags:
      - Hospitality
      summary: Baux locatifs d'un établissement
      description: >-
        HOSP-005 (#7947, BC-32) — baux de l'établissement, triés par date de
        début décroissante. Filtre `status` optionnel (active|ended|terminated).
        Tenant re-vérifié (404 fail-closed).
      security:
      - BearerAuth: []
      parameters:
      - name: property
        in: path
        required: true
        schema:
          type: integer
      - name: status
        in: query
        required: false
        schema:
          type: string
          enum: [active, ended, terminated]
      - name: page
        in: query
        required: false
        schema:
          type: integer
          minimum: 1
      - name: per_page
        in: query
        required: false
        schema:
          type: integer
          minimum: 1
          maximum: 100
      responses:
        '200':
          description: Baux de l'établissement
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/HospitalityLease'
                  meta:
                    $ref: '#/components/schemas/PaginationMeta'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
    post:
      operationId: api_post_hospitality_properties_by_property_leases
      tags:
      - Hospitality
      summary: Créer un bail locatif
      description: >-
        HOSP-005 (#7947) — l'unité doit appartenir au MÊME établissement et au
        même tenant (422 sinon) : un bail qui référencerait l'unité d'un autre
        bien ferait fuiter son occupation. `billing_day` est borné à 1..31 puis
        RAMENÉ au dernier jour du mois quand il n'existe pas (février).
        Un seul bail `active` par unité (index unique partiel).
      security:
      - BearerAuth: []
      parameters:
      - name: property
        in: path
        required: true
        schema:
          type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [unit_id, tenant_name, starts_on, monthly_rent_minor, currency]
              properties:
                unit_id:
                  type: integer
                tenant_name:
                  type: string
                  maxLength: 150
                contact_email:
                  type: string
                  format: email
                  nullable: true
                contact_phone:
                  type: string
                  maxLength: 40
                  nullable: true
                starts_on:
                  type: string
                  format: date
                ends_on:
                  type: string
                  format: date
                  nullable: true
                monthly_rent_minor:
                  type: integer
                  minimum: 0
                currency:
                  type: string
                  minLength: 3
                  maxLength: 3
                billing_day:
                  type: integer
                  minimum: 1
                  maximum: 31
                deposit_minor:
                  type: integer
                  minimum: 0
                  nullable: true
                notes:
                  type: string
                  maxLength: 2000
                  nullable: true
      responses:
        '201':
          description: Bail créé (statut `active`)
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    $ref: '#/components/schemas/HospitalityLease'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '422':
          $ref: '#/components/responses/Error422'

  /hospitality/leases/{lease}:
    get:
      operationId: api_get_hospitality_leases_by_lease
      tags:
      - Hospitality
      summary: Détail d'un bail
      description: HOSP-005 (#7947) — 404 fail-closed si le bail appartient à un autre tenant.
      security:
      - BearerAuth: []
      parameters:
      - name: lease
        in: path
        required: true
        schema:
          type: integer
      responses:
        '200':
          description: Bail
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    $ref: '#/components/schemas/HospitalityLease'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
    patch:
      operationId: api_patch_hospitality_leases_by_lease
      tags:
      - Hospitality
      summary: Modifier un bail
      description: >-
        HOSP-005 (#7947) — 422 sur un bail TERMINAL : ses conditions sont figées
        par les échéances déjà émises, les réécrire les rendrait incohérentes.
      security:
      - BearerAuth: []
      parameters:
      - name: lease
        in: path
        required: true
        schema:
          type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                tenant_name:
                  type: string
                  maxLength: 150
                contact_email:
                  type: string
                  format: email
                  nullable: true
                contact_phone:
                  type: string
                  maxLength: 40
                  nullable: true
                ends_on:
                  type: string
                  format: date
                  nullable: true
                monthly_rent_minor:
                  type: integer
                  minimum: 0
                billing_day:
                  type: integer
                  minimum: 1
                  maximum: 31
                notes:
                  type: string
                  maxLength: 2000
                  nullable: true
      responses:
        '200':
          description: Bail mis à jour
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    $ref: '#/components/schemas/HospitalityLease'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '422':
          $ref: '#/components/responses/Error422'

  /hospitality/leases/{lease}/terminate:
    post:
      operationId: api_post_hospitality_leases_by_lease_terminate
      tags:
      - Hospitality
      summary: Résilier ou clôturer un bail
      description: >-
        HOSP-005 (#7947) — transition gardée : `active` → `ended` (terme
        naturel) ou `terminated` (résiliation). 409 INVALID_LEASE_TRANSITION
        sur un bail déjà terminal. Aucune route DELETE : un bail se résilie,
        l'historique locatif et les échéances émises restent lisibles.
      security:
      - BearerAuth: []
      parameters:
      - name: lease
        in: path
        required: true
        schema:
          type: integer
      requestBody:
        required: false
        content:
          application/json:
            schema:
              type: object
              properties:
                status:
                  type: string
                  enum: [ended, terminated]
                  default: terminated
                terminated_on:
                  type: string
                  format: date
                  nullable: true
      responses:
        '200':
          description: Bail clôturé
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    $ref: '#/components/schemas/HospitalityLease'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '409':
          description: Transition de bail interdite (INVALID_LEASE_TRANSITION)
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorEnvelope'

  /hospitality/leases/{lease}/generate-periods:
    post:
      operationId: api_post_hospitality_leases_by_lease_generate_periods
      tags:
      - Hospitality
      summary: Générer les échéances de loyer
      description: >-
        HOSP-005 (#7947) — génère les échéances `due` du début du bail à la
        période `until` incluse. IDEMPOTENT : les périodes déjà présentes sont
        laissées INTACTES (ni doublon, ni réécriture d'un montant déjà payé) ;
        `skipped` les compte. La génération s'arrête à `ends_on` quand le bail
        a une fin : facturer un logement qu'on n'occupe plus fabrique un impayé.
      security:
      - BearerAuth: []
      parameters:
      - name: lease
        in: path
        required: true
        schema:
          type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [until]
              properties:
                until:
                  type: string
                  pattern: '^\\d{4}-(0[1-9]|1[0-2])$'
                  example: '2026-06'
      responses:
        '200':
          description: Compteurs de génération
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: object
                    properties:
                      lease_id:
                        type: integer
                      until:
                        type: string
                      created:
                        type: integer
                      skipped:
                        type: integer
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '422':
          $ref: '#/components/responses/Error422'

  /hospitality/rent-payments:
    get:
      operationId: api_get_hospitality_rent_payments
      tags:
      - Hospitality
      summary: Échéances de loyer
      description: >-
        HOSP-005 (#7947) — filtres `lease_id`, `status` (due|partial|paid|late)
        et `period` (YYYY-MM). Le statut `late` est DÉRIVÉ et recalculé à la
        lecture : sans cela un impayé dont la date vient de passer resterait
        affiché `due` jusqu'au prochain passage d'une tâche planifiée.
      security:
      - BearerAuth: []
      parameters:
      - name: lease_id
        in: query
        required: false
        schema:
          type: integer
      - name: status
        in: query
        required: false
        schema:
          type: string
          enum: [due, partial, paid, late]
      - name: period
        in: query
        required: false
        schema:
          type: string
          pattern: '^\\d{4}-(0[1-9]|1[0-2])$'
      - name: page
        in: query
        required: false
        schema:
          type: integer
          minimum: 1
      - name: per_page
        in: query
        required: false
        schema:
          type: integer
          minimum: 1
          maximum: 100
      responses:
        '200':
          description: Échéances
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/HospitalityRentPayment'
                  meta:
                    $ref: '#/components/schemas/PaginationMeta'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'

  /hospitality/rent-payments/{payment}/record:
    post:
      operationId: api_post_hospitality_rent_payments_by_payment_record
      tags:
      - Hospitality
      summary: Encaisser une échéance de loyer
      description: >-
        HOSP-005 (#7947) — `amount_minor` est le montant encaissé MAINTENANT
        (il s'ajoute au cumul, ce n'est pas le total). 422
        RENT_AMOUNT_EXCEEDS_OUTSTANDING si le montant dépasse le reste dû
        (refusé, jamais tronqué : un crédit créé serait irrécupérable) ;
        422 RENT_ALREADY_SETTLED sur une échéance soldée.
      security:
      - BearerAuth: []
      parameters:
      - name: payment
        in: path
        required: true
        schema:
          type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [amount_minor]
              properties:
                amount_minor:
                  type: integer
                  minimum: 1
                paid_at:
                  type: string
                  format: date
                  nullable: true
                payment_method:
                  type: string
                  enum: [cash, transfer, card, cheque, mobile_money]
                  nullable: true
                reference:
                  type: string
                  maxLength: 60
                  nullable: true
                notes:
                  type: string
                  maxLength: 2000
                  nullable: true
      responses:
        '200':
          description: Échéance après encaissement
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    $ref: '#/components/schemas/HospitalityRentPayment'
        '401':
          $ref: '#/components/responses/Error401'
        '403':
          $ref: '#/components/responses/Error403'
        '404':
          $ref: '#/components/responses/Error404'
        '422':
          $ref: '#/components/responses/Error422'
  # <<< END HOSP-005 (#7947) rentals

"""


def main() -> int:
    check = "--check" in sys.argv
    root = Path(__file__).resolve().parents[2]
    target = root / "api" / "openapi.yaml"

    if not target.exists():
        print(f"::error::openapi.yaml introuvable : {target}", file=sys.stderr)
        return 1

    text = target.read_text(encoding="utf-8")
    present = BEGIN in text and END in text

    if check:
        if present:
            print("✅ Bloc OpenAPI HOSP-005 (#7947) présent.")
            return 0
        print("::error::Bloc OpenAPI HOSP-005 (#7947) absent.", file=sys.stderr)
        return 1

    if present:
        print("✅ Bloc déjà présent — rien à faire (idempotent).")
        return 0

    if ANCHOR not in text:
        print(f"::error::Ancre introuvable : {ANCHOR.strip()}", file=sys.stderr)
        return 1

    text = text.replace(ANCHOR, BLOCK + ANCHOR, 1)
    target.write_text(text, encoding="utf-8")
    print("✅ Bloc OpenAPI HOSP-005 (#7947) inséré (8 routes).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
