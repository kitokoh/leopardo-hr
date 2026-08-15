#!/usr/bin/env python3
"""Correction d'accents manquants — contenu FR uniquement (QA 2026-08-15, #2604).

Principe :
  - Table mot → mot **connue** et **sans ambiguïté** : on ne corrige que des
    mots dont la forme non accentuée n'est PAS un mot français valide (donc la
    forme accentuée est obligatoire). Les mots ambigus (ou/où, a/à, des/dès,
    sur/sûr, realise → réalisé/réalise, change → changé, partage → partagé…)
    et les mots valides en anglais (regime, present, preparation, general…)
    sont EXCLUS de la table pour ne jamais casser le contenu EN/TR/AR.
  - Correction par mot entier (word boundary), jamais à l'intérieur d'un slug,
    d'un path, d'une URL, d'un identifiant, d'une clé d'objet ou d'un mot en
    camelCase/kebab-case.

Usage :
  fix-missing-accents.py [--check] [--dry-run] [FILE...]
    --check    quitte avec un code non nul si des mots non accentués restent
    --dry-run  affiche les remplacements sans écrire
    (sans FILE, traite les fichiers FR par défaut de la vitrine web)
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

# ---------------------------------------------------------------------------
# Table mot → mot. Règles d'entrée :
#   - la forme source ne doit pas être un mot français valide sans accent ;
#   - elle ne doit pas non plus être un mot anglais courant (pas de faux
#     positifs sur le contenu en/tr/ar des fichiers multilingues) ;
#   - seule la première lettre peut être capitalisée (variante déduite).
# ---------------------------------------------------------------------------
WORD_TABLE: dict[str, str] = {
    "activite": "activité",
    "acces": "accès",
    "categorie": "catégorie",
    "comptabilite": "comptabilité",
    "conformite": "conformité",
    "decouvrir": "découvrir",
    "decouvrez": "découvrez",
    "deja": "déjà",
    "demarrer": "démarrer",
    "demarrez": "démarrez",
    "departement": "département",
    "developpement": "développement",
    "developpeur": "développeur",
    "employe": "employé",
    "equipe": "équipe",
    "etape": "étape",
    "evenement": "événement",
    "necessaire": "nécessaire",
    "numero": "numéro",
    "periode": "période",
    "preciser": "préciser",
    "regle": "règle",
    "repondre": "répondre",
    "reponse": "réponse",
    "reussir": "réussir",
    "reussite": "réussite",
    "securite": "sécurité",
    "systeme": "système",
    "telechargement": "téléchargement",
    "telecharger": "télécharger",
    "verification": "vérification",
    "salarie": "salarié",
    "conge": "congé",
    # Formes féminines/plurielles en -ee(s) (formes non accentuées invalides)
    "associees": "associées",
    "automatisees": "automatisées",
    "connectees": "connectées",
    "dernieres": "dernières",
    "deploiement": "déploiement",
    "distribuee": "distribuée",
    "distribuees": "distribuées",
    "diversite": "diversité",
    "evolutions": "évolutions",
    "fonctionnalites": "fonctionnalités",
    "integrees": "intégrées",
    "liees": "liées",
    "mobilite": "mobilité",
    "personnalisee": "personnalisée",
    "posees": "posées",
    "securisees": "sécurisées",
    "simplifiees": "simplifiées",
    "simplifies": "simplifiés",
}

# Pluriels dérivés (mot + 's') — la forme plurielle non accentuée n'est pas
# plus valide que la forme singulière.
_PLURALS: dict[str, str] = {
    f"{src}s": f"{dst}s" for src, dst in WORD_TABLE.items()
    if not src.endswith(("s", "x", "z")) and src in {
        "activite", "categorie", "employe", "equipe", "numero", "systeme",
        "evenement", "periode", "departement", "regle", "reponse",
        "developpeur", "salarie", "conge", "etape",
    }
}

TABLE = {**WORD_TABLE, **_PLURALS}

# Capitalisée (première lettre) : « Acces » → « Accès », « Employes » → « Employés »
CAP_TABLE = {k.capitalize(): v.capitalize() for k, v in TABLE.items() if k[0].islower()}

# Ne jamais corriger ces contextes : le mot fait partie d'un slug, d'un path,
# d'une URL, d'un identifiant ou d'un mot composé. Les guillemets/apostrophes
# de chaîne NE bloquent pas (contenu FR à corriger), mais l'apostrophe de
# liaison (« l'equipe ») reste corrigeable car elle suit/précède le mot.
_SLUG_NEIGHBORS = set("/\\-_.?#@{}[]()=+*`|%&;:")
_KEY_RE = re.compile(r"^\s*[A-Za-z0-9_]+:\s*['\"]?$")
_DECL_RE = re.compile(r"^\s*(const|let|var|function|import|export|type|interface|class|enum)\s+")
_ASSIGN_RE = re.compile(r"=\s*['\"]?$")


def _is_key_position(line: str, start: int, end: int) -> bool:
    """Position à ne JAMAIS corriger : clé d'objet, nom de variable déclaré
    ou affecté (un identifiant ne doit pas être « accentué »)."""
    after = line[end : end + 1]
    if after == ":":
        return True
    # Nom de variable dans une déclaration : `const categories = ...`
    head = line[max(0, start - 12) : start]
    if re.search(r"(?:^|\s)(const|let|var|function|class|enum|import|export|type|interface)\s+$", head):
        return True
    # Affectation : `categories = ...`
    if re.match(r"^\s*=\s*['\"]?", line[end : end + 12]):
        return True
    return False


def fix_text(text: str) -> tuple[str, list[tuple[int, int, str, str]]]:
    """Retourne (nouveau texte, [(line_idx, col, avant, après), ...])."""
    lines = text.splitlines(keepends=True)
    out: list[str] = []
    changes: list[tuple[int, int, str, str]] = []
    for line_idx, line in enumerate(lines):
        new_line = line
        # On construit un masque des positions à ne PAS corriger (mots dans
        # des slugs/paths/URLs/identifiants) : on repère les segments
        # contigus de caractères « non-mot » et on interdit leurs voisins.
        for m in re.finditer(r"[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ0-9]*", line):
            start, end = m.start(), m.end()
            word = m.group()
            if word not in TABLE and word not in CAP_TABLE:
                continue
            if _is_key_position(line, start, end):
                continue
            # Voisins immédiats de type slug/url/identifiant → skip
            before = line[start - 1] if start > 0 else ""
            after = line[end] if end < len(line) else ""
            if before in _SLUG_NEIGHBORS or after in _SLUG_NEIGHBORS:
                continue
            replacement = TABLE.get(word, CAP_TABLE.get(word, word))
            if replacement != word:
                new_line = new_line[:start] + replacement + new_line[end:]
                changes.append((line_idx, start, word, replacement))
        out.append(new_line)
    return "".join(out), changes


DEFAULT_TARGETS = [
    "front/web/src/modules/vitrine/data/blog.ts",
    "front/web/src/modules/vitrine/data/faq.ts",
    "front/web/src/modules/vitrine/data/testimonials.ts",
    "front/web/src/modules/vitrine/lib/legal-content.ts",
    "front/web/src/modules/vitrine/lib/seo.ts",
    "front/web/src/lib/i18n.ts",
    "front/web/src/modules/vitrine/components/forms/SignupForm.tsx",
    "front/web/src/app/(dashboard)/dashboard/page.tsx",
    "front/web/src/app/(dashboard)/settings",
    "front/web/src/app/(landing)",
]


def iter_files(paths: list[str], root: Path) -> list[Path]:
    files: list[Path] = []
    for raw in paths:
        p = root / raw
        if p.is_dir():
            files.extend(sorted(p.rglob("*.tsx")) + sorted(p.rglob("*.ts")))
        elif p.is_file():
            files.append(p)
    return files


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--check", action="store_true", help="exit non-zero si des accents manquent")
    ap.add_argument("--dry-run", action="store_true", help="affiche sans écrire")
    ap.add_argument("files", nargs="*", help="fichiers à traiter (défaut : cibles FR de la vitrine)")
    args = ap.parse_args()

    root = Path(__file__).resolve().parents[2]  # racine du repo (dev-hub/tools/ -> repo)
    targets = args.files or DEFAULT_TARGETS
    files = iter_files(targets, root)

    total_changes = 0
    dirty = False
    for f in files:
        text = f.read_text(encoding="utf-8")
        fixed, changes = fix_text(text)
        if not changes:
            continue
        total_changes += len(changes)
        dirty = True
        print(f"{f.relative_to(root)}: {len(changes)} remplacement(s)")
        for line_idx, col, before, after in changes:
            print(f"  l.{line_idx + 1}:{col}: {before!r} -> {after!r}")
        if not args.dry_run:
            f.write_text(fixed, encoding="utf-8")

    print(f"\n{total_changes} remplacement(s) au total.")
    if args.check:
        return 1 if dirty else 0
    return 0


if __name__ == "__main__":
    sys.exit(main())
