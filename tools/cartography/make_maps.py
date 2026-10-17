#!/usr/bin/env python3
"""Génère les tracés des cartes du fond de constellation (vitrine).

Source : Natural Earth 1:110m (domaine public, `ne_110m_admin_0_countries`).
Projection : équirectangulaire simple (x = longitude, y = -latitude), puis
normalisation par région dans un cadre 0 0 100 100 avec conservation du ratio
(la silhouette est centrée, jamais étirée — pas de « silhouette aléatoire »).

Deux familles produites :
  • CONTINENTS — regroupement des pays par continent, frontières internes
    supprimées (union) : l'Afrique est UN continent, pas 51 pays juxtaposés ;
  • COUNTRIES  — le pays seul.

Sortie : front/web/src/modules/vitrine/data/geo/map-paths.ts

Régénérer :  python3 tools/cartography/make_maps.py
"""

from __future__ import annotations

import json
import os
import urllib.request

REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
GEOJSON_URL = (
    "https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/"
    "geojson/ne_110m_admin_0_countries.geojson"
)
CACHE = "/tmp/ne_110m_admin_0_countries.geojson"
OUT = os.path.join(
    REPO_ROOT,
    "front",
    "web",
    "src",
    "modules",
    "vitrine",
    "data",
    "geo",
    "map-paths.ts",
)

# Continent -> clé de sortie (ordre d'apparition dans le fond animé).
CONTINENTS = {
    "Africa": "africa",
    "Europe": "europe",
    "Asia": "asia",
}

# Pays -> nom Natural Earth (ADMIN).
COUNTRIES = {
    "france": "France",
    "algeria": "Algeria",
    "turkey": "Turkey",
    "chad": "Chad",
    "morocco": "Morocco",
    "tunisia": "Tunisia",
    "senegal": "Senegal",
    "saudi": "Saudi Arabia",
}


def load_features() -> list[dict]:
    if not os.path.exists(CACHE):
        urllib.request.urlretrieve(GEOJSON_URL, CACHE)  # noqa: S310 — URL figée
    with open(CACHE, encoding="utf-8") as handle:
        return json.load(handle)["features"]


def rings_of(geometry: dict) -> list[list[tuple[float, float]]]:
    """Anneaux [extérieur, trous…] d'un Polygon ou MultiPolygon."""
    kind = geometry["type"]
    coords = geometry["coordinates"]
    polygons = [coords] if kind == "Polygon" else coords
    rings: list[list[tuple[float, float]]] = []
    for polygon in polygons:
        for ring in polygon:
            if len(ring) >= 4:
                rings.append([(float(x), float(y)) for x, y in ring])
    return rings


def project(rings: list[list[tuple[float, float]]]) -> list[list[tuple[float, float]]]:
    """Équirectangulaire : x = lon, y = -lat (nord en haut)."""
    return [[(lon, -lat) for lon, lat in ring] for ring in rings]


def normalize(rings: list[list[tuple[float, float]]], size: float = 100.0) -> str:
    xs = [x for ring in rings for x, _ in ring]
    ys = [y for ring in rings for _, y in ring]
    min_x, max_x = min(xs), max(xs)
    min_y, max_y = min(ys), max(ys)
    width = max(max_x - min_x, 1e-9)
    height = max(max_y - min_y, 1e-9)
    # Ratio identique en X et Y : la carte reste reconnaissable.
    scale = size / max(width, height)
    offset_x = (size - width * scale) / 2
    offset_y = (size - height * scale) / 2

    parts: list[str] = []
    for ring in rings:
        points = []
        for x, y in ring:
            px = (x - min_x) * scale + offset_x
            py = (y - min_y) * scale + offset_y
            points.append(f"{px:.2f} {py:.2f}")
        if len(points) >= 3:
            parts.append("M" + "L".join(points) + "Z")
    return "".join(parts)


def main() -> None:
    features = load_features()

    entries: list[tuple[str, str, int]] = []

    for continent, key in CONTINENTS.items():
        rings: list[list[tuple[float, float]]] = []
        for feature in features:
            if feature["properties"].get("CONTINENT") != continent:
                continue
            rings.extend(project(rings_of(feature["geometry"])))
        entries.append((key, normalize(rings), len(rings)))

    for key, admin in COUNTRIES.items():
        rings = []
        for feature in features:
            if feature["properties"].get("ADMIN") != admin:
                continue
            rings.extend(project(rings_of(feature["geometry"])))
        entries.append((key, normalize(rings), len(rings)))

    lines = [
        "/**",
        " * Tracés des cartes géographiques du fond de constellation.",
        " *",
        " * GÉNÉRÉ — ne pas éditer à la main. Source : Natural Earth 1:110m",
        " * (domaine public). Frontières internes supprimées (union) pour les",
        " * continents : l'Afrique est UN continent, pas 51 pays juxtaposés.",
        " * Projection : équirectangulaire simple, centrée, viewBox 0 0 100 100,",
        " * proportions préservées (ratio d'échelle identique en X et en Y) —",
        " * chaque silhouette est reconnaissable, jamais une forme aléatoire.",
        " *",
        " * Régénérer : python3 tools/cartography/make_maps.py",
        " */",
        "",
        "export type MapOutline = { readonly d: string; readonly rings: number };",
        "",
        "export const MAP_OUTLINES = {",
    ]
    for key, path, rings_count in entries:
        lines.append(f"  {key}: {{ d: '{path}', rings: {rings_count} }},")
    lines += [
        "} as const satisfies Record<string, MapOutline>;",
        "",
        "export type MapKey = keyof typeof MAP_OUTLINES;",
        "",
    ]

    with open(OUT, "w", encoding="utf-8") as handle:
        handle.write("\n".join(lines))

    print(f"wrote {OUT}")
    for key, path, rings_count in entries:
        print(f"  {key:<9} rings={rings_count:<3} bytes={len(path)}")


if __name__ == "__main__":
    main()
