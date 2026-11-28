#!/usr/bin/env python3
"""Regenere les ai_agent_prompt de VID-01..04 depuis les champs du script.

v9 (2026-11-28) : le cadrage etroit de la v8 (groupes 500-5 000 salaries
DZ/MA/TN) a ete elargi dans les champs audience/objective/hook/beats/notes.
Les prompts IA doivent suivre, sinon le tournage rejoue l'ancien script.

Les prompts sont ecrits SANS ACCENTS (convention des prompts IA du depot :
ils sont lus par un modele, pas par un humain a l'ecran).
"""
from __future__ import annotations

import json
import unicodedata
from pathlib import Path

HERE = Path(__file__).resolve().parent
DATA = HERE / "scripts-video.json"

TARGETS = ("VID-01", "VID-02", "VID-03", "VID-04")


def sa(text: str) -> str:
    """Retire les accents (NFD -> suppression des marques)."""
    return "".join(
        c for c in unicodedata.normalize("NFD", text)
        if unicodedata.category(c) != "Mn"
    )


def build_prompt(s: dict) -> str:
    sid = s["id"]
    slug = sid.lower().replace("-", "")
    rows = "\n".join(
        f"| {b['t']} | {sa(b['vo'])} | {sa(b['shot'])} | {sa(b['text'])} |"
        for b in s["beats"]
    )
    return (
        f"ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier "
        f"des entreprises de terrain. Tu produis la video {sid} a partir du script "
        f"ci-dessous, sans rien inventer.\n"
        f"\n"
        f"LIVRABLE : {sid} - \"{sa(s['title'])}\"\n"
        f"- Format {s['format']}, duree cible {s['duration']}, 25 fps, master 3840x2160 ; "
        f"declinaisons 1920x1080, 1080x1920 (9:16), 1080x1080 (1:1)\n"
        f"- Voix off FR, debit 150-165 mots/min, ton pose, jamais publicitaire criard\n"
        f"- Sous-titres .srt + .vtt (brules en version sociale, fichiers separes en version web)\n"
        f"- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, "
        f"alerte #DC2626, neutre #94A3B8 ; typographie Inter, 2 graisses maximum par plan\n"
        f"- Musique : {sa(s['music'])} ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA\n"
        f"\n"
        f"PUBLIC : {sa(s['audience'])}\n"
        f"OBJECTIF : {sa(s['objective'])}\n"
        f"ACCROCHE (0-3 s, doit tenir sans son) : \"{sa(s['hook'])}\"\n"
        f"\n"
        f"DECOUPAGE A PRODUIRE (respecte les timecodes a la seconde) :\n"
        f"| Temps | Voix off | Plan | Texte a l'ecran |\n"
        f"|---|---|---|---|\n"
        f"{rows}\n"
        f"\n"
        f"CTA FINAL : {sa(s['cta'])}\n"
        f"\n"
        f"CONTRAINTES NON NEGOCIABLES :\n"
        f"1. Categorie = \"suite metier des entreprises de terrain\". Jamais \"logiciel RH\", "
        f"\"SaaS RH\", \"HR software\", ni \"Business OS\".\n"
        f"2. Chaque plan produit est une captation de l'application reellement livree, sur "
        f"des donnees de demonstration fictives. Aucune maquette inventee, aucune fonction "
        f"non livree.\n"
        f"3. Aucun chiffre non mesure et non date. Tout chiffre affiche doit exister dans "
        f"docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.\n"
        f"4. Aucun client reel, aucun logo, aucun temoignage sans autorisation ecrite. Les "
        f"entreprises des scripts (TechCorp DZ, Riad Atlas, Pharmacie Ennour...) sont "
        f"fictives et le restent a l'ecran.\n"
        f"5. Aucune allegation de conformite legale, de certification ou de label non obtenu. "
        f"Les referentiels de paie sont en statut pilote ; seul OIDC est implemente "
        f"(jamais \"SSO SAML\", \"SCIM\", \"certifie\").\n"
        f"6. Le film doit rester comprehensible sans son : chaque plan porte son texte a l'ecran.\n"
        f"7. Consignes de tournage du script : {sa(s['notes'])}\n"
        f"\n"
        f"SORTIES ATTENDUES :\n"
        f"- leopardo_{slug}_{{lang}}_16x9_v1.mp4 (H.264 High, AAC 320 kbps)\n"
        f"- leopardo_{slug}_{{lang}}_16x9_v1.srt et .vtt\n"
        f"- miniature 1280x720 et 1080x1920\n"
        f"- shot-list.csv (timecode, plan, source de la capture, statut)\n"
        f"\n"
        f"AUTO-CONTROLE AVANT LIVRAISON :\n"
        f"- duree reelle = {s['duration']} a +/- 2 %\n"
        f"- 0 occurrence de \"logiciel RH\", \"SaaS RH\", \"Business OS\"\n"
        f"- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date\n"
        f"- sous-titres relus, CTA et URL testes sur deux telephones\n"
        f"- versions 16:9 / 9:16 / 1:1 exportees"
    )


def main() -> None:
    data = json.loads(DATA.read_text(encoding="utf-8"))
    changed = []
    for s in data["scripts"]:
        if s["id"] in TARGETS:
            new = build_prompt(s)
            if s.get("ai_agent_prompt") != new:
                s["ai_agent_prompt"] = new
                changed.append(s["id"])
    DATA.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(f"prompts regeneres : {', '.join(changed) if changed else 'aucun changement'}")


if __name__ == "__main__":
    main()
