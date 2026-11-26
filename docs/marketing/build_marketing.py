#!/usr/bin/env python3
"""Genere l'espace « Scripts marketing » de leopardo-hr.

Espace jumeau du portail de recette (`docs/recette/`) : meme charte, meme
navigation, meme logique de publication GitHub Pages. Il rassemble les scripts
des videos de promotion de la solution, organises et classes par categorie.

Sortie (par defaut dans le dossier de ce script) :
  index.html                     — accueil : categories, formats, tous les scripts
  categories/index.html          — liste des categories
  categories/<slug>.html         — page par categorie (presentation, verticales, ...)
  scripts/<vid-id>.html          — fiche complete d'un script (hook, beats, CTA)
  recap.html                     — tableau recapitulatif filtrable
  assets/marketing.css           — feuille de style (jetons identiques au portail)
  assets/marketing.js            — recherche / filtre / tri
  scripts-video.md               — export Markdown de tous les scripts

Source de verite : `scripts-video.json` (categories + scripts). Le script ne
l'ecrase jamais ; il le lit et le rend.

Usage :
  python3 build_marketing.py [--out DIR]
"""
from __future__ import annotations

import argparse
import html
import json
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
DATA_FILENAME = "scripts-video.json"
GITHUB_REPO = "Kitokoh/leopardo-hr"
GITHUB_DATA_PATH = "docs/marketing/scripts-video.json"


def esc(s) -> str:
    return html.escape(str(s), quote=True)


def load_data() -> dict:
    raw = json.loads((HERE / DATA_FILENAME).read_text(encoding="utf-8"))
    cats = raw.get("categories") or []
    scripts = raw.get("scripts") or []
    return {"categories": cats, "scripts": scripts, "meta": raw}


DATA = load_data()
CATEGORIES = DATA["categories"]
SCRIPTS = DATA["scripts"]
META = DATA["meta"]

CAT_BY_SLUG = {c["slug"]: c for c in CATEGORIES}
SCRIPT_BY_ID = {s["id"]: s for s in SCRIPTS}

# ---------------------------------------------------------------------------
# SUIVI DE PRODUCTION (statuts-tournage.json)
# ---------------------------------------------------------------------------
# Fichier jumeau de `docs/recette/notes-humaines.json` : une entree par script,
# un statut, une observation, un auteur, une date, un historique horodate.
# Le generateur ne l'ecrit JAMAIS : il le lit et le rend.

STATUS_FILENAME = "statuts-tournage.json"


def load_statuts() -> dict:
    p = HERE / STATUS_FILENAME
    if not p.exists():
        return {"_meta": {"statuts": []}, "statuts": {}}
    return json.loads(p.read_text(encoding="utf-8"))


STATUS_DOC = load_statuts()
STATUS_DEFS = {s["code"]: s for s in (STATUS_DOC.get("_meta", {}).get("statuts") or [])}
STATUS_ORDER = [s["code"] for s in (STATUS_DOC.get("_meta", {}).get("statuts") or [])]


def status_of(sid: str) -> dict:
    return (STATUS_DOC.get("statuts") or {}).get(sid) or {}


def status_code(sid: str) -> str:
    return status_of(sid).get("statut") or "a_ecrire"


def status_badge(sid: str) -> str:
    code = status_code(sid)
    d = STATUS_DEFS.get(code, {"label": code, "color": "#64748b"})
    return (f'<span class="badge status-badge" style="background:{d["color"]}1a;color:{d["color"]};'
            f'border-color:{d["color"]}33">{esc(d["label"])}</span>')


def status_counts() -> dict:
    out = {c: 0 for c in STATUS_ORDER}
    for s in SCRIPTS:
        c = status_code(s["id"])
        out[c] = out.get(c, 0) + 1
    return out


def vid_slug(vid: str) -> str:
    return vid.lower().replace("_", "-")


def rel(depth: int) -> str:
    return "../" * depth


def cat_of(s: dict) -> dict:
    return CAT_BY_SLUG.get(s["category"], {"slug": s["category"], "name": s["category"],
                                           "icon": "🎬", "color": "#0f766e", "desc": ""})


def fmt_badge(fmt: str) -> str:
    color = {"16:9": "#0f766e", "9:16": "#db2777", "1:1": "#7c3aed"}.get(fmt, "#475569")
    return (f'<span class="badge" style="background:{color}1a;color:{color};'
            f'border-color:{color}33">{esc(fmt)}</span>')


def cat_badge(s: dict) -> str:
    c = cat_of(s)
    return (f'<span class="badge" style="background:{c["color"]}1a;color:{c["color"]};'
            f'border-color:{c["color"]}33">{c["icon"]} {esc(c["name"])}</span>')


# ---------------------------------------------------------------------------
# LAYOUT
# ---------------------------------------------------------------------------

def layout(title: str, depth: int, body: str, subtitle: str = "") -> str:
    p = rel(depth)
    sub = f'<p class="page-sub">{esc(subtitle)}</p>' if subtitle else ""
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)} — Scripts marketing Leopardo</title>
<link rel="stylesheet" href="{p}assets/marketing.css">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="{p}index.html">
      <span class="brand-mark">L</span>
      <span class="brand-text">Scripts marketing <small>leopardo-hr</small></span>
    </a>
    <nav class="mainnav">
      <a href="{p}index.html">Accueil</a>
      <a href="{p}categories/index.html">Categories</a>
      <a href="{p}calendrier.html">Calendrier de tournage</a>
      <a href="{p}recap.html">Recapitulatif</a>
      <a href="{p}scripts-video.md">Markdown</a>
      <a href="{p}../recette/index.html">Portail recette</a>
    </nav>
  </div>
</header>
<main class="wrap">
  <h1 class="page-title">{esc(title)}</h1>
  {sub}
  {body}
</main>
<footer class="footer">
  <div class="wrap">
    <p>Espace <strong>Scripts marketing</strong> de <strong>leopardo-hr</strong> — depot
      <a href="https://github.com/{GITHUB_REPO}" target="_blank" rel="noopener">{GITHUB_REPO}</a>.
      Source de verite : <a href="https://github.com/{GITHUB_REPO}/blob/main/{GITHUB_DATA_PATH}">{GITHUB_DATA_PATH}</a>.
      Chaque script porte un identifiant stable (VID-nn) : ne jamais le reutiliser pour un autre sujet.</p>
  </div>
</footer>
<script src="{p}assets/marketing.js"></script>
</body>
</html>
"""


def breadcrumb(depth: int, items: list[tuple[str, str]]) -> str:
    p = rel(depth)
    parts = [f'<a href="{p}index.html">Scripts marketing</a>']
    for label, href in items:
        if href:
            parts.append(f'<a href="{href}">{esc(label)}</a>')
        else:
            parts.append(f'<span aria-current="page">{esc(label)}</span>')
    return '<nav class="breadcrumb">' + ' <span class="sep">›</span> '.join(parts) + "</nav>"


# ---------------------------------------------------------------------------
# PAGES
# ---------------------------------------------------------------------------

def page_home() -> str:
    total = len(SCRIPTS)
    n_cats = len(CATEGORIES)
    n_vert = sum(1 for s in SCRIPTS if s["category"] == "verticales")
    n_short = sum(1 for s in SCRIPTS if s["format"] == "9:16")

    cat_cards = []
    for c in CATEGORIES:
        n = sum(1 for s in SCRIPTS if s["category"] == c["slug"])
        cat_cards.append(f"""
        <a class="card profile-card" href="categories/{c['slug']}.html" style="--accent:{c['color']}">
          <div class="card-icon">{c['icon']}</div>
          <h3>{esc(c['name'])}</h3>
          <p>{esc(c['desc'])}</p>
          <span class="card-meta">{n} script{'s' if n > 1 else ''}</span>
        </a>""")

    rows = []
    for s in SCRIPTS:
        c = cat_of(s)
        rows.append(f"""
        <tr data-cat="{esc(s['category'])}" data-format="{esc(s['format'])}" data-status="{esc(status_code(s['id']))}">
          <td class="nowrap"><a class="uc-link" href="scripts/{vid_slug(s['id'])}.html">{esc(s['id'])}</a></td>
          <td><a class="uc-link" href="scripts/{vid_slug(s['id'])}.html">{esc(s['title'])}</a></td>
          <td>{cat_badge(s)}</td>
          <td class="center">{fmt_badge(s['format'])}</td>
          <td class="center nowrap">{esc(s['duration'])}</td>
          <td class="center nowrap">{status_badge(s['id'])}</td>
          <td>{esc(s['audience'])}</td>
        </tr>""")

    return layout("Scripts marketing — videos de promotion", 0, f"""
  <section class="hero">
    <p class="hero-kicker">Espace scripts marketing</p>
    <h2 class="hero-title">Toutes les videos de promotion, ecrites et classees</h2>
    <p class="hero-lead">Le film pilier de presentation de la solution, un film par verticale,
      un film par profil utilisateur, les films produit et les formats courts pour le social.
      Chaque script est complet : hook, decoupage seconde par seconde, voix off, plans, CTA.</p>
    <div class="stats">
      <div class="stat"><span class="stat-num">{total}</span><span class="stat-label">scripts video</span></div>
      <div class="stat"><span class="stat-num">{n_cats}</span><span class="stat-label">categories</span></div>
      <div class="stat"><span class="stat-num">{n_vert}</span><span class="stat-label">verticales couvertes</span></div>
      <div class="stat"><span class="stat-num">{n_short}</span><span class="stat-label">formats 9:16</span></div>
    </div>
  </section>

  <section class="section">
    <h2 class="section-title">Par categorie</h2>
    <p class="section-sub">Meme organisation que le portail de recette : une page par categorie, une fiche par script.</p>
    <div class="grid grid-profiles">{''.join(cat_cards)}</div>
  </section>

  <section class="section">
    <h2 class="section-title">Tous les scripts</h2>
    <p class="section-sub">Filtrez par categorie ou par format, ou recherchez un titre.</p>
    <div class="toolbar">
      <input class="search" id="q" type="search" placeholder="Rechercher un script, une verticale, un mot-cle...">
      <select class="select" id="f-cat">
        <option value="">Toutes les categories</option>
        {''.join(f'<option value="{esc(c["slug"])}">{esc(c["name"])}</option>' for c in CATEGORIES)}
      </select>
      <select class="select" id="f-format">
        <option value="">Tous les formats</option>
        <option value="16:9">16:9</option>
        <option value="9:16">9:16</option>
      </select>
      <select class="select" id="f-status">
        <option value="">Tous les statuts</option>
        {''.join(f'<option value="{esc(c)}">{esc(STATUS_DEFS.get(c, {}).get("label", c))}</option>' for c in STATUS_ORDER)}
      </select>
    </div>
    <div class="table-wrap">
      <table class="data-table" id="tbl">
        <thead><tr>
          <th>ID</th><th>Titre</th><th>Categorie</th><th class="center">Format</th>
          <th class="center">Duree</th><th class="center">Statut</th><th>Audience</th>
        </tr></thead>
        <tbody>{''.join(rows)}</tbody>
      </table>
    </div>
    <p class="empty-hint" id="empty" hidden>Aucun script ne correspond a ce filtre.</p>
  </section>
""", "Videos de promotion de la solution leopardo-hr — navigation par categorie, format et script.")


def page_categories_index() -> str:
    cards = []
    for c in CATEGORIES:
        items = [s for s in SCRIPTS if s["category"] == c["slug"]]
        lis = "".join(
            f'<li><a href="../scripts/{vid_slug(s["id"])}.html">{esc(s["id"])} — {esc(s["title"])}</a>'
            f' <span class="muted small">({esc(s["duration"])})</span></li>'
            for s in items)
        cards.append(f"""
        <div class="panel" style="border-left:4px solid {c['color']}">
          <h3>{c['icon']} <a href="{c['slug']}.html">{esc(c['name'])}</a>
            <span class="hist-count-badge">{len(items)}</span></h3>
          <p class="muted">{esc(c['desc'])}</p>
          <ul class="plain">{lis}</ul>
        </div>""")
    return layout("Categories de scripts", 1, f"""
  {breadcrumb(1, [("Categories", None)])}
  <p class="lead">Les scripts sont classes par categorie, comme les cas d'usage du portail de recette
    sont classes par profil et par module.</p>
  {''.join(cards)}
""")


def page_category(slug: str) -> str:
    c = CAT_BY_SLUG[slug]
    items = [s for s in SCRIPTS if s["category"] == slug]
    cards = []
    for s in items:
        cards.append(f"""
        <a class="card" href="../scripts/{vid_slug(s['id'])}.html" style="--accent:{c['color']}">
          <div class="card-icon">{c['icon']}</div>
          <h3>{esc(s['id'])} — {esc(s['title'])}</h3>
          <p>{esc(s['objective'])}</p>
          <div class="chips">{fmt_badge(s['format'])}<span class="chip">{esc(s['duration'])}</span></div>
        </a>""")
    return layout(c["name"], 1, f"""
  {breadcrumb(1, [("Categories", "index.html"), (c["name"], None)])}
  <div class="profile-head" style="--accent:{c['color']}">
    <div class="profile-icon">{c['icon']}</div>
    <div>
      <p class="profile-desc">{esc(c['desc'])}</p>
      <div class="chips"><span class="chip">{len(items)} script{'s' if len(items) > 1 else ''}</span></div>
    </div>
  </div>
  <div class="grid grid-modules">{''.join(cards)}</div>
""", c["desc"])


def page_script(s: dict) -> str:
    c = cat_of(s)
    beats = []
    for b in s.get("beats", []):
        beats.append(f"""
        <tr>
          <td class="nowrap"><strong>{esc(b['t'])}</strong></td>
          <td>{esc(b['vo'])}</td>
          <td>{esc(b['shot'])}</td>
          <td><span class="ontext">{esc(b.get('text', ''))}</span></td>
        </tr>""")
    tags = "".join(f'<span class="chip">#{esc(t)}</span>' for t in s.get("tags", []))
    idx = [x["id"] for x in SCRIPTS].index(s["id"])
    prev_s = SCRIPTS[idx - 1] if idx > 0 else None
    next_s = SCRIPTS[idx + 1] if idx < len(SCRIPTS) - 1 else None
    nav = []
    if prev_s:
        nav.append(f'<a class="btn-back" href="{vid_slug(prev_s["id"])}.html">← {esc(prev_s["id"])}</a>')
    nav.append(f'<a class="btn-back" href="../categories/{c["slug"]}.html">Toute la categorie</a>')
    if next_s:
        nav.append(f'<a class="btn-back" href="{vid_slug(next_s["id"])}.html">{esc(next_s["id"])} →</a>')

    return layout(f"{s['id']} — {s['title']}", 1, f"""
  {breadcrumb(1, [("Categories", "../categories/index.html"),
                  (c["name"], f"../categories/{c['slug']}.html"),
                  (s["id"], None)])}
  <div class="uc-head">
    <div class="uc-id">{esc(s['id'])}</div>
    <div>
      <h2 class="uc-title">{esc(s['title'])}</h2>
      <div class="chips">{cat_badge(s)}{fmt_badge(s['format'])}
        <span class="chip">⏱ {esc(s['duration'])}</span>
        <span class="chip">🎯 {esc(s['audience'])}</span></div>
    </div>
  </div>

  <div class="panel">
    <h3>Objectif du film</h3>
    <p>{esc(s['objective'])}</p>
  </div>

  <div class="panel">
    <h3>Hook (3 premieres secondes)</h3>
    <p class="hook">« {esc(s['hook'])} »</p>
  </div>

  <div class="panel">
    <h3>Decoupage</h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Temps</th><th>Voix off</th><th>Plan / image</th><th>Texte a l'ecran</th></tr></thead>
        <tbody>{''.join(beats)}</tbody>
      </table>
    </div>
  </div>

  <div class="two-col">
    <div class="panel">
      <h3>Appel a l'action</h3>
      <p>{esc(s['cta'])}</p>
    </div>
    <div class="panel">
      <h3>Son & musique</h3>
      <p>{esc(s['music'])}</p>
    </div>
  </div>

  <div class="panel">
    <h3>Notes de production</h3>
    <p>{esc(s['notes'])}</p>
    <div class="chips">{tags}</div>
  </div>

  {status_panel(s)}

  <div class="uc-nav">{''.join(nav)}</div>
""")


def status_panel(s: dict) -> str:
    """Panneau de suivi de production d'un script (statut + historique)."""
    sid = s["id"]
    st = status_of(sid)
    code = status_code(sid)
    d = STATUS_DEFS.get(code, {"label": code, "color": "#64748b", "desc": ""})

    # Frise des 4 statuts : etapes franchies / restantes.
    try:
        cur = STATUS_ORDER.index(code)
    except ValueError:
        cur = 0
    steps = []
    for i, c in enumerate(STATUS_ORDER):
        sd = STATUS_DEFS.get(c, {"label": c, "color": "#64748b"})
        done = i <= cur
        steps.append(
            f'<li class="step{" step-done" if done else ""}" style="--step:{sd["color"]}">'
            f'<span class="step-dot">{i + 1}</span>'
            f'<span class="step-label">{esc(sd["label"])}</span></li>')

    hist = st.get("historique") or []
    hrows = []
    for h in reversed(hist):
        hd = STATUS_DEFS.get(h.get("statut", ""), {"label": h.get("statut", ""), "color": "#64748b"})
        hrows.append(f"""
        <tr>
          <td class="nowrap">{esc(h.get('date', ''))}</td>
          <td class="nowrap"><span class="badge" style="background:{hd['color']}1a;color:{hd['color']};border-color:{hd['color']}33">{esc(hd['label'])}</span></td>
          <td>{esc(h.get('auteur', ''))}</td>
          <td>{esc(h.get('observation', ''))}</td>
          <td class="nowrap">{esc(h.get('action', ''))}</td>
        </tr>""")
    hist_block = ("""
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Date</th><th>Statut</th><th>Auteur</th><th>Observation</th><th>Action</th></tr></thead>
        <tbody>""" + "".join(hrows) + """</tbody>
      </table>
    </div>""") if hrows else '<p class="muted">Aucun historique enregistre.</p>'

    return f"""
  <div class="panel status-panel" style="--accent:{d['color']}">
    <h3>Suivi de production {status_badge(sid)}</h3>
    <p class="muted small">{esc(d.get('desc', ''))}</p>
    <ol class="status-steps">{''.join(steps)}</ol>
    <p><strong>Observation :</strong> {esc(st.get('observation', 'Aucune observation.'))}</p>
    <p class="muted small">Derniere modification : {esc(st.get('date_modification', 'n/a'))}
      par {esc(st.get('auteur', 'n/a'))}.</p>
    <details class="hist-details">
      <summary>Historique ({len(hist)} entree{'s' if len(hist) > 1 else ''})</summary>
      {hist_block}
    </details>
    <p class="muted small">Source : <code>{STATUS_FILENAME}</code> — modifiable directement sur GitHub,
      le site est regenere au prochain deploiement.</p>
  </div>"""


def page_calendrier() -> str:
    """Calendrier de tournage : les scripts dans l'ordre reel de production."""
    cal = META.get("calendrier") or {}
    vagues = cal.get("vagues") or []
    counts = status_counts()

    blocks = []
    n = 0
    for v in vagues:
        items = []
        for sid in v.get("scripts", []):
            s = SCRIPT_BY_ID.get(sid)
            if not s:
                continue
            n += 1
            c = cat_of(s)
            items.append(f"""
            <tr data-status="{esc(status_code(sid))}">
              <td class="center nowrap"><strong>{n}</strong></td>
              <td class="nowrap"><a class="uc-link" href="scripts/{vid_slug(sid)}.html">{esc(sid)}</a></td>
              <td><a class="uc-link" href="scripts/{vid_slug(sid)}.html">{esc(s['title'])}</a></td>
              <td>{cat_badge(s)}</td>
              <td class="center">{fmt_badge(s['format'])}</td>
              <td class="center nowrap">{esc(s['duration'])}</td>
              <td class="center nowrap">{status_badge(sid)}</td>
            </tr>""")
        blocks.append(f"""
    <section class="panel vague" style="border-left:4px solid var(--brand)">
      <div class="vague-head">
        <span class="vague-id">{esc(v['id'])}</span>
        <div>
          <h3>{esc(v['name'])}</h3>
          <p class="muted small">{esc(v.get('goal', ''))}</p>
        </div>
        <span class="hist-count-badge">{len(v.get('scripts', []))}</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th class="center">#</th><th>ID</th><th>Titre</th><th>Categorie</th>
            <th class="center">Format</th><th class="center">Duree</th><th class="center">Statut</th></tr></thead>
          <tbody>{''.join(items)}</tbody>
        </table>
      </div>
      <p class="muted small"><strong>Pourquoi cet ordre :</strong> {esc(v.get('rationale', ''))}</p>
    </section>""")

    stat_cards = "".join(
        f'<div class="stat stat-light" style="--accent:{STATUS_DEFS.get(c, {}).get("color", "#64748b")}">'
        f'<span class="stat-num">{counts.get(c, 0)}</span>'
        f'<span class="stat-label">{esc(STATUS_DEFS.get(c, {}).get("label", c))}</span></div>'
        for c in STATUS_ORDER)

    return layout("Calendrier de tournage", 0, f"""
  <p class="lead">Les {n} scripts dans leur ordre reel de production, regroupes en vagues.
    Une vague ne demarre pas avant que la precedente soit montee : chaque film maitre alimente
    ses derives. Le statut de chaque script est celui du fichier <code>{STATUS_FILENAME}</code>.</p>

  <div class="stats stats-light">{stat_cards}</div>

  <div class="panel">
    <h3>Regle d'ordonnancement</h3>
    <p>{esc(cal.get('note', ''))}</p>
  </div>

  {''.join(blocks)}
""", "Ordre de production des videos de promotion, vague par vague.")


def page_recap() -> str:
    rows = []
    for s in SCRIPTS:
        c = cat_of(s)
        rows.append(f"""
        <tr data-cat="{esc(s['category'])}" data-format="{esc(s['format'])}" data-status="{esc(status_code(s['id']))}">
          <td class="nowrap"><a class="uc-link" href="scripts/{vid_slug(s['id'])}.html">{esc(s['id'])}</a></td>
          <td><a class="uc-link" href="scripts/{vid_slug(s['id'])}.html">{esc(s['title'])}</a></td>
          <td>{esc(c['name'])}</td>
          <td class="center">{esc(s['format'])}</td>
          <td class="center nowrap">{esc(s['duration'])}</td>
          <td class="center nowrap">{status_badge(s['id'])}</td>
          <td>{esc(s['audience'])}</td>
          <td>{esc(s['cta'])}</td>
        </tr>""")
    return layout("Recapitulatif des scripts", 0, f"""
  <p class="lead">Vue unique de tous les scripts video : identifiant, categorie, format, duree,
    audience et appel a l'action. Utilisez les filtres pour preparer un tournage ou une campagne.</p>
  <div class="toolbar">
    <input class="search" id="q" type="search" placeholder="Rechercher...">
    <select class="select" id="f-cat">
      <option value="">Toutes les categories</option>
      {''.join(f'<option value="{esc(c["slug"])}">{esc(c["name"])}</option>' for c in CATEGORIES)}
    </select>
    <select class="select" id="f-format">
      <option value="">Tous les formats</option>
      <option value="16:9">16:9</option>
      <option value="9:16">9:16</option>
    </select>
    <select class="select" id="f-status">
      <option value="">Tous les statuts</option>
      {''.join(f'<option value="{esc(c)}">{esc(STATUS_DEFS.get(c, {}).get("label", c))}</option>' for c in STATUS_ORDER)}
    </select>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="tbl">
      <thead><tr><th>ID</th><th>Titre</th><th>Categorie</th><th class="center">Format</th>
        <th class="center">Duree</th><th class="center">Statut</th><th>Audience</th><th>CTA</th></tr></thead>
      <tbody>{''.join(rows)}</tbody>
    </table>
  </div>
  <p class="empty-hint" id="empty" hidden>Aucun script ne correspond a ce filtre.</p>
""")


def build_markdown() -> str:
    lines = ["# Scripts marketing — leopardo-hr", "",
             f"_{len(SCRIPTS)} scripts video, {len(CATEGORIES)} categories. "
             f"Source de verite : `{GITHUB_DATA_PATH}`._", ""]

    # Calendrier de tournage en tete : c'est l'ordre de production.
    cal = META.get("calendrier") or {}
    if cal.get("vagues"):
        lines += ["## Calendrier de tournage", "", cal.get("note", ""), ""]
        n = 0
        for v in cal["vagues"]:
            lines += [f"### {v['id']} — {v['name']}", "", v.get("goal", ""), "",
                      "| # | ID | Titre | Statut |", "|---|---|---|---|"]
            for sid in v.get("scripts", []):
                s = SCRIPT_BY_ID.get(sid)
                if not s:
                    continue
                n += 1
                lbl = STATUS_DEFS.get(status_code(sid), {}).get("label", status_code(sid))
                lines.append(f"| {n} | {sid} | {s['title']} | {lbl} |")
            lines += ["", f"**Pourquoi cet ordre :** {v.get('rationale', '')}", ""]

    for c in CATEGORIES:
        items = [s for s in SCRIPTS if s["category"] == c["slug"]]
        lines += [f"## {c['icon']} {c['name']}", "", c["desc"], ""]
        for s in items:
            lines += [f"### {s['id']} — {s['title']}", "",
                      f"- **Format :** {s['format']} · **Duree :** {s['duration']} · "
                      f"**Audience :** {s['audience']}",
                      f"- **Statut de production :** "
                      f"{STATUS_DEFS.get(status_code(s['id']), {}).get('label', status_code(s['id']))}",
                      f"- **Objectif :** {s['objective']}",
                      f"- **Hook :** « {s['hook']} »", "",
                      "| Temps | Voix off | Plan | Texte a l'ecran |", "|---|---|---|---|"]
            for b in s.get("beats", []):
                lines.append(f"| {b['t']} | {b['vo']} | {b['shot']} | {b.get('text','')} |")
            lines += ["", f"**CTA :** {s['cta']}", "",
                      f"**Son :** {s['music']}", "",
                      f"**Notes :** {s['notes']}", ""]
    return "\n".join(lines) + "\n"


# ---------------------------------------------------------------------------
# ASSETS
# ---------------------------------------------------------------------------

CSS = """
:root{
  --bg:#f6f7fb; --surface:#ffffff; --ink:#0f172a; --muted:#64748b;
  --line:#e2e8f0; --brand:#0f766e; --brand-2:#115e59; --accent:#0f766e;
  --radius:14px; --shadow:0 1px 2px rgba(15,23,42,.06),0 8px 24px rgba(15,23,42,.06);
  --max:1180px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  color:var(--ink);background:var(--bg);line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--brand);text-decoration:none}
a:hover{text-decoration:underline}
code{background:#eef2f7;padding:.1em .35em;border-radius:5px;font-size:.9em}
.wrap{max-width:var(--max);margin:0 auto;padding:0 20px}
.topbar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--line)}
.topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;height:64px}
.brand{display:flex;align-items:center;gap:10px;color:var(--ink);font-weight:700}
.brand:hover{text-decoration:none}
.brand-mark{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;
  background:linear-gradient(135deg,var(--brand),#0ea5a4);color:#fff;font-weight:800}
.brand-text{font-size:15px;line-height:1.1}
.brand-text small{display:block;font-weight:500;color:var(--muted);font-size:11px}
.mainnav{display:flex;gap:4px;flex-wrap:wrap}
.mainnav a{padding:8px 12px;border-radius:9px;color:#334155;font-size:14px;font-weight:500}
.mainnav a:hover{background:#eef2f7;text-decoration:none}
main{padding:28px 20px 60px}
.page-title{font-size:30px;line-height:1.2;margin:6px 0 4px;letter-spacing:-.02em}
.page-sub{color:var(--muted);margin:0 0 22px}
.lead{color:#334155;font-size:16px;margin:0 0 20px}
.muted{color:var(--muted)}
.breadcrumb{font-size:13px;color:var(--muted);margin-bottom:14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.breadcrumb .sep{color:#cbd5e1}
.breadcrumb span[aria-current]{color:var(--ink);font-weight:600}
.hero{background:linear-gradient(135deg,#0f766e,#0e7490 55%,#1d4ed8);color:#fff;border-radius:20px;
  padding:38px 34px;box-shadow:var(--shadow);margin-bottom:34px}
.hero-kicker{text-transform:uppercase;letter-spacing:.14em;font-size:12px;opacity:.85;margin:0 0 8px}
.hero-title{font-size:34px;line-height:1.15;margin:0 0 12px;letter-spacing:-.02em}
.hero-lead{max-width:760px;opacity:.95;margin:0 0 22px;font-size:16px}
.stats{display:flex;flex-wrap:wrap;gap:14px}
.stat{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:12px;
  padding:12px 18px;min-width:110px}
.stat-num{display:block;font-size:24px;font-weight:800;line-height:1.1}
.stat-label{font-size:12px;opacity:.9}
.section{margin-bottom:40px}
.section-title{font-size:21px;margin:0 0 4px;letter-spacing:-.01em}
.section-sub{color:var(--muted);margin:0 0 18px;font-size:14px}
.grid{display:grid;gap:16px}
.grid-profiles{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
.grid-modules{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
.card{display:block;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);
  padding:18px;box-shadow:var(--shadow);color:var(--ink);transition:transform .15s ease,box-shadow .15s ease,border-color .15s}
.card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(15,23,42,.10);border-color:#cbd5e1;text-decoration:none}
.card-icon{font-size:24px;margin-bottom:8px}
.card h3{margin:0 0 6px;font-size:16px}
.card p{margin:0 0 10px;color:var(--muted);font-size:13.5px}
.card-meta{font-size:12px;font-weight:600;color:var(--accent)}
.profile-card{border-top:3px solid var(--accent,var(--brand))}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.chip{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1px solid var(--line);
  border-radius:999px;padding:4px 11px;font-size:12.5px;color:#334155}
.badge{display:inline-flex;align-items:center;gap:5px;border:1px solid transparent;border-radius:999px;
  padding:3px 10px;font-size:12px;font-weight:600}
.table-wrap{overflow-x:auto;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);
  box-shadow:var(--shadow)}
.data-table{width:100%;border-collapse:collapse;font-size:14px}
.data-table th,.data-table td{padding:11px 13px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}
.data-table thead th{background:#f8fafc;font-size:12px;text-transform:uppercase;
  letter-spacing:.05em;color:#475569;font-weight:700}
.data-table tbody tr:hover{background:#f8fafc}
.data-table tbody tr:last-child td{border-bottom:none}
.center{text-align:center}
.nowrap{white-space:nowrap;font-variant-numeric:tabular-nums}
.uc-link{font-weight:600}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.search,.select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;
  font-size:14px;color:var(--ink);outline:none}
.search{flex:1;min-width:220px}
.search:focus,.select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(15,118,110,.12)}
.empty-hint{color:var(--muted);text-align:center;padding:18px}
.profile-head{display:flex;gap:18px;align-items:flex-start;background:var(--surface);
  border:1px solid var(--line);border-left:4px solid var(--accent,var(--brand));border-radius:var(--radius);
  padding:20px;box-shadow:var(--shadow);margin-bottom:22px}
.profile-icon{font-size:34px;line-height:1}
.profile-desc{margin:0 0 8px;color:#334155}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:26px}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;
  box-shadow:var(--shadow);margin-bottom:18px}
.panel h3{margin:0 0 10px;font-size:16px}
.plain{margin:0;padding-left:18px}
.plain li{margin:4px 0}
.uc-head{display:flex;gap:16px;align-items:flex-start;margin-bottom:20px}
.uc-id{background:var(--brand);color:#fff;font-weight:800;border-radius:12px;padding:10px 14px;font-size:15px;
  white-space:nowrap}
.uc-title{margin:0 0 6px;font-size:24px;letter-spacing:-.01em}
.hook{font-size:17px;font-style:italic;color:#0f172a;margin:0;padding-left:14px;border-left:3px solid var(--brand)}
.ontext{display:inline-block;background:#0f172a;color:#fff;border-radius:6px;padding:2px 8px;font-size:12.5px}
.hist-count-badge{display:inline-block;min-width:24px;padding:1px 8px;border-radius:999px;
  background:#0d9488;color:#fff;font-size:12.5px;font-weight:700;vertical-align:middle}
/* --- suivi de production --- */
.status-panel{border-left:4px solid var(--accent,var(--brand))}
.status-badge{white-space:nowrap}
.status-steps{display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:12px 0 16px;padding:0}
.status-steps .step{display:flex;align-items:center;gap:7px;background:#f8fafc;border:1px solid var(--line);
  border-radius:999px;padding:5px 13px 5px 6px;font-size:12.5px;color:var(--muted)}
.status-steps .step-done{background:color-mix(in srgb,var(--step) 12%,#fff);
  border-color:color-mix(in srgb,var(--step) 35%,#fff);color:var(--step);font-weight:600}
.step-dot{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;
  background:#e2e8f0;color:#64748b;font-size:11px;font-weight:700}
.step-done .step-dot{background:var(--step);color:#fff}
.hist-details{margin-top:10px}
.hist-details summary{cursor:pointer;font-size:13.5px;font-weight:600;color:var(--brand);padding:6px 0}
.hist-details .table-wrap{margin-top:8px}
/* --- calendrier --- */
.stats-light{margin:0 0 22px}
.stat-light{background:var(--surface);border:1px solid var(--line);border-top:3px solid var(--accent,var(--brand));
  border-radius:12px;padding:12px 18px;min-width:110px;box-shadow:var(--shadow)}
.stat-light .stat-num{color:var(--ink)}
.stat-light .stat-label{color:var(--muted)}
.vague{margin-bottom:20px}
.vague-head{display:flex;gap:14px;align-items:flex-start;margin-bottom:12px}
.vague-head h3{margin:0 0 2px}
.vague-id{display:grid;place-items:center;min-width:44px;height:44px;border-radius:12px;
  background:linear-gradient(135deg,var(--brand),#0ea5a4);color:#fff;font-weight:800;font-size:15px}
.vague-head .hist-count-badge{margin-left:auto}
.small{font-size:12px}
.uc-nav{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.btn-back{display:inline-block;background:#fff;border:1px solid var(--line);border-radius:10px;
  padding:8px 14px;font-size:13.5px;color:#334155}
.btn-back:hover{background:#f8fafc;text-decoration:none}
.footer{border-top:1px solid var(--line);background:#fff;padding:22px 0;margin-top:20px}
.footer p{margin:0;color:var(--muted);font-size:13px}
@media (max-width:820px){
  .two-col{grid-template-columns:1fr}
  .hero{padding:28px 22px}
  .hero-title{font-size:26px}
  .page-title{font-size:24px}
  .topbar-inner{height:auto;padding:10px 0;flex-direction:column;align-items:flex-start}
  .mainnav{width:100%}
  /* Sur mobile, un tableau ne se comprime pas : il defile dans son conteneur.
     Sans cela les colonnes Titre/Categorie s'ecrasent en 5 lignes et la
     lecture devient penible (constate au test 390 px du 2026-11-26). */
  .data-table{min-width:760px}
  .table-wrap{-webkit-overflow-scrolling:touch}
  .vague-head{flex-wrap:wrap}
  .vague-head .hist-count-badge{margin-left:0}
}
@media print{
  .topbar,.footer,.toolbar,.uc-nav{display:none}
  body{background:#fff}
  .card,.panel,.table-wrap{box-shadow:none}
}
"""

JS = """
(function(){
  var q = document.getElementById('q');
  var fCat = document.getElementById('f-cat');
  var fFmt = document.getElementById('f-format');
  var fSta = document.getElementById('f-status');
  var tbl = document.getElementById('tbl');
  var empty = document.getElementById('empty');
  if(!tbl) return;
  var rows = Array.prototype.slice.call(tbl.querySelectorAll('tbody tr'));
  function apply(){
    var term = (q && q.value || '').toLowerCase().trim();
    var cat = fCat && fCat.value || '';
    var fmt = fFmt && fFmt.value || '';
    var sta = fSta && fSta.value || '';
    var shown = 0;
    rows.forEach(function(tr){
      var ok = true;
      if(cat && tr.getAttribute('data-cat') !== cat) ok = false;
      if(fmt && tr.getAttribute('data-format') !== fmt) ok = false;
      if(sta && tr.getAttribute('data-status') !== sta) ok = false;
      if(term && tr.textContent.toLowerCase().indexOf(term) === -1) ok = false;
      tr.hidden = !ok;
      if(ok) shown++;
    });
    if(empty) empty.hidden = shown !== 0;
  }
  [q, fCat, fFmt, fSta].forEach(function(el){ if(el) el.addEventListener('input', apply); });
  apply();
})();
"""


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------

def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=None, help="Dossier de sortie (defaut : dossier du script)")
    args = ap.parse_args()
    out = Path(args.out) if args.out else HERE
    out.mkdir(parents=True, exist_ok=True)
    (out / "categories").mkdir(exist_ok=True)
    (out / "scripts").mkdir(exist_ok=True)
    (out / "assets").mkdir(exist_ok=True)

    (out / "index.html").write_text(page_home(), encoding="utf-8")
    (out / "recap.html").write_text(page_recap(), encoding="utf-8")
    (out / "calendrier.html").write_text(page_calendrier(), encoding="utf-8")
    (out / "categories" / "index.html").write_text(page_categories_index(), encoding="utf-8")
    for c in CATEGORIES:
        (out / "categories" / f"{c['slug']}.html").write_text(page_category(c["slug"]), encoding="utf-8")
    for s in SCRIPTS:
        (out / "scripts" / f"{vid_slug(s['id'])}.html").write_text(page_script(s), encoding="utf-8")
    (out / "assets" / "marketing.css").write_text(CSS, encoding="utf-8")
    (out / "assets" / "marketing.js").write_text(JS, encoding="utf-8")
    (out / "scripts-video.md").write_text(build_markdown(), encoding="utf-8")

    # La source de verite n'est jamais ecrasee : on la recopie si la sortie differe.
    dest_data = out / DATA_FILENAME
    if dest_data.resolve() != (HERE / DATA_FILENAME).resolve():
        shutil.copyfile(HERE / DATA_FILENAME, dest_data)
    dest_status = out / STATUS_FILENAME
    if dest_status.resolve() != (HERE / STATUS_FILENAME).resolve() and (HERE / STATUS_FILENAME).exists():
        shutil.copyfile(HERE / STATUS_FILENAME, dest_status)

    n_pages = 4 + len(CATEGORIES) + len(SCRIPTS)
    print(f"Espace scripts marketing genere dans {out} — {n_pages} pages HTML, "
          f"{len(SCRIPTS)} scripts, {len(CATEGORIES)} categories.")


if __name__ == "__main__":
    main()
