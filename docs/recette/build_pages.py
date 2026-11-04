#!/usr/bin/env python3
"""Construit la page GitHub Pages du cahier de recette Leopardo.

Usage: python3 build_pages.py <chemin/vers/tests-ia-leopardo-hr.md> <sortie/index.html>
"""
import sys
import markdown

CSS = """
:root{
  --bg:#0f1115; --panel:#161a21; --panel-2:#1c222b; --text:#e6e9ef; --muted:#9aa4b2;
  --accent:#f5a524; --accent-2:#4f9cf9; --border:#2a323d; --ok:#3fb950; --warn:#d29922;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0; background:var(--bg); color:var(--text);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,Helvetica,Arial,sans-serif;
  line-height:1.65; font-size:16px;
}
.wrap{max-width:1180px;margin:0 auto;padding:0 24px 96px}
header.hero{
  background:linear-gradient(135deg,#1a1f27 0%,#0f1115 60%);
  border-bottom:1px solid var(--border); padding:56px 0 40px; margin-bottom:40px;
}
header.hero .wrap{padding-bottom:0}
.eyebrow{
  display:inline-block; font-size:12px; letter-spacing:.14em; text-transform:uppercase;
  color:var(--accent); border:1px solid rgba(245,165,36,.35); border-radius:999px;
  padding:5px 14px; margin-bottom:18px; background:rgba(245,165,36,.07);
}
header.hero h1{margin:0 0 12px; font-size:clamp(28px,4vw,44px); line-height:1.15; letter-spacing:-.02em}
header.hero p.sub{margin:0; color:var(--muted); font-size:17px; max-width:760px}
.meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.meta span{
  font-size:13px;color:var(--muted);background:var(--panel);border:1px solid var(--border);
  border-radius:8px;padding:6px 12px;
}
.meta span b{color:var(--text);font-weight:600}
main h1,main h2,main h3,main h4{line-height:1.25;letter-spacing:-.01em;scroll-margin-top:24px}
main h1{font-size:30px;margin:56px 0 18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
main h2{
  font-size:24px;margin:52px 0 16px;padding:14px 18px;background:var(--panel);
  border-left:4px solid var(--accent);border-radius:0 10px 10px 0;
}
main h3{font-size:18px;margin:28px 0 10px;color:var(--accent-2)}
main p{margin:12px 0}
main strong{color:#fff}
main a{color:var(--accent-2);text-decoration:none;border-bottom:1px solid rgba(79,156,249,.35)}
main a:hover{border-bottom-color:var(--accent-2)}
main ul,main ol{padding-left:24px;margin:12px 0}
main li{margin:6px 0}
main code{
  background:var(--panel-2);border:1px solid var(--border);border-radius:5px;
  padding:2px 6px;font-size:.88em;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  color:#ffd28a;
}
main pre{
  background:var(--panel);border:1px solid var(--border);border-radius:12px;
  padding:18px 20px;overflow-x:auto;margin:18px 0;
}
main pre code{background:none;border:none;padding:0;color:#c9d4e3;font-size:13.5px;line-height:1.6}
.table-scroll{overflow-x:auto;margin:20px 0;border:1px solid var(--border);border-radius:12px;background:var(--panel)}
table{border-collapse:collapse;width:100%;font-size:14.5px;min-width:520px}
thead th{
  background:var(--panel-2);color:#fff;text-align:left;font-weight:600;
  padding:12px 14px;border-bottom:2px solid var(--border);overflow-wrap:anywhere;
}
tbody td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:top;overflow-wrap:anywhere;word-break:break-word}
/* colonnes de notation : largeur minimale pour rester lisibles */
table th:nth-child(3),table td:nth-child(3){min-width:64px;text-align:center}
tbody tr:last-child td{border-bottom:none}
tbody tr:nth-child(even){background:rgba(255,255,255,.018)}
tbody tr:hover{background:rgba(79,156,249,.06)}
/* colonnes de notation : cases à remplir */
td:empty{background:rgba(245,165,36,.05)}
td:empty::after{content:"";display:block;min-height:22px}
blockquote{
  margin:20px 0;padding:14px 18px;background:rgba(210,153,34,.08);
  border-left:4px solid var(--warn);border-radius:0 10px 10px 0;color:#e8d9b5;
}
blockquote p{margin:6px 0}
hr{border:none;border-top:1px solid var(--border);margin:44px 0}
footer{
  margin-top:64px;padding-top:24px;border-top:1px solid var(--border);
  color:var(--muted);font-size:13px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;
}
@media print{
  body{background:#fff;color:#111}
  header.hero{background:#fff;border-bottom:1px solid #ccc}
  main h2{background:#f4f4f4;border-left-color:#333}
  .table-scroll{border-color:#ccc}
  thead th{background:#eee;color:#111}
  main code{background:#f4f4f4;color:#111}
}
@media (max-width:640px){
  .wrap{padding:0 16px 64px}
  header.hero{padding:36px 0 28px}
  main h2{font-size:20px;padding:12px 14px}
  table{font-size:13px}
}
"""

TEMPLATE = """<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cahier de recette — Leopardo (leopardo-hr)</title>
<meta name="description" content="Cahier de recette destiné aux testeurs IA et humains de la solution Leopardo (dépôt Kitokoh/leopardo-hr).">
<style>{css}</style>
</head>
<body>
<header class="hero">
  <div class="wrap">
    <span class="eyebrow">Cahier de recette · Testeurs IA &amp; humains</span>
    <h1>Leopardo — Business OS pour entreprises de terrain</h1>
    <p class="sub">Grille d'évaluation des cas d'usage de la solution <code>leopardo-hr</code> : pointage, absences, paie, multi-tenant, kiosque, CRM et solutions verticales.</p>
    <div class="meta">
      <span>Dépôt&nbsp;: <b>Kitokoh/leopardo-hr</b></span>
      <span>Version&nbsp;: <b>bc0464b</b></span>
      <span>Cas d'usage&nbsp;: <b>14</b></span>
      <span>Barème&nbsp;: <b>/5</b></span>
    </div>
  </div>
</header>
<main class="wrap">
{body}
<footer>
  <span>Leopardo — cahier de recette généré le 2026-11-03</span>
  <span>Colonnes « Note humaine » et « Observation humaine » réservées à l'évaluation humaine.</span>
</footer>
</main>
</body>
</html>
"""


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2
    src, dst = sys.argv[1], sys.argv[2]
    with open(src, encoding="utf-8") as fh:
        text = fh.read()

    body = markdown.markdown(
        text,
        extensions=["tables", "fenced_code", "sane_lists", "attr_list", "toc"],
        output_format="html5",
    )
    # Enveloppe chaque tableau pour permettre le défilement horizontal sur mobile.
    body = body.replace("<table>", '<div class="table-scroll"><table>').replace(
        "</table>", "</table></div>"
    )

    html = TEMPLATE.format(css=CSS, body=body)
    with open(dst, "w", encoding="utf-8") as fh:
        fh.write(html)
    print(f"OK -> {dst} ({len(html)} octets)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
