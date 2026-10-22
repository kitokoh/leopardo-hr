#!/usr/bin/env python3
"""Genere les entrees OpenAPI 3.0.3 des routes Farm OS T2.2 -> T6.2.

Script d'outillage de mission : il produit un bloc YAML insere avant
`components:` dans api/openapi.yaml. Il ne fait aucune supposition sur les
schemas de reponse (les controleurs renvoient des objets libres) : il documente
la route, ses parametres de chemin, son corps de requete et ses codes de
reponse, ce qui est exactement ce que verifie le garde de couverture.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

# (methode, chemin, tag, resume, corps_requis, proprietes_du_corps)
ROUTES: list[tuple[str, str, str, str, bool, list[tuple[str, str, str]]]] = [
    # ── T2.2 Materiel agricole ──
    ("get", "/farm/equipment", "FarmEquipment", "Liste du materiel agricole", False, []),
    ("post", "/farm/equipment", "FarmEquipment", "Enregistrer un materiel (saisie manuelle)", True, [
        ("name", "string", "Nom du materiel."),
        ("kind", "string", "Type de materiel (registre ferme)."),
        ("meter_type", "string", "Type de compteur suivi, ou `none`."),
        ("meter_value", "number", "Valeur de compteur DECLAREE (jamais lue automatiquement)."),
        ("status", "string", "Etat DECLARE par l'exploitant."),
    ]),
    ("get", "/farm/equipment/maintenance-due", "FarmEquipment", "Entretiens planifies arrives a echeance", False, []),
    ("get", "/farm/equipment/{equipment}", "FarmEquipment", "Detail d'un materiel", False, []),
    ("post", "/farm/equipment/{equipment}/usage", "FarmEquipment", "Relever un compteur (saisie manuelle)", True, [
        ("meter_value", "number", "Nouvelle valeur de compteur. Ne peut pas reculer."),
        ("meter_source", "string", "`manual` ou `imported` — jamais une telemetrie."),
        ("occurred_at", "string", "Horodatage du releve."),
        ("client_usage_id", "string", "Identifiant d'idempotence pour le rejeu hors ligne."),
    ]),
    ("post", "/farm/equipment/{equipment}/meter-reset", "FarmEquipment", "Remplacer un compteur (remise a zero)", True, [
        ("meter_value", "number", "Nouvelle valeur de reference du compteur."),
        ("reason", "string", "Motif du remplacement."),
    ]),
    ("post", "/farm/equipment/{equipment}/maintenances", "FarmEquipment", "Planifier un entretien", True, [
        ("maintenance_type", "string", "`preventive` ou `corrective`."),
        ("title", "string", "Intitule de l'entretien."),
        ("planned_at", "string", "Date planifiee."),
        ("cost_amount", "number", "Cout DECLARE. Laisser vide si inconnu (jamais zero de remplissage)."),
    ]),
    ("post", "/farm/equipment/{equipment}/breakdowns", "FarmEquipment", "Declarer une panne", True, [
        ("severity", "string", "Gravite DECLAREE par l'exploitant."),
        ("title", "string", "Intitule de la panne."),
        ("occurred_at", "string", "Date de survenue."),
    ]),
    ("post", "/farm/equipment/{equipment}/documents", "FarmEquipment", "Associer un document a un materiel", True, [
        ("document_type", "string", "Type de document (registre ferme)."),
        ("title", "string", "Intitule du document."),
        ("file_id", "integer", "Identifiant du fichier dans le service de fichiers EXISTANT."),
        ("external_url", "string", "Lien fourni par l'exploitant (jamais fabrique par Farm OS)."),
    ]),
    ("post", "/farm/equipment/maintenances/{maintenance}/complete", "FarmEquipment", "Cloturer un entretien", True, [
        ("performed_at", "string", "Date de realisation."),
        ("cost_amount", "number", "Cout reel DECLARE."),
    ]),
    ("post", "/farm/equipment/breakdowns/{breakdown}/resolve", "FarmEquipment", "Resoudre une panne", True, [
        ("resolved_at", "string", "Date de resolution."),
        ("cost_amount", "number", "Cout de reparation DECLARE."),
    ]),
    # ── T3.1 Elevage ──
    ("get", "/farm/livestock/batches", "FarmLivestock", "Liste des lots de cheptel", False, []),
    ("post", "/farm/livestock/batches", "FarmLivestock", "Creer un lot de cheptel (effectif initial zero)", True, [
        ("name", "string", "Nom du lot."),
        ("species", "string", "Espece (registre ferme)."),
        ("sex", "string", "Sexe, ou `unknown`."),
    ]),
    ("get", "/farm/livestock/batches/{batch}", "FarmLivestock", "Detail d'un lot de cheptel", False, []),
    ("post", "/farm/livestock/batches/{batch}/events", "FarmLivestock", "Enregistrer un mouvement d'effectif", True, [
        ("event_type", "string", "Type d'evenement : le SENS est porte par le type."),
        ("quantity", "integer", "Quantite toujours POSITIVE."),
        ("occurred_at", "string", "Date de l'evenement."),
        ("client_event_id", "string", "Identifiant d'idempotence pour le rejeu hors ligne."),
    ]),
    ("post", "/farm/livestock/batches/{batch}/health-events", "FarmLivestock", "Enregistrer un evenement sanitaire", True, [
        ("event_type", "string", "`vaccination`, `treatment`, `deworming`, `checkup`, `other`."),
        ("title", "string", "Intitule de l'evenement."),
        ("administered_at", "string", "Date d'administration."),
        ("next_due_at", "string", "Date du rappel, si declaree (aucun rappel n'est invente)."),
    ]),
    ("post", "/farm/livestock/batches/{batch}/reproduction-events", "FarmLivestock", "Enregistrer un evenement de reproduction", True, [
        ("event_type", "string", "`heat`, `mating`, `pregnancy_check`, `birth`, `abortion`, `drying_off`."),
        ("occurred_at", "string", "Date de l'evenement."),
        ("gestation_days", "integer", "Duree de gestation SAISIE. Sans elle, aucune date de mise bas n'est produite."),
    ]),
    ("post", "/farm/livestock/batches/{batch}/production", "FarmLivestock", "Enregistrer une production animale", True, [
        ("production_type", "string", "`milk`, `eggs`, `wool`, `honey`, `meat`, `manure`, `other`."),
        ("quantity", "number", "Quantite DECLAREE."),
        ("unit", "string", "Unite de saisie."),
        ("produced_at", "string", "Date de production."),
    ]),
    ("post", "/farm/livestock/batches/{batch}/feed", "FarmLivestock", "Enregistrer une distribution d'alimentation", True, [
        ("feed_name", "string", "Nom de l'aliment."),
        ("quantity", "number", "Quantite distribuee."),
        ("unit", "string", "Unite de saisie."),
        ("fed_at", "string", "Date de distribution."),
    ]),
    ("get", "/farm/livestock/animals", "FarmLivestock", "Liste des animaux identifies individuellement", False, []),
    ("post", "/farm/livestock/animals", "FarmLivestock", "Enregistrer un animal", True, [
        ("tag_number", "string", "Identifiant de l'animal."),
        ("species", "string", "Espece (registre ferme)."),
        ("sex", "string", "Sexe, ou `unknown`."),
        ("birth_date", "string", "Date de naissance, si connue (aucun age n'est invente)."),
        ("origin", "string", "`born`, `purchased`, `transferred`, `unknown`."),
    ]),
    ("get", "/farm/livestock/summary", "FarmLivestock", "Synthese du cheptel present", False, []),
    # ── T3.2 Recoltes et tracabilite ──
    ("get", "/farm/harvests/locations", "FarmHarvests", "Liste des emplacements de stockage", False, []),
    ("post", "/farm/harvests/locations", "FarmHarvests", "Creer un emplacement de stockage", True, [
        ("name", "string", "Nom de l'emplacement."),
        ("location_type", "string", "Type d'emplacement (registre ferme)."),
        ("capacity", "number", "Capacite DECLAREE, si connue."),
    ]),
    ("get", "/farm/harvests/lots", "FarmHarvests", "Liste des lots de recolte", False, []),
    ("post", "/farm/harvests/lots", "FarmHarvests", "Enregistrer une recolte (quantite figee)", True, [
        ("lot_code", "string", "Code de lot (tracabilite)."),
        ("quantity_harvested", "number", "Quantite recoltee. Ecrite UNE SEULE FOIS, jamais mise a jour."),
        ("unit", "string", "Unite de saisie."),
        ("harvested_at", "string", "Date de recolte."),
        ("client_harvest_id", "string", "Identifiant d'idempotence pour le rejeu hors ligne."),
    ]),
    ("get", "/farm/harvests/lots/{lot}", "FarmHarvests", "Detail d'un lot de recolte", False, []),
    ("post", "/farm/harvests/lots/{lot}/movements", "FarmHarvests", "Enregistrer un mouvement de stock", True, [
        ("movement_type", "string", "`in`, `transfer`, `loss`, `adjustment`, `sale`, `consumption`."),
        ("quantity", "number", "Quantite toujours POSITIVE. Une sortie ne peut pas depasser le stock restant."),
        ("sale_document_id", "integer", "Document du service de VENTES EXISTANT (obligatoire pour une vente)."),
        ("client_movement_id", "string", "Identifiant d'idempotence pour le rejeu hors ligne."),
    ]),
    ("get", "/farm/harvests/summary", "FarmHarvests", "Synthese du stock de recolte", False, []),
    # ── T4.1 Achats, ventes, depenses, rentabilite ──
    ("get", "/farm/finance/campaigns", "FarmFinance", "Liste des campagnes agricoles", False, []),
    ("post", "/farm/finance/campaigns", "FarmFinance", "Creer une campagne agricole", True, [
        ("name", "string", "Nom de la campagne."),
        ("start_year", "integer", "Annee de debut."),
    ]),
    ("get", "/farm/finance/expenses", "FarmFinance", "Liste des depenses enregistrees", False, []),
    ("post", "/farm/finance/expenses", "FarmFinance", "Enregistrer une depense", True, [
        ("category", "string", "Categorie de depense (registre ferme)."),
        ("label", "string", "Intitule de la depense."),
        ("amount", "number", "Montant DECLARE, strictement positif."),
        ("is_estimated", "boolean", "`true` si le montant est une estimation, pas une piece reelle."),
        ("spent_on", "string", "Date de la depense."),
    ]),
    ("get", "/farm/finance/sale-links", "FarmFinance", "Liste des ventes rattachees a un objet agricole", False, []),
    ("post", "/farm/finance/sale-links", "FarmFinance", "Rattacher un document de vente EXISTANT a un objet agricole", True, [
        ("sale_document_id", "integer", "Document du service de ventes existant. Farm OS n'en cree aucun."),
        ("amount", "number", "Montant impute a cet objet."),
        ("sold_on", "string", "Date de vente."),
        ("farm_harvest_lot_id", "integer", "Lot de recolte concerne (ou lot d'animaux)."),
    ]),
    ("get", "/farm/finance/profitability", "FarmFinance", "Rentabilite par axe (reel / estime / manquant)", False, []),
    ("get", "/farm/finance/profitability-by-campaign", "FarmFinance", "Rentabilite par campagne", False, []),
    # ── T5.1 Applications ──
    ("get", "/farm/apps/catalogue", "FarmApps", "Catalogue « Mes logiciels » (aucun faux lien)", False, []),
    ("get", "/farm/apps/roles/{role}/tools", "FarmApps", "Outils recommandes pour un profil, restreints aux outils actives", False, []),
    # ── T5.2 Mode hors ligne ──
    ("post", "/farm/sync/operations", "FarmSync", "Pousser un lot d'operations realisees hors ligne", True, [
        ("operations", "array", "Operations a synchroniser (1 a 200)."),
    ]),
    ("get", "/farm/sync/status", "FarmSync", "Etat de la file de synchronisation", False, []),
    ("get", "/farm/sync/errors", "FarmSync", "Historique des erreurs de synchronisation", False, []),
    ("get", "/farm/sync/conflicts", "FarmSync", "Conflits en attente d'arbitrage humain", False, []),
    ("post", "/farm/sync/conflicts/{conflict}/resolve", "FarmSync", "Resoudre explicitement un conflit", True, [
        ("strategy", "string", "`local_wins`, `server_wins` ou `manual_review`."),
        ("note", "string", "Motif de la decision (conserve pour audit)."),
    ]),
    # ── T6.1 Assistant agricole ──
    ("get", "/farm/assistant/context", "FarmAssistant", "Contexte interne de l'assistant (donnees autorisees uniquement)", False, []),
    ("get", "/farm/assistant/advisor-questions", "FarmAssistant", "Questions a poser a un conseiller agricole", False, []),
    # ── T6.2 Integrations externes ──
    ("get", "/farm/integrations", "FarmIntegrations", "Matrice des integrations externes (aucune API payante activee)", False, []),
]


def yaml_type(php_type: str) -> str:
    return {
        "string": "string",
        "integer": "integer",
        "number": "number",
        "boolean": "boolean",
        "array": "array",
    }[php_type]


def render() -> str:
    lines: list[str] = []

    # Une cle de chemin ne peut apparaitre qu'UNE fois : on regroupe donc les
    # methodes par chemin (GET + POST sur /farm/equipment, par exemple).
    grouped: dict[str, list[tuple[str, str, str, bool, list[tuple[str, str, str]]]]] = {}
    for method, path, tag, summary, has_body, props in ROUTES:
        grouped.setdefault(path, []).append((method, tag, summary, has_body, props))

    for path, entries in grouped.items():
        lines.append(f"  {path}:")

        for method, tag, summary, has_body, props in entries:
            lines.append(f"    {method}:")
            lines.append(f"      tags: [{tag}]")
            lines.append(f"      summary: {summary}")
            op_id = method + re.sub(r"[^A-Za-z0-9]+", " ", path).title().replace(" ", "")
            lines.append(f"      operationId: {op_id[0].lower()}{op_id[1:]}")
            lines.append("      security:")
            lines.append("        - BearerAuth: []")

            params = re.findall(r"\{(\w+)\}", path)
            if params:
                lines.append("      parameters:")
                for param in params:
                    lines.append(f"        - name: {param}")
                    lines.append("          in: path")
                    lines.append("          required: true")
                    lines.append("          schema: {type: integer}")
                    lines.append("          description: Identifiant interne de la ressource.")

            if has_body:
                lines.append("      requestBody:")
                lines.append("        required: true")
                lines.append("        content:")
                lines.append("          application/json:")
                lines.append("            schema:")
                lines.append('              type: "object"')
                lines.append("              properties:")
                for name, kind, description in props:
                    lines.append(f"                {name}:")
                    lines.append(f"                  type: \"{yaml_type(kind)}\"")
                    # Les descriptions sont TOUJOURS quotees : certaines
                    # contiennent « : » ou des accents, qui casseraient le YAML.
                    safe = description.replace('"', '\\"')
                    lines.append(f'                  description: "{safe}"')

            lines.append("      responses:")
            lines.append("        '200':")
            lines.append("          description: Succes.")
            lines.append("          content:")
            lines.append("            application/json:")
            lines.append("              schema: {type: object}")
            lines.append("        '401':")
            lines.append("          description: Non authentifie.")
            lines.append("        '403':")
            lines.append("          description: Module Farm OS non active, ou outil metier non active.")
            if has_body:
                lines.append("        '422':")
                lines.append("          description: Donnees invalides ou operation refusee (code d'erreur stable).")

    return "\n".join(lines) + "\n"


def main() -> int:
    target = Path(__file__).resolve().parents[1] / "openapi.yaml"
    text = target.read_text(encoding="utf-8")

    marker = "\ncomponents:\n"
    if marker not in text:
        print("ERREUR: marqueur 'components:' introuvable", file=sys.stderr)
        return 1

    block = render()

    # Idempotence : on retire un eventuel bloc precedent.
    start = text.find("  /farm/equipment:\n")
    if start != -1:
        end = text.find(marker, start)
        text = text[:start] + text[end + 1 :]

    idx = text.find(marker)
    text = text[:idx] + "\n" + block + text[idx + 1 :]

    target.write_text(text, encoding="utf-8")
    print(f"{len(ROUTES)} routes Farm OS documentees.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
