#!/usr/bin/env python3
"""Mise a jour du statut de production d'un script video.

Outil de l'equipe production : il ecrit dans `statuts-tournage.json` (source de
verite du suivi), jamais dans le HTML genere. Le site est regenere au prochain
deploiement par `build_marketing.py`.

Chaque changement ajoute une entree horodatee dans l'historique du script : le
portail affiche la frise des 4 etapes et l'historique complet, donc un statut
n'est jamais ecrase sans trace.

Usage
-----
    # un script
    python3 set_statut.py VID-05 tourne --obs "Rushes tournes le 2026-12-02, salle du Soir"

    # plusieurs scripts d'un coup (meme statut, meme observation)
    python3 set_statut.py VID-05 VID-06 VID-07 tourne --obs "Vague V2 tournee en 2 jours"

    # toute une vague du calendrier
    python3 set_statut.py --vague V2 tourne --obs "Vague V2 tournee"

    # etat courant
    python3 set_statut.py --etat

    # simulation, n'ecrit rien
    python3 set_statut.py VID-05 monte --obs "Montage termine" --dry-run

Statuts valides : a_ecrire, tourne, monte, publie (definis dans le _meta du
fichier, jamais codes en dur ici).

Regle de progression : on avance d'une etape a la fois. Un saut en avant est
refuse sauf `--force`, pour eviter un statut « publie » sur un script jamais
tourne. Un retour en arriere reste possible (reprise, rush perdu) : il est
enregistre comme tel dans l'historique.
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
STATUS_FILE = HERE / "statuts-tournage.json"
DATA_FILE = HERE / "scripts-video.json"

DEFAULT_AUTHOR = "Equipe production"

# Codes de statut connus, utilises pour separer « ids + statut » sur la ligne de
# commande. La liste qui fait foi reste celle du _meta de statuts-tournage.json.
STATUS_CODES = ("a_ecrire", "tourne", "monte", "publie")


def load_json(path: Path) -> dict:
    if not path.exists():
        sys.exit(f"ERREUR : fichier introuvable — {path}")
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        sys.exit(f"ERREUR : JSON invalide dans {path.name} — {exc}")


def status_defs(doc: dict) -> tuple[list[str], dict]:
    defs = (doc.get("_meta") or {}).get("statuts") or []
    order = [d["code"] for d in defs]
    return order, {d["code"]: d for d in defs}


def resolve_targets(args, data: dict) -> list[str]:
    """IDs cibles : explicites, ou tous les scripts d'une vague."""
    if args.vague:
        vagues = (data.get("calendrier") or {}).get("vagues") or []
        match = [v for v in vagues if v.get("id", "").upper() == args.vague.upper()]
        if not match:
            known = ", ".join(v.get("id", "?") for v in vagues)
            sys.exit(f"ERREUR : vague inconnue « {args.vague} » (connues : {known})")
        return list(match[0].get("scripts") or [])
    return list(args.ids or [])


def main() -> None:
    ap = argparse.ArgumentParser(
        description="Met a jour le statut de production d'un ou plusieurs scripts video.")
    ap.add_argument("args", nargs="*",
                    help="IDs des scripts puis le nouveau statut (ex. VID-05 VID-06 tourne)")
    ap.add_argument("--vague", help="Cibler tous les scripts d'une vague du calendrier (ex. V2)")
    ap.add_argument("--obs", default="", help="Observation affichee dans l'historique")
    ap.add_argument("--auteur", default=DEFAULT_AUTHOR, help=f"Auteur (defaut : {DEFAULT_AUTHOR})")
    ap.add_argument("--date", default=None, help="Horodatage ISO 8601 (defaut : maintenant UTC)")
    ap.add_argument("--force", action="store_true", help="Autoriser un saut d'etape en avant")
    ap.add_argument("--dry-run", action="store_true", help="Afficher sans ecrire")
    ap.add_argument("--etat", action="store_true", help="Afficher l'etat courant et sortir")
    args = ap.parse_args()

    # argparse ne sait pas separer « N ids + 1 statut » avec deux positionnels
    # (nargs='*' avale tout) : on decoupe nous-memes sur le dernier mot.
    args.ids, args.statut = [], None
    if args.args:
        if args.args[-1] in STATUS_CODES:
            args.statut = args.args[-1]
            args.ids = args.args[:-1]
        else:
            args.ids = args.args
            # Un dernier mot qui n'est pas un statut connu : c'est une faute de
            # frappe, pas un ID. Le dire clairement au lieu de « statut manquant ».
            if len(args.args) > 1:
                sys.exit(f"ERREUR : statut inconnu « {args.args[-1]} » "
                         f"(valides : {', '.join(STATUS_CODES)})")

    doc = load_json(STATUS_FILE)
    order, defs = status_defs(doc)
    statuts = doc.setdefault("statuts", {})

    if args.etat:
        counts = {c: 0 for c in order}
        for sid, entry in statuts.items():
            code = entry.get("statut", "a_ecrire")
            counts[code] = counts.get(code, 0) + 1
        print(f"{len(statuts)} scripts suivis — " + " | ".join(
            f"{defs.get(c, {}).get('label', c)} : {counts.get(c, 0)}" for c in order))
        for sid in sorted(statuts):
            e = statuts[sid]
            print(f"  {sid}  {e.get('statut', '?'):<10} {e.get('date_modification', '')}")
        return
    if not args.statut:
        ap.error("statut manquant (ou utiliser --etat)")
    if args.statut not in defs:
        sys.exit(f"ERREUR : statut inconnu « {args.statut} » (valides : {', '.join(order)})")

    data = load_json(DATA_FILE)
    targets = resolve_targets(args, data)
    if not targets:
        ap.error("aucun script cible (donner des IDs ou --vague)")

    known_ids = {s["id"] for s in (data.get("scripts") or [])}
    unknown = [t for t in targets if t not in known_ids]
    if unknown:
        sys.exit(f"ERREUR : script(s) inconnu(s) dans scripts-video.json — {', '.join(unknown)}")

    now = args.date or datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    new_idx = order.index(args.statut)

    changed, skipped = [], []
    for sid in targets:
        entry = statuts.setdefault(sid, {"historique": []})
        old = entry.get("statut", "a_ecrire")
        old_idx = order.index(old) if old in order else 0

        if old == args.statut:
            skipped.append((sid, "deja dans ce statut"))
            continue
        if new_idx > old_idx + 1 and not args.force:
            skipped.append((sid, f"saut d'etape {old} -> {args.statut} (utiliser --force)"))
            continue

        action = "avancement" if new_idx > old_idx else "retour en arriere"
        entry["statut"] = args.statut
        entry["observation"] = args.obs or entry.get("observation", "")
        entry["auteur"] = args.auteur
        entry["date_modification"] = now
        entry.setdefault("historique", []).append({
            "date": now,
            "auteur": args.auteur,
            "statut": args.statut,
            "observation": args.obs or f"Passage a « {defs[args.statut]['label']} ».",
            "action": action,
        })
        changed.append((sid, old, args.statut))

    for sid, old, new in changed:
        print(f"  {sid} : {defs.get(old, {}).get('label', old)} -> {defs[new]['label']}")
    for sid, why in skipped:
        print(f"  {sid} : ignore ({why})")

    if not changed:
        print("Aucun changement.")
        return

    if args.dry_run:
        print(f"[dry-run] {len(changed)} changement(s) non ecrit(s).")
        return

    STATUS_FILE.write_text(json.dumps(doc, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"{len(changed)} statut(s) mis a jour dans {STATUS_FILE.name}.")
    print("Le site sera regenere au prochain deploiement (build_marketing.py).")


if __name__ == "__main__":
    try:
        main()
    except BrokenPipeError:
        # `| head` ferme le tube : sortie normale, pas une erreur.
        sys.stderr.close()
        sys.exit(0)
