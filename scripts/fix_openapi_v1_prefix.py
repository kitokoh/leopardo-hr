#!/usr/bin/env python3
"""#eval-2026-11-24 — retire le prefixe de version `/v1` des chemins OpenAPI.

Cause racine des erreurs Redocly `operation-operationId-unique` et
`no-identical-paths` : le bloc `servers` porte deja `/api/v1`, donc un chemin
documente `/v1/travel/adverts` designe en realite `/api/v1/v1/travel/adverts`.
La garde CI (`check-openapi-route-coverage.py`) neutralise ce prefixe pour la
comparaison, ce qui masquait le probleme ; Redocly, lui, le voit.

Le script :
  1. renomme le parametre de chemin `{site}` en `{travelTouristSite}` sur
     l'alias `/travel/community/tourist-sites/*` (le controleur injecte
     `TravelTouristSite $travelTouristSite` : la liaison implicite se fait par
     NOM, un `{site}` laisse le modele vide -> 404 sur tout identifiant valide) ;
     les declarations `parameters:` de niveau chemin ET d'operation sont
     renommees avec lui ;
  2. retire le prefixe `/v1` de tous les chemins documentes ;
  3. fusionne les blocs devenus homonymes, verbe par verbe, en conservant la
     variante la plus riche (a egalite : celle qui porte une vraie description,
     pas un resume-stub auto-genere). Les cles de niveau chemin (`parameters:`)
     sont conservees — les perdre ferait tomber `path-parameters-defined`.

Idempotent : relance sans effet si le prefixe est deja retire.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
SPEC = REPO / "api" / "openapi.yaml"

VERB_RE = re.compile(r"^    (get|post|put|patch|delete|options|head):$")
PATH_RE = re.compile(r"^  (/[^:]+):$")
OLD_PATH = "/travel/community/tourist-sites/{site}"
NEW_PATH = "/travel/community/tourist-sites/{travelTouristSite}"


def parse_blocks(lines: list[str]) -> list[dict]:
    """Decoupe la section `paths:` en blocs {path, start, end, head, verbs}."""
    comp = lines.index("components:")
    starts = [i for i, l in enumerate(lines) if PATH_RE.match(l) and i < comp]
    blocks = []
    for idx, s in enumerate(starts):
        e = starts[idx + 1] if idx + 1 < len(starts) else comp
        verbs: dict[str, tuple[int, int]] = {}
        first_verb = None
        cur, cs = None, None
        for i in range(s + 1, e):
            m = VERB_RE.match(lines[i])
            if m:
                if first_verb is None:
                    first_verb = i
                if cur is not None:
                    verbs[cur] = (cs, i)
                cur, cs = m.group(1), i
        if cur is not None:
            verbs[cur] = (cs, e)
        head_end = first_verb if first_verb is not None else e
        blocks.append(
            {
                "path": lines[s][2:-1],
                "start": s,
                "end": e,
                "head": lines[s + 1 : head_end],
                "verbs": verbs,
            }
        )
    return blocks


def is_stub(lines: list[str], span: tuple[int, int], path: str, verb: str) -> bool:
    """Vrai si le resume est le stub auto-genere `VERB /chemin`."""
    for i in range(span[0], span[1]):
        m = re.match(r"^      summary: (.+)$", lines[i])
        if m:
            return m.group(1).strip() == f"{verb.upper()} {path}"
    return False


def rename_param(block_lines: list[str]) -> list[str]:
    """`- name: site` -> `- name: travelTouristSite` (niveau chemin ou operation)."""
    return [
        re.sub(r"^(\s*- name: )site$", r"\1travelTouristSite", l) for l in block_lines
    ]


def main() -> int:
    lines = SPEC.read_text(encoding="utf-8").split("\n")
    blocks = parse_blocks(lines)

    # --- 1. renommage du parametre de chemin fautif -------------------------
    renamed = 0
    for b in blocks:
        if b["path"] == OLD_PATH:
            b["path"] = NEW_PATH
            b["head"] = rename_param(b["head"])
            for verb, span in list(b["verbs"].items()):
                s, e = span
                lines[s:e] = rename_param(lines[s:e])
                b["verbs"][verb] = (s, e)
            renamed += 1

    # --- 2. retrait du prefixe de version -----------------------------------
    stripped = 0
    for b in blocks:
        if b["path"].startswith("/v1/"):
            b["path"] = b["path"][3:]
            stripped += 1

    # --- 3. fusion des blocs homonymes, verbe par verbe ---------------------
    merged: dict[str, dict] = {}
    order: list[str] = []
    for b in blocks:
        p = b["path"]
        if p not in merged:
            merged[p] = {"verbs": {}, "order": [], "head": b["head"]}
            order.append(p)
        tgt = merged[p]
        if len(b["head"]) > len(tgt["head"]):
            tgt["head"] = b["head"]
        for verb, span in b["verbs"].items():
            cand = {
                "span": span,
                "rich": span[1] - span[0],
                "stub": is_stub(lines, span, b["path"], verb),
            }
            cur = tgt["verbs"].get(verb)
            if cur is None:
                tgt["verbs"][verb] = cand
                tgt["order"].append(verb)
                continue
            # plus riche gagne ; a egalite, une vraie description bat un stub.
            if (cand["rich"], not cand["stub"]) > (cur["rich"], not cur["stub"]):
                tgt["verbs"][verb] = cand

    # --- emission -----------------------------------------------------------
    out: list[str] = []
    i = 0
    starts = {b["start"]: b for b in blocks}
    emitted: set[str] = set()
    while i < len(lines):
        b = starts.get(i)
        if b is not None:
            p = b["path"]
            if p in emitted:
                i = b["end"]  # bloc absorbe par la fusion
                continue
            emitted.add(p)
            out.append(f"  {p}:")
            out.extend(merged[p]["head"])
            for verb in merged[p]["order"]:
                s, e = merged[p]["verbs"][verb]["span"]
                out.extend(lines[s:e])
            i = b["end"]
            continue
        out.append(lines[i])
        i += 1

    SPEC.write_text("\n".join(out), encoding="utf-8")

    print(f"parametre renomme : {renamed}")
    print(f"chemins de-prefixes : {stripped}")
    print(f"blocs fusionnes : {len(blocks) - len(order)}")
    print(f"chemins finaux : {len(order)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
