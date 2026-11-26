#!/usr/bin/env python3
"""Genere l'espace « Scripts marketing » de leopardo-hr.

Espace jumeau du portail de recette (`docs/recette/`) : meme charte, meme
navigation, meme logique de publication GitHub Pages. Il rassemble les scripts
des videos de promotion de la solution, organises et classes par categorie.

MULTILINGUE (v3)
----------------
L'espace est publie dans les 4 locales du produit (fr, en, tr, ar), celles de
`shared/i18n/locales/`. Le francais reste a la racine (`/marketing/`) pour ne
casser aucun lien existant ; les autres locales vivent dans `/marketing/<loc>/`
avec la meme arborescence. Chaque page porte ses balises `hreflang` et un
selecteur de langue. L'arabe est servi en `dir="rtl"`.

Sources de verite (jamais ecrasees par ce script) :
  scripts-video.json        — categories, scripts, calendrier (contenu FR)
  statuts-tournage.json     — suivi de production
  i18n/ui.json              — libelles d'interface, 4 locales
  i18n/content-<loc>.json   — contenu traduit (title, objective, hook, cta, beats)

Les champs non traduits (shot, music, notes) restent en francais et sont
signales par un badge FR : ce sont des consignes de tournage internes.

Sortie (par defaut dans le dossier de ce script) :
  index.html                     — accueil FR (racine, liens historiques)
  categories/index.html          — liste des categories
  categories/<slug>.html         — page par categorie
  scripts/<vid-id>.html          — fiche complete d'un script
  recap.html                     — tableau recapitulatif filtrable
  calendrier.html                — calendrier de tournage + export CSV
  calendrier-tournage.csv        — export production (BOM UTF-8, ';')
  assets/marketing.css|.js       — feuille de style et filtres
  scripts-video.md               — export Markdown (FR)
  <loc>/...                      — meme arborescence pour en, tr, ar

Usage :
  python3 build_marketing.py [--out DIR]
"""
from __future__ import annotations

import argparse
import csv
import html
import io
import json
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
DATA_FILENAME = "scripts-video.json"
STATUS_FILENAME = "statuts-tournage.json"
CSV_FILENAME = "calendrier-tournage.csv"
I18N_DIR = HERE / "i18n"
UI_FILENAME = "ui.json"
GITHUB_REPO = "Kitokoh/leopardo-hr"
GITHUB_DATA_PATH = "docs/marketing/scripts-video.json"

LOCALES = ("fr", "en", "tr", "ar")
DEFAULT_LOCALE = "fr"
RTL_LOCALES = ("ar",)
LOCALE_SHORT = {"fr": "FR", "en": "EN", "tr": "TR", "ar": "AR"}


def esc(s) -> str:
    return html.escape(str(s), quote=True)


# ---------------------------------------------------------------------------
# DONNEES
# ---------------------------------------------------------------------------

def load_data() -> dict:
    raw = json.loads((HERE / DATA_FILENAME).read_text(encoding="utf-8"))
    return {"categories": raw.get("categories") or [],
            "scripts": raw.get("scripts") or [],
            "meta": raw}


DATA = load_data()
CATEGORIES = DATA["categories"]
SCRIPTS = DATA["scripts"]
META = DATA["meta"]

CAT_BY_SLUG = {c["slug"]: c for c in CATEGORIES}
SCRIPT_BY_ID = {s["id"]: s for s in SCRIPTS}


def load_json(path: Path, default: dict) -> dict:
    if not path.exists():
        return default
    return json.loads(path.read_text(encoding="utf-8"))


UI = load_json(I18N_DIR / UI_FILENAME, {"fr": {}})
CONTENT = {loc: load_json(I18N_DIR / f"content-{loc}.json", {})
           for loc in LOCALES if loc != DEFAULT_LOCALE}


def t(loc: str, key: str, **kw) -> str:
    """Libelle d'interface dans la locale demandee, repli sur le francais."""
    val = (UI.get(loc) or {}).get(key)
    if val is None:
        val = (UI.get(DEFAULT_LOCALE) or {}).get(key, key)
    if kw:
        try:
            val = val.format(**kw)
        except (KeyError, IndexError):
            pass
    return val


def cfield(loc: str, sid: str, field: str):
    """Champ de contenu traduit. Retourne (texte, est_un_repli_fr)."""
    base = SCRIPT_BY_ID[sid].get(field, "")
    if loc == DEFAULT_LOCALE:
        return base, False
    node = ((CONTENT.get(loc) or {}).get("scripts") or {}).get(sid) or {}
    if field in node and node[field]:
        return node[field], False
    return base, True


def cbeats(loc: str, sid: str):
    """Decoupage traduit : liste de dicts {t, vo, shot, text, fr_vo, fr_text}."""
    base = SCRIPT_BY_ID[sid].get("beats", [])
    if loc == DEFAULT_LOCALE:
        return [{"t": b["t"], "vo": b["vo"], "shot": b["shot"],
                 "text": b.get("text", ""), "fr_vo": False, "fr_text": False}
                for b in base]
    node = ((CONTENT.get(loc) or {}).get("scripts") or {}).get(sid) or {}
    tr_beats = node.get("beats") or []
    out = []
    for i, b in enumerate(base):
        tb = tr_beats[i] if i < len(tr_beats) else {}
        vo = tb.get("vo") or b["vo"]
        tx = tb.get("text") or b.get("text", "")
        out.append({"t": b["t"], "vo": vo, "shot": b["shot"], "text": tx,
                    "fr_vo": not bool(tb.get("vo")),
                    "fr_text": not bool(tb.get("text"))})
    return out


def ccat(loc: str, slug: str) -> dict:
    base = CAT_BY_SLUG.get(slug, {"slug": slug, "name": slug, "icon": "🎬",
                                  "color": "#0f766e", "desc": ""})
    if loc == DEFAULT_LOCALE:
        return base
    node = ((CONTENT.get(loc) or {}).get("categories") or {}).get(slug) or {}
    out = dict(base)
    out["name"] = node.get("name") or base["name"]
    out["desc"] = node.get("desc") or base["desc"]
    return out


def cvague(loc: str, v: dict) -> dict:
    if loc == DEFAULT_LOCALE:
        return v
    node = ((CONTENT.get(loc) or {}).get("vagues") or {}).get(v.get("id", "")) or {}
    out = dict(v)
    for k in ("name", "goal", "rationale"):
        if node.get(k):
            out[k] = node[k]
    return out


# ---------------------------------------------------------------------------
# SUIVI DE PRODUCTION (statuts-tournage.json)
# ---------------------------------------------------------------------------

STATUS_DOC = load_json(HERE / STATUS_FILENAME, {"_meta": {"statuts": []}, "statuts": {}})
STATUS_DEFS = {s["code"]: s for s in (STATUS_DOC.get("_meta", {}).get("statuts") or [])}
STATUS_ORDER = [s["code"] for s in (STATUS_DOC.get("_meta", {}).get("statuts") or [])]


def status_of(sid: str) -> dict:
    return (STATUS_DOC.get("statuts") or {}).get(sid) or {}


def status_code(sid: str) -> str:
    return status_of(sid).get("statut") or "a_ecrire"


def status_label(loc: str, code: str) -> str:
    key = f"st_{code}"
    if key in (UI.get(loc) or {}):
        return t(loc, key)
    return STATUS_DEFS.get(code, {}).get("label", code)


def status_desc(loc: str, code: str) -> str:
    key = f"st_{code}_d"
    if key in (UI.get(loc) or {}):
        return t(loc, key)
    return STATUS_DEFS.get(code, {}).get("desc", "")


def status_badge(loc: str, sid: str) -> str:
    code = status_code(sid)
    d = STATUS_DEFS.get(code, {"color": "#64748b"})
    return (f'<span class="badge status-badge" style="background:{d["color"]}1a;color:{d["color"]};'
            f'border-color:{d["color"]}33">{esc(status_label(loc, code))}</span>')


def status_counts() -> dict:
    out = {c: 0 for c in STATUS_ORDER}
    for s in SCRIPTS:
        c = status_code(s["id"])
        out[c] = out.get(c, 0) + 1
    return out


# ---------------------------------------------------------------------------
# CHEMINS
# ---------------------------------------------------------------------------

def vid_slug(vid: str) -> str:
    return vid.lower().replace("_", "-")


def rel(depth: int) -> str:
    return "../" * depth


def loc_root(loc: str) -> str:
    """Prefixe relatif vers la racine de la locale (depuis la racine du site)."""
    return "" if loc == DEFAULT_LOCALE else f"{loc}/"


def href(loc: str, depth: int, page_rel: str, src: str | None = None) -> str:
    """Lien vers `page_rel` dans la locale `loc`, depuis une page de profondeur `depth`.

    `depth` est mesure depuis la RACINE DE LA LOCALE DE LA PAGE SOURCE (`src`,
    par defaut la locale cible). Une page d'une locale non par defaut est un
    niveau plus bas dans l'arbre du site (`/marketing/<loc>/`), donc son prefixe
    vers la racine du site compte un `../` de plus. Le decalage depend de la
    SOURCE, pas de la cible : c'est ce qui casse les liens du selecteur de
    langue si on l'oublie (222 liens casses mesures le 2026-11-26).
    """
    src = src or loc
    site_depth = depth + (0 if src == DEFAULT_LOCALE else 1)
    return rel(site_depth) + loc_root(loc) + page_rel


def site_href(loc: str, depth: int, page_rel: str) -> str:
    """Lien vers un livrable de NIVEAU SITE (racine /marketing/), pas de locale.

    Le CSV de tournage et l'export Markdown sont des livrables production : ils
    existent en une seule copie, en francais, a la racine. Les autres locales y
    renvoient donc sans prefixe de locale (sinon `ar/scripts-video.md`, absent).
    """
    site_depth = depth + (0 if loc == DEFAULT_LOCALE else 1)
    return rel(site_depth) + page_rel


def fr_badge(loc: str) -> str:
    if loc == DEFAULT_LOCALE:
        return ""
    return (f' <span class="fr-badge" title="{esc(t(loc, "fallback_legend"))}">'
            f'{esc(t(loc, "fallback_badge"))}</span>')


# ---------------------------------------------------------------------------
# LAYOUT
# ---------------------------------------------------------------------------

def layout(loc: str, title: str, depth: int, page_rel: str, body: str,
           subtitle: str = "") -> str:
    # Prefixe vers la racine du SITE (pas de la locale) : voir href().
    p = rel(depth + (0 if loc == DEFAULT_LOCALE else 1))
    sub = f'<p class="page-sub">{esc(subtitle)}</p>' if subtitle else ""
    direction = "rtl" if loc in RTL_LOCALES else "ltr"

    # hreflang : chaque page declare ses 4 variantes + x-default (fr).
    alts = [f'<link rel="alternate" hreflang="{L}" href="{href(L, depth, page_rel, src=loc)}">'
            for L in LOCALES]
    alts.append(f'<link rel="alternate" hreflang="x-default" '
                f'href="{href(DEFAULT_LOCALE, depth, page_rel, src=loc)}">')

    # Selecteur de langue : conserve la page courante.
    lang_items = []
    for L in LOCALES:
        cls = "lang-link is-current" if L == loc else "lang-link"
        cur = ' aria-current="true"' if L == loc else ""
        lang_items.append(
            f'<a class="{cls}" href="{href(L, depth, page_rel, src=loc)}" hreflang="{L}" '
            f'lang="{L}"{cur}>{esc(LOCALE_SHORT[L])}</a>')

    return f"""<!doctype html>
<html lang="{loc}" dir="{direction}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)} — {esc(t(loc, "brand"))} Leopardo</title>
{chr(10).join(alts)}
<link rel="stylesheet" href="{p}assets/marketing.css">
</head>
<body class="loc-{loc}">
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="{href(loc, depth, "index.html")}">
      <span class="brand-mark">L</span>
      <span class="brand-text">{esc(t(loc, "brand"))} <small>{esc(t(loc, "brand_sub"))}</small></span>
    </a>
    <nav class="mainnav">
      <a href="{href(loc, depth, "index.html")}">{esc(t(loc, "nav_home"))}</a>
      <a href="{href(loc, depth, "categories/index.html")}">{esc(t(loc, "nav_categories"))}</a>
      <a href="{href(loc, depth, "calendrier.html")}">{esc(t(loc, "nav_calendar"))}</a>
      <a href="{href(loc, depth, "recap.html")}">{esc(t(loc, "nav_recap"))}</a>
      <a href="{site_href(loc, depth, "scripts-video.md")}">{esc(t(loc, "nav_markdown"))}</a>
      <a href="{p}../recette/index.html">{esc(t(loc, "nav_portal"))}</a>
    </nav>
    <div class="langswitch" role="group" aria-label="{esc(t(loc, "lang_label"))}">
      {''.join(lang_items)}
    </div>
  </div>
</header>
<main class="wrap">
  <h1 class="page-title">{esc(title)}</h1>
  {sub}
  {body}
</main>
<footer class="footer">
  <div class="wrap">
    <p>{esc(t(loc, "brand"))} — <strong>leopardo-hr</strong> —
      <a href="https://github.com/{GITHUB_REPO}" target="_blank" rel="noopener">{GITHUB_REPO}</a>.
      <a href="https://github.com/{GITHUB_REPO}/blob/main/{GITHUB_DATA_PATH}">{GITHUB_DATA_PATH}</a>.</p>
  </div>
</footer>
<script src="{p}assets/marketing.js"></script>
</body>
</html>
"""


def breadcrumb(loc: str, depth: int, items: list) -> str:
    parts = [f'<a href="{href(loc, depth, "index.html")}">{esc(t(loc, "crumb_root"))}</a>']
    for label, target in items:
        if target:
            parts.append(f'<a href="{href(loc, depth, target)}">{esc(label)}</a>')
        else:
            parts.append(f'<span aria-current="page">{esc(label)}</span>')
    return '<nav class="breadcrumb">' + ' <span class="sep">›</span> '.join(parts) + "</nav>"


def fmt_badge(fmt: str) -> str:
    color = {"16:9": "#0f766e", "9:16": "#db2777", "1:1": "#7c3aed"}.get(fmt, "#475569")
    return (f'<span class="badge" style="background:{color}1a;color:{color};'
            f'border-color:{color}33">{esc(fmt)}</span>')


def cat_badge(loc: str, s: dict) -> str:
    c = ccat(loc, s["category"])
    return (f'<span class="badge" style="background:{c["color"]}1a;color:{c["color"]};'
            f'border-color:{c["color"]}33">{c["icon"]} {esc(c["name"])}</span>')


def n_scripts_label(loc: str, n: int) -> str:
    return f"{n} {t(loc, 'n_script' if n <= 1 else 'n_scripts')}"


# ---------------------------------------------------------------------------
# PAGES
# ---------------------------------------------------------------------------

def page_home(loc: str) -> str:
    total = len(SCRIPTS)
    n_cats = len(CATEGORIES)
    n_vert = sum(1 for s in SCRIPTS if s["category"] == "verticales")
    n_short = sum(1 for s in SCRIPTS if s["format"] == "9:16")

    cat_cards = []
    for c in CATEGORIES:
        cc = ccat(loc, c["slug"])
        n = sum(1 for s in SCRIPTS if s["category"] == c["slug"])
        cat_cards.append(f"""
        <a class="card profile-card" href="{href(loc, 0, f"categories/{c['slug']}.html")}" style="--accent:{c['color']}">
          <div class="card-icon">{c['icon']}</div>
          <h3>{esc(cc['name'])}</h3>
          <p>{esc(cc['desc'])}</p>
          <span class="card-meta">{esc(n_scripts_label(loc, n))}</span>
        </a>""")

    rows = []
    for s in SCRIPTS:
        title, _ = cfield(loc, s["id"], "title")
        aud, aud_fb = cfield(loc, s["id"], "audience")
        rows.append(f"""
        <tr data-cat="{esc(s['category'])}" data-format="{esc(s['format'])}" data-status="{esc(status_code(s['id']))}">
          <td class="nowrap"><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(s['id'])}.html")}">{esc(s['id'])}</a></td>
          <td><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(s['id'])}.html")}">{esc(title)}</a></td>
          <td>{cat_badge(loc, s)}</td>
          <td class="center">{fmt_badge(s['format'])}</td>
          <td class="center nowrap">{esc(s['duration'])}</td>
          <td class="center nowrap">{status_badge(loc, s['id'])}</td>
          <td>{esc(aud)}{fr_badge(loc) if aud_fb else ''}</td>
        </tr>""")

    return layout(loc, t(loc, "home_title"), 0, "index.html", f"""
  <section class="hero">
    <p class="hero-kicker">{esc(t(loc, "home_kicker"))}</p>
    <h2 class="hero-title">{esc(t(loc, "home_title"))}</h2>
    <p class="hero-lead">{esc(t(loc, "home_lead"))}</p>
    <div class="stats">
      <div class="stat"><span class="stat-num">{total}</span><span class="stat-label">{esc(t(loc, "stat_scripts"))}</span></div>
      <div class="stat"><span class="stat-num">{n_cats}</span><span class="stat-label">{esc(t(loc, "stat_categories"))}</span></div>
      <div class="stat"><span class="stat-num">{n_vert}</span><span class="stat-label">{esc(t(loc, "stat_verticals"))}</span></div>
      <div class="stat"><span class="stat-num">{n_short}</span><span class="stat-label">{esc(t(loc, "stat_short"))}</span></div>
    </div>
  </section>

  <section class="section">
    <h2 class="section-title">{esc(t(loc, "sec_by_cat"))}</h2>
    <p class="section-sub">{esc(t(loc, "sec_by_cat_sub"))}</p>
    <div class="grid grid-profiles">{''.join(cat_cards)}</div>
  </section>

  <section class="section">
    <h2 class="section-title">{esc(t(loc, "sec_all"))}</h2>
    <p class="section-sub">{esc(t(loc, "sec_all_sub"))}</p>
    <div class="toolbar">
      <input class="search" id="q" type="search" placeholder="{esc(t(loc, "search_ph"))}">
      <select class="select" id="f-cat">
        <option value="">{esc(t(loc, "all_cats"))}</option>
        {''.join(f'<option value="{esc(c["slug"])}">{esc(ccat(loc, c["slug"])["name"])}</option>' for c in CATEGORIES)}
      </select>
      <select class="select" id="f-format">
        <option value="">{esc(t(loc, "all_formats"))}</option>
        <option value="16:9">16:9</option>
        <option value="9:16">9:16</option>
      </select>
      <select class="select" id="f-status">
        <option value="">{esc(t(loc, "all_status"))}</option>
        {''.join(f'<option value="{esc(c)}">{esc(status_label(loc, c))}</option>' for c in STATUS_ORDER)}
      </select>
    </div>
    <div class="table-wrap">
      <table class="data-table" id="tbl">
        <thead><tr>
          <th>{esc(t(loc, "th_id"))}</th><th>{esc(t(loc, "th_title"))}</th><th>{esc(t(loc, "th_cat"))}</th>
          <th class="center">{esc(t(loc, "th_format"))}</th>
          <th class="center">{esc(t(loc, "th_duration"))}</th><th class="center">{esc(t(loc, "th_status"))}</th>
          <th>{esc(t(loc, "th_audience"))}</th>
        </tr></thead>
        <tbody>{''.join(rows)}</tbody>
      </table>
    </div>
    <p class="empty-hint" id="empty" hidden>{esc(t(loc, "empty_hint"))}</p>
  </section>
""")


def page_categories_index(loc: str) -> str:
    cards = []
    for c in CATEGORIES:
        cc = ccat(loc, c["slug"])
        items = [s for s in SCRIPTS if s["category"] == c["slug"]]
        lis_parts = []
        for s in items:
            s_url = href(loc, 1, "scripts/" + vid_slug(s["id"]) + ".html")
            lis_parts.append(
                f'<li><a href="{s_url}">{esc(s["id"])} — '
                f'{esc(cfield(loc, s["id"], "title")[0])}</a> '
                f'<span class="muted small">({esc(s["duration"])})</span></li>')
        lis = "".join(lis_parts)
        cards.append(f"""
        <div class="panel" style="border-left:4px solid {c['color']}">
          <h3>{c['icon']} <a href="{href(loc, 1, f"categories/{c['slug']}.html")}">{esc(cc['name'])}</a>
            <span class="hist-count-badge">{len(items)}</span></h3>
          <p class="muted">{esc(cc['desc'])}</p>
          <ul class="plain">{lis}</ul>
        </div>""")
    return layout(loc, t(loc, "cats_title"), 1, "categories/index.html", f"""
  {breadcrumb(loc, 1, [(t(loc, "nav_categories"), None)])}
  <p class="lead">{esc(t(loc, "cats_lead"))}</p>
  {''.join(cards)}
""")


def page_category(loc: str, slug: str) -> str:
    c = CAT_BY_SLUG[slug]
    cc = ccat(loc, slug)
    items = [s for s in SCRIPTS if s["category"] == slug]
    cards = []
    for s in items:
        title, _ = cfield(loc, s["id"], "title")
        obj, _ = cfield(loc, s["id"], "objective")
        cards.append(f"""
        <a class="card" href="{href(loc, 1, f"scripts/{vid_slug(s['id'])}.html")}" style="--accent:{c['color']}">
          <div class="card-icon">{c['icon']}</div>
          <h3>{esc(s['id'])} — {esc(title)}</h3>
          <p>{esc(obj)}</p>
          <div class="chips">{fmt_badge(s['format'])}<span class="chip">{esc(s['duration'])}</span></div>
        </a>""")
    return layout(loc, cc["name"], 1, f"categories/{slug}.html", f"""
  {breadcrumb(loc, 1, [(t(loc, "nav_categories"), "categories/index.html"), (cc["name"], None)])}
  <div class="profile-head" style="--accent:{c['color']}">
    <div class="profile-icon">{c['icon']}</div>
    <div>
      <p class="profile-desc">{esc(cc['desc'])}</p>
      <div class="chips"><span class="chip">{esc(n_scripts_label(loc, len(items)))}</span></div>
    </div>
  </div>
  <div class="grid grid-modules">{''.join(cards)}</div>
""", cc["desc"])


def page_script(loc: str, s: dict) -> str:
    sid = s["id"]
    c = CAT_BY_SLUG.get(s["category"], {"slug": s["category"], "color": "#0f766e", "icon": "🎬"})
    cc = ccat(loc, s["category"])
    title, _ = cfield(loc, sid, "title")
    obj, _ = cfield(loc, sid, "objective")
    hook, _ = cfield(loc, sid, "hook")
    cta, _ = cfield(loc, sid, "cta")
    aud, _ = cfield(loc, sid, "audience")
    music, music_fr = cfield(loc, sid, "music")
    notes, notes_fr = cfield(loc, sid, "notes")

    beats = []
    for b in cbeats(loc, sid):
        beats.append(f"""
        <tr>
          <td class="nowrap"><strong>{esc(b['t'])}</strong></td>
          <td>{esc(b['vo'])}{fr_badge(loc) if b['fr_vo'] else ''}</td>
          <td>{esc(b['shot'])}{fr_badge(loc)}</td>
          <td><span class="ontext">{esc(b['text'])}</span>{fr_badge(loc) if b['fr_text'] else ''}</td>
        </tr>""")

    tags = "".join(f'<span class="chip">#{esc(x)}</span>' for x in s.get("tags", []))
    idx = [x["id"] for x in SCRIPTS].index(sid)
    prev_s = SCRIPTS[idx - 1] if idx > 0 else None
    next_s = SCRIPTS[idx + 1] if idx < len(SCRIPTS) - 1 else None
    nav = []
    if prev_s:
        prev_url = href(loc, 1, "scripts/" + vid_slug(prev_s["id"]) + ".html")
        nav.append(f'<a class="btn-back" href="{prev_url}">← {esc(prev_s["id"])}</a>')
    cat_url = href(loc, 1, "categories/" + c["slug"] + ".html")
    nav.append(f'<a class="btn-back" href="{cat_url}">{esc(t(loc, "all_cat_btn"))}</a>')
    if next_s:
        next_url = href(loc, 1, "scripts/" + vid_slug(next_s["id"]) + ".html")
        nav.append(f'<a class="btn-back" href="{next_url}">{esc(next_s["id"])} →</a>')

    return layout(loc, f"{sid} — {title}", 1, f"scripts/{vid_slug(sid)}.html", f"""
  {breadcrumb(loc, 1, [(t(loc, "nav_categories"), "categories/index.html"),
                       (cc["name"], f"categories/{c['slug']}.html"),
                       (sid, None)])}
  <div class="uc-head">
    <div class="uc-id">{esc(sid)}</div>
    <div>
      <h2 class="uc-title">{esc(title)}</h2>
      <div class="chips">{cat_badge(loc, s)}{fmt_badge(s['format'])}
        <span class="chip">⏱ {esc(s['duration'])}</span>
        <span class="chip">🎯 {esc(aud)}</span></div>
    </div>
  </div>

  <div class="panel">
    <h3>{esc(t(loc, "obj_title"))}</h3>
    <p>{esc(obj)}</p>
  </div>

  <div class="panel">
    <h3>{esc(t(loc, "hook_title"))}</h3>
    <p class="hook">« {esc(hook)} »</p>
  </div>

  <div class="panel">
    <h3>{esc(t(loc, "beats_title"))}</h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>{esc(t(loc, "th_time"))}</th><th>{esc(t(loc, "th_vo"))}</th>
          <th>{esc(t(loc, "th_shot"))}</th><th>{esc(t(loc, "th_text"))}</th></tr></thead>
        <tbody>{''.join(beats)}</tbody>
      </table>
    </div>
  </div>

  <div class="two-col">
    <div class="panel">
      <h3>{esc(t(loc, "cta_title"))}</h3>
      <p>{esc(cta)}</p>
    </div>
    <div class="panel">
      <h3>{esc(t(loc, "music_title"))}</h3>
      <p>{esc(music)}{fr_badge(loc) if music_fr else ''}</p>
    </div>
  </div>

  <div class="panel">
    <h3>{esc(t(loc, "notes_title"))}</h3>
    <p>{esc(notes)}{fr_badge(loc) if notes_fr else ''}</p>
    <div class="chips">{tags}</div>
  </div>

  {status_panel(loc, s)}

  <div class="uc-nav">{''.join(nav)}</div>
""")


def status_panel(loc: str, s: dict) -> str:
    sid = s["id"]
    st = status_of(sid)
    code = status_code(sid)
    d = STATUS_DEFS.get(code, {"color": "#64748b"})

    try:
        cur = STATUS_ORDER.index(code)
    except ValueError:
        cur = 0
    steps = []
    for i, c in enumerate(STATUS_ORDER):
        sd = STATUS_DEFS.get(c, {"color": "#64748b"})
        done = i <= cur
        steps.append(
            f'<li class="step{" step-done" if done else ""}" style="--step:{sd["color"]}">'
            f'<span class="step-dot">{i + 1}</span>'
            f'<span class="step-label">{esc(status_label(loc, c))}</span></li>')

    hist = st.get("historique") or []
    hrows = []
    for h in reversed(hist):
        hc = h.get("statut", "")
        hd = STATUS_DEFS.get(hc, {"color": "#64748b"})
        hrows.append(f"""
        <tr>
          <td class="nowrap">{esc(h.get('date', ''))}</td>
          <td class="nowrap"><span class="badge" style="background:{hd['color']}1a;color:{hd['color']};border-color:{hd['color']}33">{esc(status_label(loc, hc))}</span></td>
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
    </div>""") if hrows else f'<p class="muted">{esc(t(loc, "prod_none"))}</p>'

    return f"""
  <div class="panel status-panel" style="--accent:{d['color']}">
    <h3>{esc(t(loc, "prod_title"))} {status_badge(loc, sid)}</h3>
    <p class="muted small">{esc(status_desc(loc, code))}</p>
    <ol class="status-steps">{''.join(steps)}</ol>
    <p><strong>{esc(t(loc, "prod_obs"))} :</strong> {esc(st.get('observation', '—'))}</p>
    <p class="muted small">{esc(t(loc, "prod_lastmod"))} : {esc(st.get('date_modification', 'n/a'))}
      — {esc(st.get('auteur', 'n/a'))}.</p>
    <details class="hist-details">
      <summary>{esc(t(loc, "prod_hist"))} ({len(hist)} {esc(t(loc, "prod_entries"))})</summary>
      {hist_block}
    </details>
    <p class="muted small">{esc(t(loc, "prod_source"))} : <code>{STATUS_FILENAME}</code></p>
  </div>"""


def page_calendrier(loc: str) -> str:
    cal = META.get("calendrier") or {}
    vagues = cal.get("vagues") or []
    counts = status_counts()

    blocks = []
    n = 0
    for v in vagues:
        vv = cvague(loc, v)
        items = []
        for sid in v.get("scripts", []):
            s = SCRIPT_BY_ID.get(sid)
            if not s:
                continue
            n += 1
            title, _ = cfield(loc, sid, "title")
            items.append(f"""
            <tr data-status="{esc(status_code(sid))}">
              <td class="center nowrap"><strong>{n}</strong></td>
              <td class="nowrap"><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(sid)}.html")}">{esc(sid)}</a></td>
              <td><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(sid)}.html")}">{esc(title)}</a></td>
              <td>{cat_badge(loc, s)}</td>
              <td class="center">{fmt_badge(s['format'])}</td>
              <td class="center nowrap">{esc(s['duration'])}</td>
              <td class="center nowrap">{status_badge(loc, sid)}</td>
            </tr>""")
        blocks.append(f"""
    <section class="panel vague" style="border-left:4px solid var(--brand)">
      <div class="vague-head">
        <span class="vague-id">{esc(v['id'])}</span>
        <div>
          <h3>{esc(vv['name'])}</h3>
          <p class="muted small">{esc(vv.get('goal', ''))}</p>
        </div>
        <span class="hist-count-badge">{len(v.get('scripts', []))}</span>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th class="center">#</th><th>{esc(t(loc, "th_id"))}</th><th>{esc(t(loc, "th_title"))}</th>
            <th>{esc(t(loc, "th_cat"))}</th><th class="center">{esc(t(loc, "th_format"))}</th>
            <th class="center">{esc(t(loc, "th_duration"))}</th><th class="center">{esc(t(loc, "th_status"))}</th></tr></thead>
          <tbody>{''.join(items)}</tbody>
        </table>
      </div>
      <p class="muted small"><strong>{esc(t(loc, "cal_why"))}</strong> {esc(vv.get('rationale', ''))}</p>
    </section>""")

    stat_cards = "".join(
        f'<div class="stat stat-light" style="--accent:{STATUS_DEFS.get(c, {}).get("color", "#64748b")}">'
        f'<span class="stat-num">{counts.get(c, 0)}</span>'
        f'<span class="stat-label">{esc(status_label(loc, c))}</span></div>'
        for c in STATUS_ORDER)

    return layout(loc, t(loc, "cal_title"), 0, "calendrier.html", f"""
  <p class="lead">{esc(t(loc, "cal_lead", n=n))}</p>

  <div class="stats stats-light">{stat_cards}</div>

  <div class="panel export-panel">
    <h3>{esc(t(loc, "cal_export_title"))}</h3>
    <p>{esc(t(loc, "cal_export_desc"))}</p>
    <p class="export-actions">
      <a class="btn-export" href="{site_href(loc, 0, CSV_FILENAME)}" download="{CSV_FILENAME}">
        {esc(t(loc, "cal_download"))}
      </a>
      <span class="muted small">{n} &middot; {CSV_FILENAME}</span>
    </p>
  </div>

  <div class="panel">
    <h3>{esc(t(loc, "cal_rule_title"))}</h3>
    <p>{esc(cal.get('note', ''))}</p>
  </div>

  {''.join(blocks)}
""")


def build_calendrier_csv() -> str:
    """Calendrier de tournage au format CSV pour l'equipe production.

    Une ligne par script, dans l'ordre reel de production (vague puis position
    dans la vague). Separateur point-virgule et BOM UTF-8 : c'est ce qu'Excel
    attend en locale francaise, ou la virgule est le separateur decimal.
    """
    cal = META.get("calendrier") or {}
    vagues = cal.get("vagues") or []

    buf = io.StringIO()
    w = csv.writer(buf, delimiter=";", quoting=csv.QUOTE_MINIMAL, lineterminator="\r\n")
    w.writerow([
        "ordre", "vague", "vague_nom", "id", "titre", "categorie", "format",
        "duree", "statut", "statut_libelle", "audience", "objectif", "cta",
        "date_modification", "auteur",
    ])

    n = 0
    for v in vagues:
        for sid in v.get("scripts", []):
            s = SCRIPT_BY_ID.get(sid)
            if not s:
                continue
            n += 1
            c = CAT_BY_SLUG.get(s["category"], {"name": s.get("category", "")})
            code = status_code(sid)
            st = status_of(sid)
            w.writerow([
                n, v.get("id", ""), v.get("name", ""), sid, s.get("title", ""),
                c.get("name", s.get("category", "")), s.get("format", ""),
                s.get("duration", ""), code,
                STATUS_DEFS.get(code, {}).get("label", code),
                s.get("audience", ""), s.get("objective", ""), s.get("cta", ""),
                st.get("date_modification", ""), st.get("auteur", ""),
            ])
    return buf.getvalue()


def page_recap(loc: str) -> str:
    rows = []
    for s in SCRIPTS:
        title, _ = cfield(loc, s["id"], "title")
        aud, aud_fb = cfield(loc, s["id"], "audience")
        cta, _ = cfield(loc, s["id"], "cta")
        rows.append(f"""
        <tr data-cat="{esc(s['category'])}" data-format="{esc(s['format'])}" data-status="{esc(status_code(s['id']))}">
          <td class="nowrap"><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(s['id'])}.html")}">{esc(s['id'])}</a></td>
          <td><a class="uc-link" href="{href(loc, 0, f"scripts/{vid_slug(s['id'])}.html")}">{esc(title)}</a></td>
          <td>{esc(ccat(loc, s['category'])['name'])}</td>
          <td class="center">{esc(s['format'])}</td>
          <td class="center nowrap">{esc(s['duration'])}</td>
          <td class="center nowrap">{status_badge(loc, s['id'])}</td>
          <td>{esc(aud)}{fr_badge(loc) if aud_fb else ''}</td>
          <td>{esc(cta)}</td>
        </tr>""")
    return layout(loc, t(loc, "recap_title"), 0, "recap.html", f"""
  <p class="lead">{esc(t(loc, "recap_lead"))}</p>
  <div class="toolbar">
    <input class="search" id="q" type="search" placeholder="{esc(t(loc, "search_ph"))}">
    <select class="select" id="f-cat">
      <option value="">{esc(t(loc, "all_cats"))}</option>
      {''.join(f'<option value="{esc(c["slug"])}">{esc(ccat(loc, c["slug"])["name"])}</option>' for c in CATEGORIES)}
    </select>
    <select class="select" id="f-format">
      <option value="">{esc(t(loc, "all_formats"))}</option>
      <option value="16:9">16:9</option>
      <option value="9:16">9:16</option>
    </select>
    <select class="select" id="f-status">
      <option value="">{esc(t(loc, "all_status"))}</option>
      {''.join(f'<option value="{esc(c)}">{esc(status_label(loc, c))}</option>' for c in STATUS_ORDER)}
    </select>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="tbl">
      <thead><tr><th>{esc(t(loc, "th_id"))}</th><th>{esc(t(loc, "th_title"))}</th>
        <th>{esc(t(loc, "th_cat"))}</th><th class="center">{esc(t(loc, "th_format"))}</th>
        <th class="center">{esc(t(loc, "th_duration"))}</th><th class="center">{esc(t(loc, "th_status"))}</th>
        <th>{esc(t(loc, "th_audience"))}</th><th>{esc(t(loc, "th_cta"))}</th></tr></thead>
      <tbody>{''.join(rows)}</tbody>
    </table>
  </div>
  <p class="empty-hint" id="empty" hidden>{esc(t(loc, "empty_hint"))}</p>
""")


def build_markdown() -> str:
    lines = ["# Scripts marketing — leopardo-hr", "",
             f"_{len(SCRIPTS)} scripts video, {len(CATEGORIES)} categories. "
             f"Source de verite : `{GITHUB_DATA_PATH}`._", ""]

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
/* --- selecteur de langue --- */
.langswitch{display:flex;gap:2px;background:#f1f5f9;border:1px solid var(--line);
  border-radius:999px;padding:3px;flex-shrink:0}
.lang-link{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;
  font-weight:700;color:#475569;letter-spacing:.02em}
.lang-link:hover{background:#e2e8f0;text-decoration:none}
.lang-link.is-current{background:var(--brand);color:#fff}
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
.fr-badge{display:inline-block;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;
  border-radius:5px;padding:0 5px;font-size:10px;font-weight:800;vertical-align:middle;
  letter-spacing:.04em;line-height:1.5}
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
.export-panel{border-left:4px solid var(--brand)}
.export-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:12px 0 0}
.btn-export{display:inline-flex;align-items:center;gap:8px;background:var(--brand);color:#fff;
  font-weight:700;font-size:14px;text-decoration:none;border-radius:10px;padding:11px 18px;
  border:1px solid var(--brand);box-shadow:0 1px 2px rgba(15,23,42,.12)}
.btn-export:hover{filter:brightness(1.08)}
.btn-export::before{content:"\\2b07";font-size:15px;line-height:1}
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
/* --- RTL (arabe) --- */
body.loc-ar{direction:rtl}
body.loc-ar .data-table th,body.loc-ar .data-table td{text-align:right}
body.loc-ar .data-table .center{text-align:center}
body.loc-ar .hook{padding-left:0;padding-right:14px;border-left:none;border-right:3px solid var(--brand)}
body.loc-ar .plain{padding-left:0;padding-right:18px}
body.loc-ar .profile-head{border-left:1px solid var(--line);border-right:4px solid var(--accent,var(--brand))}
body.loc-ar .export-panel{border-left:1px solid var(--line);border-right:4px solid var(--brand)}
body.loc-ar .status-panel{border-left:1px solid var(--line);border-right:4px solid var(--accent,var(--brand))}
body.loc-ar .vague{border-left:1px solid var(--line)!important;border-right:4px solid var(--brand)}
body.loc-ar .vague-head .hist-count-badge{margin-left:0;margin-right:auto}
body.loc-ar .status-steps .step{padding:5px 6px 5px 13px}
@media (max-width:820px){
  .two-col{grid-template-columns:1fr}
  .hero{padding:28px 22px}
  .hero-title{font-size:26px}
  .page-title{font-size:24px}
  .topbar-inner{height:auto;padding:10px 0;flex-direction:column;align-items:flex-start}
  .mainnav{width:100%}
  .langswitch{align-self:flex-start}
  /* Tablette (768-820 px) : le tableau doit tenir dans son conteneur. Un
     min-width:760px force ici laissait 71 a 91 px de colonnes hors ecran sans
     aucun indice de defilement (mesure du 2026-11-26 a 768 px : conteneur
     669 px, tableau 760 px, colonne TEXTE coupee). On laisse donc le tableau
     se comprimer et le texte passer a la ligne ; le defilement horizontal
     n'est retabli qu'en dessous de 560 px, ou il est inevitable. */
  .data-table{min-width:0}
  .data-table th,.data-table td{padding:9px 8px;font-size:13px}
  .table-wrap{-webkit-overflow-scrolling:touch;scrollbar-width:thin}
  .table-wrap::-webkit-scrollbar{height:8px}
  .table-wrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
  /* Indicateur de defilement : ajoute par le JS quand le tableau depasse
     reellement son conteneur, pour qu'une colonne hors ecran ne soit jamais
     silencieuse. */
  .table-wrap.is-scrollable{position:relative}
  .table-wrap.is-scrollable::after{content:"\2194";position:absolute;top:6px;right:8px;
    font-size:12px;color:var(--muted);background:var(--surface);padding:0 4px;
    border-radius:4px;pointer-events:none}
  /* Calendrier : sur mobile, les colonnes secondaires sont masquees pour que
     # / ID / Titre / Statut tiennent sans defilement horizontal. Mesure du
     2026-11-26 a 390 px : tableau 760 px dans un conteneur de 288 px, la
     colonne Statut restait hors ecran. */
  .vague .data-table{min-width:0}
  .vague .data-table th:nth-child(4), .vague .data-table td:nth-child(4),
  .vague .data-table th:nth-child(5), .vague .data-table td:nth-child(5),
  .vague .data-table th:nth-child(6), .vague .data-table td:nth-child(6){display:none}
  .vague .data-table th:nth-child(3), .vague .data-table td:nth-child(3){min-width:0}
  .vague .data-table th, .vague .data-table td{padding-left:8px;padding-right:8px;font-size:12.5px}
  .vague-head{flex-wrap:wrap}
  .vague-head .hist-count-badge{margin-left:0}
}
/* Sous 560 px, un tableau a 4 colonnes ne peut plus se comprimer lisiblement :
   on retablit le defilement horizontal dans le conteneur (jamais sur la page),
   avec l'indicateur ajoute par le JS. Constat du test 390 px du 2026-11-26. */
@media (max-width:560px){
  .data-table{min-width:760px}
  .vague .data-table{min-width:0}
}
@media print{
  .topbar,.footer,.toolbar,.uc-nav,.langswitch{display:none}
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

/* Indicateur de defilement des tableaux.
   Un tableau plus large que son conteneur cache des colonnes : sans indice,
   l'utilisateur croit que la donnee s'arrete la. On marque donc chaque
   conteneur qui defile vraiment, et on remesure au redimensionnement (le
   passage tablette -> paysage change la largeur disponible). */
(function(){
  function mark(){
    var wraps = document.querySelectorAll('.table-wrap');
    for(var i=0;i<wraps.length;i++){
      var w = wraps[i];
      var t = w.querySelector('table');
      if(!t) continue;
      if(t.scrollWidth > w.clientWidth + 1){ w.classList.add('is-scrollable'); }
      else { w.classList.remove('is-scrollable'); }
    }
  }
  mark();
  window.addEventListener('resize', mark);
  window.addEventListener('orientationchange', mark);
})();
"""


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------

def write_locale(out: Path, loc: str) -> int:
    """Ecrit l'arborescence complete d'une locale. Retourne le nombre de pages."""
    root = out if loc == DEFAULT_LOCALE else out / loc
    (root / "categories").mkdir(parents=True, exist_ok=True)
    (root / "scripts").mkdir(parents=True, exist_ok=True)
    (root / "assets").mkdir(parents=True, exist_ok=True)

    (root / "index.html").write_text(page_home(loc), encoding="utf-8")
    (root / "recap.html").write_text(page_recap(loc), encoding="utf-8")
    (root / "calendrier.html").write_text(page_calendrier(loc), encoding="utf-8")
    (root / "categories" / "index.html").write_text(page_categories_index(loc), encoding="utf-8")
    for c in CATEGORIES:
        (root / "categories" / f"{c['slug']}.html").write_text(page_category(loc, c["slug"]), encoding="utf-8")
    for s in SCRIPTS:
        (root / "scripts" / f"{vid_slug(s['id'])}.html").write_text(page_script(loc, s), encoding="utf-8")

    # Le CSV et le Markdown sont des livrables production : une seule copie, en
    # francais, a la racine. Les autres locales y renvoient par un lien relatif.
    if loc == DEFAULT_LOCALE:
        (root / CSV_FILENAME).write_text("\ufeff" + build_calendrier_csv(),
                                         encoding="utf-8", newline="")
        (root / "scripts-video.md").write_text(build_markdown(), encoding="utf-8")

    return 4 + len(CATEGORIES) + len(SCRIPTS)


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=None, help="Dossier de sortie (defaut : dossier du script)")
    args = ap.parse_args()
    out = Path(args.out) if args.out else HERE
    out.mkdir(parents=True, exist_ok=True)
    (out / "assets").mkdir(exist_ok=True)

    total = 0
    for loc in LOCALES:
        total += write_locale(out, loc)

    (out / "assets" / "marketing.css").write_text(CSS, encoding="utf-8")
    (out / "assets" / "marketing.js").write_text(JS, encoding="utf-8")

    # Les sources de verite ne sont jamais ecrasees : on les recopie si la
    # sortie differe du dossier du script.
    for name in (DATA_FILENAME, STATUS_FILENAME):
        dest = out / name
        if dest.resolve() != (HERE / name).resolve() and (HERE / name).exists():
            shutil.copyfile(HERE / name, dest)

    print(f"Espace scripts marketing genere dans {out} — {total} pages HTML "
          f"({len(LOCALES)} locales : {', '.join(LOCALES)}), "
          f"{len(SCRIPTS)} scripts, {len(CATEGORIES)} categories.")


if __name__ == "__main__":
    main()
