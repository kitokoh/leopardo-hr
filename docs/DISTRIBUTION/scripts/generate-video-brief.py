#!/usr/bin/env python3
"""Issue #9804 — genere un brief video a partir de trois axes.

## Pourquoi un generateur

`docs/GOTO_MARKET/ASSETS_PRODUCTION/VIDEOS/` contient trois scripts **figes**, ecrits
pour un usage unique. Des qu'il faut une video pour une autre verticale, une autre
cible ou un autre objectif, il faut repartir de zero — et le resultat depend de qui
l'ecrit ce jour-la.

Ce generateur produit un brief **structure et reproductible** a partir de trois axes :

  - **categorie** : ce que la video cherche a faire (publicite, faire connaitre,
    sensibilisation, demonstration, temoignage, recrutement, formation) ;
  - **objet** : ce dont elle parle (un module, une fonctionnalite, le produit) ;
  - **cible** : ou elle sera publiee (LinkedIn, YouTube, TikTok/Reels, vitrine,
    App Store, salon).

Chaque axe change reellement le brief : la duree, le format, l'accroche, le CTA et
l'UTM en decoulent. Ce n'est pas un gabarit unique avec trois variables substituees.

## Ce que le generateur ne fait PAS

Il n'invente **aucun** contenu factuel : pas de client, pas de temoignage, pas de
chiffre. Les emplacements a remplir sont marques `[A COMPLETER]`, et les contraintes
du depot sont rappelees en tete de chaque brief :

  - pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` ;
  - aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md` ;
  - UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.

## Usage

    python3 docs/DISTRIBUTION/scripts/generate-video-brief.py \\
        --categorie publicite --objet pointage --cible linkedin

    python3 docs/DISTRIBUTION/scripts/generate-video-brief.py --list
    python3 docs/DISTRIBUTION/scripts/generate-video-brief.py --all
    python3 docs/DISTRIBUTION/scripts/generate-video-brief.py --self-test
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

DISTRIBUTION = Path(__file__).resolve().parents[1]
BRIEFS = DISTRIBUTION / "VIDEOS" / "briefs"
INDEX = DISTRIBUTION / "VIDEOS" / "INDEX.md"

# --- Axe 1 : categorie ------------------------------------------------------
# (duree cible, format, intention, structure du plan, CTA)
CATEGORIES: dict[str, dict[str, str]] = {
    "publicite": {
        "duree": "15-30 s",
        "format": "vertical 9:16 (Reels/Shorts) ou carre 1:1 (feed)",
        "intention": "faire agir maintenant — capter, montrer le benefice, appeler a l'essai",
        "structure": (
            "0-3 s accroche (le probleme en une phrase) ; "
            "3-10 s demonstration du benefice ; "
            "10-20 s preuve (ecran reel, pas de maquette) ; "
            "20-30 s CTA"
        ),
        "cta": "Essayer gratuitement — lien en bio / premier commentaire",
    },
    "faire-connaitre": {
        "duree": "45-90 s",
        "format": "horizontal 16:9 (YouTube/LinkedIn) ou vertical 9:16",
        "intention": "installer le nom et la categorie — pas de vente directe",
        "structure": (
            "0-5 s contexte (qui est concerne) ; "
            "5-25 s ce que fait le produit, en une idee ; "
            "25-60 s trois capacites concretes ; "
            "60-90 s ou en savoir plus"
        ),
        "cta": "En savoir plus — lien vers la vitrine",
    },
    "sensibilisation": {
        "duree": "60-120 s",
        "format": "horizontal 16:9 (LinkedIn/YouTube)",
        "intention": "faire comprendre un probleme, sans vendre le produit comme solution unique",
        "structure": (
            "0-10 s le probleme, chiffre seulement s'il est source et date ; "
            "10-40 s ce que cela coute concretement ; "
            "40-90 s ce qui change quand on outille ; "
            "90-120 s invitation a la discussion"
        ),
        "cta": "Et chez vous ? — commentaire ou message",
    },
    "demonstration": {
        "duree": "2-5 min",
        "format": "horizontal 16:9 (YouTube), capture d'ecran commentee",
        "intention": "prouver que ca marche — montrer le parcours reel, sans coupe magique",
        "structure": (
            "0-15 s ce qu'on va voir et pour qui ; "
            "15 s-4 min parcours pas a pas sur donnees de demonstration ; "
            "4-5 min limites et cas non couverts"
        ),
        "cta": "Essayer sur vos donnees — lien vers l'essai",
    },
    "temoignage": {
        "duree": "60-120 s",
        "format": "horizontal 16:9 ou vertical 9:16",
        "intention": "faire parler un client reel — jamais un acteur, jamais un texte invente",
        "structure": (
            "0-10 s qui parle (nom, entreprise, role) ; "
            "10-40 s la situation avant ; "
            "40-90 s ce qui a change, avec un fait verifiable ; "
            "90-120 s a qui il le recommanderait"
        ),
        "cta": "Parler a une entreprise comme la votre",
    },
    "recrutement": {
        "duree": "60-90 s",
        "format": "vertical 9:16 ou horizontal 16:9",
        "intention": "attirer des candidats — montrer le travail reel, pas des valeurs abstraites",
        "structure": (
            "0-10 s le poste et le lieu ; "
            "10-40 s ce qu'on fait vraiment au quotidien ; "
            "40-70 s ce qu'on demande et ce qu'on offre ; "
            "70-90 s comment postuler"
        ),
        "cta": "Postuler — lien vers l'offre",
    },
    "formation": {
        "duree": "3-8 min",
        "format": "horizontal 16:9, capture d'ecran + voix",
        "intention": "rendre autonome — un parcours complet, rejouable",
        "structure": (
            "0-20 s objectif et prerequis ; "
            "20 s-7 min etapes numerotees ; "
            "7-8 min verification et erreurs courantes"
        ),
        "cta": "Refaire l'exercice — documentation liee",
    },
}

# --- Axe 2 : objet ----------------------------------------------------------
# (libelle lisible, module de reference, angle)
OBJECTS: dict[str, dict[str, str]] = {
    "produit": {"label": "la suite metier Leopardo", "module": "—", "angle": "la suite, pas un outil isole"},
    "rh": {"label": "la gestion RH", "module": "HR", "angle": "le dossier employe au meme endroit"},
    "paie": {"label": "la paie", "module": "Payroll", "angle": "le bulletin juste, sans reprise manuelle"},
    "pointage": {"label": "le pointage terrain", "module": "Attendance", "angle": "pointer depuis le terrain, sans badge"},
    "absences": {"label": "les absences et conges", "module": "Absence", "angle": "la demande et la validation sans e-mail"},
    "comptabilite": {"label": "la comptabilite", "module": "Accounting", "angle": "les ecritures issues de l'activite"},
    "depenses": {"label": "les notes de frais", "module": "Expense", "angle": "la depense capturee puis comptabilisee"},
    "crm": {"label": "le CRM", "module": "CRM", "angle": "le pipeline commercial visible"},
    "trade": {"label": "le commerce import-export", "module": "Trade", "angle": "contreparties, produits, dossiers"},
    "restaurant": {"label": "la restauration", "module": "Restaurant", "angle": "le service et la marge du jour"},
    "travel": {"label": "le voyage", "module": "TravelAgency", "angle": "les dossiers clients et les reservations"},
    "sante": {"label": "la sante", "module": "HealthManager", "angle": "le parcours patient outille"},
    "education": {"label": "l'education", "module": "EduManager", "angle": "la vie scolaire outillee"},
    "agriculture": {"label": "l'agriculture", "module": "FarmOs", "angle": "l'exploitation pilotee"},
}

# --- Axe 3 : cible ----------------------------------------------------------
# (plateforme, ratio, duree max, utm_source, utm_medium, note)
TARGETS: dict[str, dict[str, str]] = {
    "linkedin": {"platform": "LinkedIn", "ratio": "1:1 ou 16:9", "max": "3 min", "source": "linkedin", "medium": "social"},
    "youtube": {"platform": "YouTube", "ratio": "16:9", "max": "10 min", "source": "youtube", "medium": "social"},
    "tiktok": {"platform": "TikTok / Reels / Shorts", "ratio": "9:16", "max": "60 s", "source": "tiktok", "medium": "social"},
    "vitrine": {"platform": "Site vitrine", "ratio": "16:9", "max": "5 min", "source": "vitrine", "medium": "referral"},
    "appstore": {"platform": "App Store / Play Store", "ratio": "9:16", "max": "30 s", "source": "appstore", "medium": "listing"},
    "salon": {"platform": "Salon / evenement", "ratio": "16:9", "max": "2 min", "source": "salon", "medium": "event"},
}


def slug(value: str) -> str:
    return value.strip().lower().replace(" ", "-")


def brief_path(categorie: str, objet: str, cible: str) -> Path:
    return BRIEFS / f"{slug(categorie)}__{slug(objet)}__{slug(cible)}.md"


def render(categorie: str, objet: str, cible: str) -> str:
    cat = CATEGORIES[categorie]
    obj = OBJECTS[objet]
    tgt = TARGETS[cible]

    lines: list[str] = []
    lines.append(f"# Brief video — {cat['intention'].split(' — ')[0]} · {obj['label']} · {tgt['platform']}")
    lines.append("")
    lines.append(f"> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` "
                 f"(`{categorie}` × `{objet}` × `{cible}`).")
    lines.append("> **Gabarit a remplir** : les `[A COMPLETER]` sont des emplacements, pas du contenu.")
    lines.append("")
    lines.append("## Contraintes (non negociables)")
    lines.append("")
    lines.append("- Pitch et promesses interdites : `docs/REFERENTIEL_PRODUIT/MESSAGE.md`.")
    lines.append("- Aucun chiffre public sans date de mesure : `METRIQUES_VITRINE.md`.")
    lines.append("- UTM conformes : `docs/GOTO_MARKET/CONVENTION_UTM.md`.")
    lines.append("- Aucun client, temoignage ou metrique invente.")
    lines.append("")
    lines.append("## Parametres")
    lines.append("")
    lines.append("| Parametre | Valeur |")
    lines.append("|---|---|")
    lines.append(f"| Categorie | `{categorie}` — {cat['intention']} |")
    lines.append(f"| Objet | `{objet}` — {obj['label']} (module `{obj['module']}`) |")
    lines.append(f"| Angle | {obj['angle']} |")
    lines.append(f"| Cible | {tgt['platform']} |")
    lines.append(f"| Duree cible | {cat['duree']} (max plateforme : {tgt['max']}) |")
    lines.append(f"| Format | {cat['format']} — ratio plateforme {tgt['ratio']} |")
    lines.append("")
    lines.append("## Accroche (3 premieres secondes)")
    lines.append("")
    lines.append("> [A COMPLETER] Une phrase qui nomme le probleme du spectateur, pas le produit.")
    lines.append("")
    lines.append("## Plan")
    lines.append("")
    lines.append(f"{cat['structure']}")
    lines.append("")
    lines.append("| # | Plan | Visuel | Voix / texte | Duree |")
    lines.append("|---|---|---|---|---|")
    lines.append("| 1 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |")
    lines.append("| 2 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |")
    lines.append("| 3 | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] | [A COMPLETER] |")
    lines.append("")
    lines.append("## Preuves a l'ecran")
    lines.append("")
    lines.append("- [A COMPLETER] Ecran reel du module, sur donnees de demonstration.")
    lines.append("- Ne jamais montrer une maquette en la faisant passer pour le produit.")
    lines.append("")
    lines.append("## Appel a l'action")
    lines.append("")
    lines.append(f"{cat['cta']}")
    lines.append("")
    lines.append("## Lien et UTM")
    lines.append("")
    lines.append("```")
    lines.append(f"utm_source={tgt['source']}")
    lines.append(f"utm_medium={tgt['medium']}")
    lines.append(f"utm_campaign={slug(categorie)}-{slug(objet)}")
    lines.append("utm_content=[A COMPLETER]")
    lines.append("```")
    lines.append("")
    lines.append("## Prompt pour agent IA de production")
    lines.append("")
    lines.append("A coller tel quel dans un agent de production video, une fois le brief rempli.")
    lines.append("")
    lines.append("```text")
    lines.append("ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.")
    lines.append(f"Tu produis une video {categorie} sur {obj['label']} (module {obj['module']}) pour {tgt['platform']},")
    lines.append("a partir du brief ci-dessus, sans rien inventer.")
    lines.append("")
    lines.append("LIVRABLE")
    lines.append(f"- Duree {cat['duree']} (max plateforme {tgt['max']}), format {tgt['ratio']}, 25 fps, H.264 High, AAC 320 kbps")
    lines.append("- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt (brules en social, separes en web)")
    lines.append("- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan")
    lines.append("")
    lines.append("CONTRAINTES NON NEGOCIABLES")
    lines.append("1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », « SaaS RH », « HR software », ni « Business OS ».")
    lines.append("2. Chaque plan produit est une captation de l'application reellement livree, sur donnees de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.")
    lines.append("3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.")
    lines.append("4. Aucun client reel, logo ou temoignage sans autorisation ecrite.")
    lines.append("5. Aucune allegation de conformite legale, de certification ou de label non obtenu.")
    lines.append("6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.")
    lines.append("7. Remplacer chaque [A COMPLETER] du brief par du contenu verifie ; ne jamais livrer un brief non rempli.")
    lines.append("")
    lines.append("SORTIES")
    lines.append(f"- leopardo_{slug(categorie)}_{slug(objet)}_{{lang}}_{tgt['ratio'].replace(':', 'x')}_v1.mp4 / .srt / .vtt")
    lines.append("- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)")
    lines.append("")
    lines.append("AUTO-CONTROLE")
    lines.append("- duree dans la fourchette cible a +/- 2 %")
    lines.append("- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »")
    lines.append("- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date")
    lines.append("- UTM du brief reportes a l'identique dans la description publiee")
    lines.append("```")
    lines.append("")
    lines.append("## Suivi")
    lines.append("")
    lines.append("Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :")
    lines.append("")
    lines.append(f"`| {cat['intention'].split(' — ')[0]} {obj['label']} | {categorie} | {objet} | "
                 f"{tgt['platform']} | a faire | — | — | [A COMPLETER] |`")
    lines.append("")
    return "\n".join(lines)


def write_index() -> int:
    """Regenere VIDEOS/INDEX.md depuis les briefs presents sur disque."""
    BRIEFS.mkdir(parents=True, exist_ok=True)
    files = sorted(BRIEFS.glob("*.md"))

    lines = [
        "# Index des briefs video",
        "",
        "> Genere par `scripts/generate-video-brief.py`. Ne pas editer a la main.",
        "",
        "| Brief | Categorie | Objet | Cible | Statut |",
        "|---|---|---|---|---|",
    ]

    for path in files:
        parts = path.stem.split("__")
        if len(parts) != 3:
            continue
        categorie, objet, cible = parts
        lines.append(
            f"| [{path.stem}](briefs/{path.name}) | {categorie} | {objet} | {cible} | a faire |"
        )

    lines.append("")
    lines.append(f"Total : **{len(files)}** brief(s).")
    lines.append("")

    INDEX.parent.mkdir(parents=True, exist_ok=True)
    INDEX.write_text("\n".join(lines), encoding="utf-8")
    return len(files)


def self_test() -> int:
    failures = 0

    # 1. Les trois axes changent reellement le brief.
    a = render("publicite", "pointage", "tiktok")
    b = render("demonstration", "pointage", "tiktok")
    c = render("publicite", "paie", "tiktok")
    d = render("publicite", "pointage", "youtube")

    checks = [
        ("categorie change la duree", "15-30 s" in a and "2-5 min" in b),
        ("categorie change le CTA", "Essayer gratuitement" in a and "Essayer sur vos donnees" in b),
        ("objet change le module", "`Attendance`" in a and "`Payroll`" in c),
        ("cible change l'UTM", "utm_source=tiktok" in a and "utm_source=youtube" in d),
        ("cible change le ratio", "9:16" in a and "16:9" in d),
        ("aucun contenu invente", "[A COMPLETER]" in a),
        ("contraintes rappelees", "MESSAGE.md" in a and "METRIQUES_VITRINE.md" in a),
        ("slug stable", brief_path("publicite", "pointage", "tiktok").name
         == "publicite__pointage__tiktok.md"),
    ]

    for name, ok in checks:
        print(f"  [{'OK  ' if ok else 'FAIL'}] {name}")
        if not ok:
            failures += 1

    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas sur {len(checks)}.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {len(checks)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--categorie", choices=sorted(CATEGORIES))
    parser.add_argument("--objet", choices=sorted(OBJECTS))
    parser.add_argument("--cible", choices=sorted(TARGETS))
    parser.add_argument("--all", action="store_true", help="genere toutes les combinaisons")
    parser.add_argument("--list", action="store_true", help="liste les axes disponibles")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    if args.list:
        print("Categories :", ", ".join(sorted(CATEGORIES)))
        print("Objets     :", ", ".join(sorted(OBJECTS)))
        print("Cibles     :", ", ".join(sorted(TARGETS)))
        return 0

    if args.all:
        BRIEFS.mkdir(parents=True, exist_ok=True)
        count = 0
        for categorie in sorted(CATEGORIES):
            for objet in sorted(OBJECTS):
                for cible in sorted(TARGETS):
                    path = brief_path(categorie, objet, cible)
                    path.write_text(render(categorie, objet, cible), encoding="utf-8")
                    count += 1
        total = write_index()
        print(f"OK : {count} brief(s) genere(s), index a jour ({total} briefs).")
        return 0

    missing = [
        name
        for name, value in (
            ("--categorie", args.categorie),
            ("--objet", args.objet),
            ("--cible", args.cible),
        )
        if not value
    ]
    if missing:
        print(
            f"ERREUR — parametres manquants : {', '.join(missing)}. "
            f"Utiliser --list pour voir les valeurs, ou --all.",
            file=sys.stderr,
        )
        return 2

    BRIEFS.mkdir(parents=True, exist_ok=True)
    path = brief_path(args.categorie, args.objet, args.cible)
    path.write_text(render(args.categorie, args.objet, args.cible), encoding="utf-8")
    total = write_index()

    print(f"OK : {path.relative_to(DISTRIBUTION)}")
    print(f"     index a jour ({total} briefs).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
