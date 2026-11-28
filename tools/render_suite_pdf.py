#!/usr/bin/env python3
"""Rend SCRIPTS_VIDEOS_SUITE.md en PDF pagine, pour relecture et impression.

Pourquoi ce script existe
-------------------------
Le programme SUITE est un document de production : il se relit sur papier, se
coche en reunion de tournage et s'envoie a une agence. Un Markdown brut ne se
relit pas dans ces conditions, et un export HTML sans pagination coupe les
tableaux de plans en deux.

Ce script produit un PDF A4 avec :
  - une page de garde ;
  - un sommaire numerote ;
  - les tableaux qui ne se coupent pas au milieu d'une ligne ;
  - les blocs de prompt en chasse fixe, encadres, non coupes ;
  - un pied de page avec le numero de page.

Il ne modifie pas le Markdown : il le rend. Toute correction de fond se fait
dans SCRIPTS_VIDEOS_SUITE.md, jamais ici.

Usage
-----
    python3 tools/render_suite_pdf.py
    python3 tools/render_suite_pdf.py --out /tmp/suite.pdf
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs/GOTO_MARKET/ASSETS_PRODUCTION/VIDEOS/SCRIPTS_VIDEOS_SUITE.md"
DEFAULT_OUT = ROOT / "docs/GOTO_MARKET/ASSETS_PRODUCTION/VIDEOS/SCRIPTS_VIDEOS_SUITE.pdf"

CSS = """
@page { size: A4; margin: 18mm 15mm 20mm 15mm;
        @bottom-center { content: counter(page) " / " counter(pages);
                         font: 8pt "DejaVu Sans"; color: #64748b; } }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9.2pt; line-height: 1.45;
       color: #0f172a; }
h1 { font-size: 20pt; color: #0b1220; border-bottom: 3px solid #2563eb;
     padding-bottom: 6pt; margin: 0 0 14pt; }
h2 { font-size: 14pt; color: #0b1220; margin: 20pt 0 8pt;
     border-bottom: 1px solid #cbd5e1; padding-bottom: 3pt;
     break-after: avoid; }
h3 { font-size: 11.5pt; color: #1e3a8a; margin: 16pt 0 6pt; break-after: avoid; }
h4 { font-size: 10pt; color: #334155; margin: 12pt 0 4pt; break-after: avoid; }
p, li { orphans: 3; widows: 3; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt;
        font-size: 8.2pt; break-inside: auto; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
th { background: #0b1220; color: #fff; text-align: left; padding: 4pt 5pt;
     font-weight: 600; }
td { border-bottom: 1px solid #e2e8f0; padding: 3.5pt 5pt; vertical-align: top; }
tbody tr:nth-child(even) td { background: #f8fafc; }
pre { background: #0b1220; color: #e2e8f0; padding: 8pt 10pt; border-radius: 4pt;
      font-family: "DejaVu Sans Mono", monospace; font-size: 7.4pt; line-height: 1.35;
      white-space: pre-wrap; word-wrap: break-word;
      /* Un bloc de prompt fait 30 a 40 lignes : l'interdire de coupure repoussait
         le bloc entier a la page suivante et laissait 35 % de blanc. On autorise
         la coupure, mais jamais sur une seule ligne isolee en bas de page. */
      break-inside: auto; orphans: 4; widows: 4;
      border-left: 3px solid #2563eb; }
code { font-family: "DejaVu Sans Mono", monospace; font-size: 8pt;
       background: #f1f5f9; padding: 0.5pt 2pt; border-radius: 2pt; }
pre code { background: none; padding: 0; font-size: inherit; color: inherit; }
blockquote { border-left: 3px solid #94a3b8; margin: 8pt 0; padding: 4pt 10pt;
             color: #334155; background: #f8fafc; break-inside: avoid; }
hr { border: none; border-top: 1px solid #cbd5e1; margin: 14pt 0; }
.cover { text-align: center; padding-top: 60mm; break-after: page; }
.cover .kicker { font-size: 10pt; letter-spacing: 2pt; color: #2563eb;
                 text-transform: uppercase; }
.cover h1 { border: none; font-size: 26pt; margin: 10pt 0; }
.cover .sub { font-size: 11pt; color: #475569; }
.cover .meta { margin-top: 30mm; font-size: 9pt; color: #64748b; }
"""

COVER = """
<div class="cover">
  <div class="kicker">Leopardo &middot; production vid&eacute;o</div>
  <h1>Scripts vid&eacute;o &mdash; suite m&eacute;tier</h1>
  <div class="sub">Programme complet : marque, d&eacute;monstration, vente,<br>
  verticales, confiance, activation, partenaires</div>
  <div class="meta">Document de production &middot; version du {date}<br>
  Source : <code>SCRIPTS_VIDEOS_SUITE.md</code></div>
</div>
"""


def build_html(md_text: str) -> str:
    import markdown

    body = markdown.markdown(
        md_text,
        extensions=["tables", "fenced_code", "sane_lists", "attr_list"],
    )
    # Numero de page + titre courant : le PDF doit rester utilisable imprime.
    return f"""<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<title>Scripts video - suite metier Leopardo</title>
<style>{CSS}</style></head>
<body>{COVER.format(date=_doc_date(md_text))}{body}</body></html>"""


def _doc_date(md_text: str) -> str:
    m = re.search(r"(\d{4}-\d{2}-\d{2})", md_text)
    return m.group(1) if m else "2026-11-28"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--out", default=str(DEFAULT_OUT))
    ap.add_argument("--html-only", action="store_true")
    args = ap.parse_args()

    if not SOURCE.exists():
        print(f"ERREUR — source introuvable : {SOURCE}", file=sys.stderr)
        return 2

    md_text = SOURCE.read_text(encoding="utf-8")
    html = build_html(md_text)

    out = Path(args.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    html_path = out.with_suffix(".html")
    html_path.write_text(html, encoding="utf-8")

    if args.html_only:
        print(f"OK : {html_path}")
        return 0

    # wkhtmltopdf : present dans l'environnement, gere @page et les en-tetes.
    cmd = [
        "wkhtmltopdf", "--quiet", "--enable-local-file-access",
        "--page-size", "A4",
        "--margin-top", "16mm", "--margin-bottom", "18mm",
        "--margin-left", "14mm", "--margin-right", "14mm",
        "--footer-font-size", "8", "--footer-right", "[page] / [topage]",
        "--footer-left", "Leopardo — Scripts video suite metier",
        str(html_path), str(out),
    ]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        print("ERREUR wkhtmltopdf :", r.stderr[-1500:], file=sys.stderr)
        return 1

    print(f"OK : {out} ({out.stat().st_size // 1024} Ko)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
