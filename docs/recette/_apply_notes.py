#!/usr/bin/env python3
"""Applique les notes IA dans la source de vérité de la taxonomie
(build_portal.py pour UC-01..UC-26, taxonomy_extension.py pour UC-27..UC-89).

Ne touche JAMAIS aux notes humaines (notes-humaines.json).
"""
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
notes = json.loads((HERE / "_notes_ia.json").read_text(encoding="utf-8"))

# Chaîne Python entre guillemets doubles, échappements compris.
STR = r'"(?:[^"\\]|\\.)*"'
RE_NOTE = re.compile(r"note_ia=" + STR)
RE_OBS = re.compile(r"obs_ia=" + STR)


def patch_file(path: Path, ids):
    txt = path.read_text(encoding="utf-8")
    changed = 0
    for uc_id in ids:
        if uc_id not in notes:
            continue
        note = notes[uc_id]["note_ia"]
        obs = notes[uc_id]["obs_ia"]
        start = txt.find('dict(id="%s"' % uc_id)
        if start < 0:
            print("  !! %s introuvable dans %s" % (uc_id, path.name))
            continue
        nxt = txt.find("dict(id=", start + 10)
        end = nxt if nxt > 0 else len(txt)
        block = txt[start:end]
        new_block = RE_NOTE.sub(lambda m: "note_ia=" + json.dumps(note), block, count=1)
        new_block = RE_OBS.sub(lambda m: "obs_ia=" + json.dumps(obs), new_block, count=1)
        if new_block != block:
            txt = txt[:start] + new_block + txt[end:]
            changed += 1
    path.write_text(txt, encoding="utf-8")
    print("%s: %d cas mis à jour" % (path.name, changed))


patch_file(HERE / "build_portal.py", ["UC-%02d" % i for i in range(1, 27)])
patch_file(HERE / "taxonomy_extension.py", ["UC-%02d" % i for i in range(27, 90)])
