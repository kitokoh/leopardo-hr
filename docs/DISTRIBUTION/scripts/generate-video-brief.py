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
    # Ajouts 2026-11-28 : les verticales detaillees en 6.7 a 6.15 du programme
    # SUITE n'avaient pas toutes un objet dans ce generateur, donc aucun brief
    # n'etait generable pour elles.
    "hebergement": {"label": "l'hebergement", "module": "HospitalityManager", "angle": "l'unite, l'arrivee, le depart"},
    "pharmacie": {"label": "la pharmacie", "module": "Pharmacy", "angle": "le lot, l'ordonnance, la vitrine"},
    "station-service": {"label": "la station-service", "module": "FuelStation", "angle": "les pompes, les ventes, la cloture"},
    "vtc": {"label": "le VTC", "module": "Vtc", "angle": "la course, la grille, le suivi"},
    "livraison": {"label": "la livraison", "module": "Delivery", "angle": "la tournee, la preuve, le suivi"},
    "bibliotheque": {"label": "la bibliotheque", "module": "Library", "angle": "le catalogue, l'acces, la lecture"},
    "cagnottes": {"label": "les cagnottes solidaires", "module": "Fundraising", "angle": "le lien, le don, le reversement"},
    "flotte": {"label": "la flotte et les cameras", "module": "Fleet, Cameras", "angle": "ce qui roule, ce qui filme"},
    "cabinet-comptable": {"label": "le cabinet comptable", "module": "Accounting", "angle": "le dossier, le partage, l'export"},
    "retail": {"label": "le commerce de detail", "module": "Retail", "angle": "la caisse, le stock, la boutique en ligne"},
    "restauration": {"label": "la restauration", "module": "RestaurantManager", "angle": "la salle, l'ecran cuisine, le ticket"},
    "ecole": {"label": "l'ecole", "module": "EduManager", "angle": "l'appel, les notes, l'espace parents"},
    "hotellerie": {"label": "l'hotellerie", "module": "HospitalityManager", "angle": "le planning, l'arrivee, l'etat des unites"},
    "sante": {"label": "la sante", "module": "HealthManager", "angle": "l'agenda, le dossier, l'ordonnance"},
    "voyage": {"label": "l'agence de voyage", "module": "TravelAgency", "angle": "la vitrine, la reservation, le devis"},
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


# --- Verticales detaillees (programme SUITE, sections 6.7 a 6.15) -----------
#
# Le generateur produisait des briefs **vides** ([A COMPLETER]) a partir de
# trois axes. Les neuf verticales qui ont desormais un script detaille plan par
# plan ont donc leur brief **rempli depuis le script** : ce n'est pas du contenu
# invente, c'est la section 6.x du programme SUITE recopiee dans la structure
# d'un brief. Les seules cases restant a remplir sont celles qui dependent du
# tournage (nom du lieu, date), jamais celles qui dependent du produit.
VERTICALS: dict[str, dict] = {
    "D1": {
        "code": "D1", "titre": "Le service ne s'arrete pas pour une saisie",
        "objet": "restauration", "module": "RestaurantManager",
        "cible": "restaurateurs, gerants de plusieurs etablissements, chaines de restauration rapide",
        "objectif": "montrer la salle, l'ecran cuisine et le ticket pendant le service",
        "accroche": "Pendant le coup de feu, chaque saisie retire une seconde au service.",
        "blocs": [
            ("0-4 s", "Lieu", "Salle pleine, service en cours, une commande prise debout sur un carnet"),
            ("4-10 s", "Situation", "Le coup de feu, et la saisie qui coupe le service"),
            ("10-24 s", "3 plans produit", "Plan de salle par zone et par table (session ouverte, fermee, annulee) · Ecran cuisine : la commande passe de « ouverte » a « en preparation » a « prete » a « servie » · Ticket : bon cuisine et recu client imprimes separement"),
            ("24-30 s", "Signature", "« Leopardo, pour la restauration. » + CTA"),
        ],
        "note": "La salle se montre par zones et tables avec une session de table (ouverte, fermee, annulee). L'ecran cuisine est un poste de travail : la commande y change d'etat selon la machine a etats du module — brouillon, ouverte, en preparation, prete, servie, payee, cloturee, annulee, remboursee. Ne jamais afficher de montant total reel ni de marge : le film montre le flux, pas le chiffre.",
        "contrainte": "La salle se montre par zones et tables avec une session de table (ouverte, fermee, annulee). L'ecran cuisine est un poste de travail : la commande y change d'etat selon la machine a etats du module — brouillon, ouverte, en preparation, prete, servie, payee, cloturee, annulee, remboursee ; ne pas inventer d'autre etat. Ne jamais afficher de ticket portant un montant total reel ni de marge : le film montre le flux, pas le chiffre.",
        "autocontrole": "- 0 occurrence de « marge », « chiffre d'affaires », « benefice »",
        "musique": "Groove leger, ambiance service, 115 BPM",
        "master": "leopardo_d1_restauration",
    },
    "D3": {
        "code": "D3", "titre": "Une chambre ne se loue pas deux fois",
        "objet": "hotellerie", "module": "HospitalityManager",
        "cible": "hoteliers, gestionnaires de residences, exploitants de locations courte et longue duree",
        "objectif": "montrer le planning des unites, l'arrivee et le depart, l'etat des unites",
        "accroche": "Une chambre vendue deux fois, c'est une arrivee qui commence mal.",
        "blocs": [
            ("0-4 s", "Lieu", "Reception d'hotel, deux clients arrives en meme temps, un planning papier surligne"),
            ("4-10 s", "Situation", "La double reservation qui ne se voit qu'a l'arrivee"),
            ("10-24 s", "3 plans produit", "Planning des unites : reservations posees sur l'inventaire reel · Arrivee et depart : en attente, confirmee, installee, partie, annulee, non presentee · Etat de chaque unite : disponible, occupee, en maintenance, hors service"),
            ("24-30 s", "Signature", "« Leopardo, pour l'hotellerie. » + CTA"),
        ],
        "note": "Le module HospitalityManager ne contient AUCUNE gestion de menage ni de housekeeping (verifie dans api/app/Modules/HospitalityManager au 2026-11-27 : aucun modele, service ni route de menage). Ne jamais filmer de valet de chambre, de checklist de nettoyage ni de « chambre prete ». Les baux et loyers de location longue duree existent bien, mais appartiennent au film Hotellerie long format (VID-08), pas a ce format court.",
        "contrainte": "Le module ne gere ni le menage ni le housekeeping : ne jamais montrer de checklist de nettoyage, de valet de chambre ni de statut « chambre prete ». Les etats d'unite sont exactement disponibles, occupee, en maintenance, hors service. Les etats de reservation sont en attente, confirmee, installee, partie, annulee, non presentee : ne pas en inventer d'autres.",
        "autocontrole": "- 0 occurrence de « housekeeping », « menage », « femme de chambre », « chambre prete »",
        "musique": "Nappe discrete, piano, 95 BPM",
        "master": "leopardo_d3_hotellerie",
    },
    "D4": {
        "code": "D4", "titre": "L'officine tient son stock et sa vitrine",
        "objet": "pharmacie", "module": "Pharmacy",
        "cible": "pharmacies d'officine, groupements officinaux, pharmacies de garde",
        "objectif": "montrer la vente sur ordonnance, le stock par lot, la fiche publique",
        "accroche": "Une erreur de lot n'est pas une erreur de stock. C'est une question de sante publique.",
        "blocs": [
            ("0-4 s", "Lieu", "Comptoir d'officine, une boite posee sur le comptoir, numero de lot visible"),
            ("4-10 s", "Situation", "Le lot, la peremption, et ce qui se passe quand on les confond"),
            ("10-24 s", "3 plans produit", "Vente au comptoir, rattachee a une ordonnance quand il y en a une · Stock par lot : numero de lot, date de peremption, quantite, cout unitaire · Fiche publique : nom public, ville, pharmacie de garde, services"),
            ("24-30 s", "Signature", "« Leopardo, pour la pharmacie. » + CTA"),
        ],
        "note": "La fiche publique est publique : elle n'expose NI stock NI prix, seulement le nom, la ville, la garde et les services. Ne jamais afficher de nom de medicament reel associe a une quantite reelle, ni suggerer une conformite reglementaire que le produit ne certifie pas.",
        "contrainte": "La fiche publique n'expose ni stock ni prix : ne jamais montrer de quantite ni de tarif sur la page publique. Le stock se tient lot par lot (numero de lot, peremption, quantite, cout). Ne jamais afficher de nom de medicament reel avec une quantite reelle : utiliser des libelles de demonstration fictifs.",
        "autocontrole": "- 0 occurrence de « conforme », « certifie », « agree »",
        "musique": "Neutre, precis, 100 BPM",
        "master": "leopardo_d4_pharmacie",
    },
    "D5": {
        "code": "D5", "titre": "Le dossier reste confidentiel, et il est la",
        "objet": "sante", "module": "HealthManager",
        "cible": "cliniques privees, polycliniques, centres de soins, cabinets de groupe",
        "objectif": "montrer l'agenda, le dossier de soin et l'ordonnance",
        "accroche": "Le dossier patient est souvent le dernier endroit ou l'information arrive.",
        "blocs": [
            ("0-4 s", "Lieu", "Couloir de clinique, un dossier papier qui circule entre trois mains"),
            ("4-10 s", "Situation", "L'information qui n'arrive pas jusqu'au praticien"),
            ("10-24 s", "3 plans produit", "Agenda : rendez-vous par praticien et par service · Dossier de soin : identite et donnees medicales, affichees masquees par defaut · Ordonnance : lignes rattachees a une consultation"),
            ("24-30 s", "Signature", "« Leopardo, pour la sante. » + CTA"),
        ],
        "note": "Toutes les donnees sensibles (identite, telephone, allergies, antecedents, assurance) sont stockees CHIFFREES et s'affichent masquees a l'ecran : c'est le point a montrer, pas un detail. L'acces est porte par les roles du module (administrateur, praticien, accueil, facturation). Ne jamais afficher un nom de patient lisible ni une donnee clinique reelle.",
        "contrainte": "Les donnees sensibles sont stockees chiffrees et s'affichent masquees : ne jamais montrer un nom de patient lisible, un numero de telephone, une allergie ni un antecedent reel. L'acces est borne par les roles du module (administrateur, praticien, accueil, facturation) : ne pas inventer de role supplementaire. Aucune allegation de conformite reglementaire.",
        "autocontrole": "- 0 occurrence de « conforme RGPD », « certifie », « dossier medical legal »",
        "musique": "Nappe calme, cordes discretes, 88 BPM",
        "master": "leopardo_d5_sante",
    },
    "D6": {
        "code": "D6", "titre": "Le bulletin est publie, les parents le voient",
        "objet": "ecole", "module": "EduManager",
        "cible": "ecoles privees, groupes scolaires, centres de formation",
        "objectif": "montrer l'appel, les notes et l'espace parents",
        "accroche": "L'absence est notee le matin. Le parent l'apprend la semaine suivante.",
        "blocs": [
            ("0-4 s", "Lieu", "Salle de classe, appel sur un cahier, un parent qui appelle l'accueil"),
            ("4-10 s", "Situation", "Le delai entre ce qui se passe en classe et ce que la famille en sait"),
            ("10-24 s", "3 plans produit", "Appel : present, absent, en retard, excuse · Notes : saisie par evaluation, bulletin construit puis valide puis publie · Espace parents : lien a duree limitee, journal des acces"),
            ("24-30 s", "Signature", "« Leopardo, pour l'ecole. » + CTA"),
        ],
        "note": "Le bulletin suit un cycle explicite : construit, valide, publie. C'est la publication qui rend la note visible au parent, pas la saisie. L'espace parents passe par un lien a duree limitee, revocable, avec journal d'acces : montrer le lien et sa date d'expiration, c'est ce qui rassure. Ne jamais promettre « le jour meme » (aucune mesure ne l'etablit) et ne jamais afficher de note d'eleve reelle.",
        "contrainte": "Le bulletin suit un cycle construit, valide, publie : ne jamais montrer une note visible du parent avant la publication. L'espace parents passe par un lien a duree limitee, revocable, avec journal d'acces : montrer l'expiration du lien. Ne jamais afficher de note d'eleve reelle ni de nom d'eleve lisible. Ne pas promettre de delai de publication chiffre.",
        "autocontrole": "- 0 occurrence de « le jour meme », « instantane », « temps reel »",
        "musique": "Leger, piano et clics, 105 BPM",
        "master": "leopardo_d6_ecole",
    },
    "D7": {
        "code": "D7", "titre": "La journee se cloture sur des chiffres qui tombent juste",
        "objet": "station-service", "module": "FuelStation",
        "cible": "reseaux de stations-service, gerants independants, stations de nuit",
        "objectif": "montrer les pompes, les ventes carburant et la cloture",
        "accroche": "Une station ne ferme jamais. La cloture, elle, doit tomber juste.",
        "blocs": [
            ("0-4 s", "Lieu", "Station de nuit, pompe eclairee, gerant seul dans la boutique"),
            ("4-10 s", "Situation", "La nuit, et la cloture qui ne tombe pas juste"),
            ("10-24 s", "3 plans produit", "Index des pompes : pompe, produit, etat · Ventes carburant : poste, volume, montant, mode de paiement · Cloture : fond d'ouverture, attendu, compte, ecart ; puis reconciliation de stock ouverture plus livraisons moins ventes"),
            ("24-30 s", "Signature", "« Leopardo, pour la station-service. » + CTA"),
        ],
        "note": "La cloture affiche un ECART, jamais une « marge ». La reconciliation de stock compare un theorique a un mesure, avec une tolerance : c'est ce qui rend la cloture verifiable. Ne jamais afficher de prix au litre reel : ils varient par pays et par jour.",
        "contrainte": "La cloture montre un ecart entre un attendu et un compte, jamais une marge. La reconciliation de stock compare ouverture plus livraisons moins ventes a une jauge mesuree, avec une tolerance : ne pas inventer d'autre formule. Ne jamais afficher de prix au litre reel, ni de volume total reel : ils dependent du pays et du jour.",
        "autocontrole": "- 0 occurrence de « marge », « benefice », « prix au litre »",
        "musique": "Basse reguliere, nappe tendue, 100 BPM",
        "master": "leopardo_d7_station-service",
    },
    "D8": {
        "code": "D8", "titre": "Le tarif est connu avant de demarrer",
        "objet": "vtc", "module": "Vtc",
        "cible": "flottes VTC et taxi, plateformes locales de mobilite",
        "objectif": "montrer la course, la grille tarifaire et le suivi",
        "accroche": "Le client appelle. On cherche qui est le plus proche. A la main.",
        "blocs": [
            ("0-4 s", "Lieu", "Standard telephonique, carte papier, trois chauffeurs appeles en meme temps"),
            ("4-10 s", "Situation", "Le dispatch a la main, et l'attente du client"),
            ("10-24 s", "3 plans produit", "Course : demandee, en dispatch, acceptee, arrivee, en cours, terminee · Grille tarifaire : prise en charge, prix au kilometre, prix a la minute, minimum · Suivi : position du chauffeur et code de suivi partage au passager"),
            ("24-30 s", "Signature", "« Leopardo, pour le VTC. » + CTA"),
        ],
        "note": "Le module VTC ne calcule AUCUNE distance lui-meme : il interroge le noyau geospatial, et la distance routiere est une estimation (vol d'oiseau multiplie par un facteur de route). Le prix affiche avant le depart est donc une ESTIMATION, pas un engagement : le dire a l'ecran si l'environnement de demonstration n'a pas de grille configuree. Ne pas promettre de dispatch temps reel si le noyau geospatial n'est pas active.",
        "contrainte": "Le module ne calcule aucune distance lui-meme : il interroge le noyau geospatial, et la distance routiere est une estimation (vol d'oiseau x facteur de route). Le prix affiche avant le depart est une estimation des lors que la grille tarifaire n'est pas configuree : ne jamais le presenter comme un prix garanti. Ne pas promettre de dispatch temps reel sans noyau geospatial active.",
        "autocontrole": "- 0 occurrence de « prix garanti », « tarif fixe », « dispatch temps reel »",
        "musique": "Pulsation urbaine, 118 BPM",
        "master": "leopardo_d8_vtc",
    },
    "D10": {
        "code": "D10", "titre": "La vitrine vend, l'agence gere",
        "objet": "voyage", "module": "TravelAgency",
        "cible": "agences de voyage, tour-operateurs, agences d'affaires",
        "objectif": "montrer la boutique publique, la reservation et le devis corporate",
        "accroche": "Vos clients reservent sur WhatsApp. Vos dossiers vivent dans un cahier.",
        "blocs": [
            ("0-4 s", "Lieu", "Bureau d'agence, trois ecrans ouverts : messagerie, tableur, PDF d'un partenaire"),
            ("4-10 s", "Situation", "La reservation dispersee entre trois outils"),
            ("10-24 s", "3 plans produit", "Boutique publique : trajets et annonces en ligne · Reservation : un dossier unique (reference, passagers, paiement, consentement aux notifications) · Devis corporate rattache a un compte entreprise, puis converti en reservation"),
            ("24-30 s", "Signature", "« Leopardo, pour l'agence de voyage. » + CTA"),
        ],
        "note": "La reservation enregistre le CONSENTEMENT AUX NOTIFICATIONS et sa date : c'est un point a montrer, pas a cacher. Le devis corporate est rattache a un compte entreprise et devient une reservation, il ne reste pas un document isole. Ne jamais afficher de tarif aerien reel ni de disponibilite « temps reel » de compagnies qui ne sont pas connectees.",
        "contrainte": "La reservation enregistre le consentement aux notifications et sa date : montrer le consentement, ne jamais le passer sous silence. Le devis corporate est rattache a un compte entreprise puis converti en reservation : ne pas le presenter comme un simple document. Ne jamais afficher de tarif aerien reel ni de disponibilite temps reel d'une compagnie non connectee.",
        "autocontrole": "- 0 occurrence de « temps reel », « disponibilite garantie »",
        "musique": "Nappe ouverte, cordes, 108 BPM",
        "master": "leopardo_d10_agence-de-voyage",
    },
    "D11": {
        "code": "D11", "titre": "La saison se lit dans un seul historique",
        "objet": "agriculture", "module": "FarmOs",
        "cible": "agriculteurs, eleveurs, exploitations et cooperatives agricoles",
        "objectif": "montrer la parcelle, les intrants et la recolte",
        "accroche": "Vous savez ce que vous avez seme. Savez-vous ce que la parcelle a rapporte ?",
        "blocs": [
            ("0-4 s", "Lieu", "Parcelle a l'aube, un carnet de travaux a la main"),
            ("4-10 s", "Situation", "Le carnet qui ne dit ni ce qui a ete applique ni ce qui a ete recolte"),
            ("10-24 s", "3 plans produit", "Parcelle : superficie, source de la superficie, culture et cycle cultural · Intrants : stock, seuil d'alerte, lots · Recolte : lot de recolte, quantite recoltee, qualite, destination de stockage"),
            ("24-30 s", "Signature", "« Leopardo, pour l'agriculture. » + CTA"),
        ],
        "note": "Farm OS enregistre des rendements DECLARES par l'exploitant (attendus et reels, sur le cycle cultural), mais ne les ESTIME jamais automatiquement : c'est la regle du registre FarmProductionType (« aucune estimation automatique de rendement »). Ne jamais montrer de rendement calcule par le systeme, ni de marge. Chaque superficie porte sa source (declaree, mesuree, importee), conformement a ADR-FARM-0003 (docs/mission-farm-os/01_tad.md §6) : c'est ce qui distingue un chiffre d'une estimation. Une recolte cree un seul lot de stock, et ce lot garde sa tracabilite jusqu'a la vente.",
        "contrainte": "Farm OS enregistre des rendements declares par l'exploitant (attendus et reels) mais ne les estime jamais automatiquement : ne jamais montrer de rendement calcule par le systeme, ni de marge. Chaque superficie porte sa source (declaree, mesuree, importee), conformement a ADR-FARM-0003 : ne jamais afficher une superficie sans sa source. Une recolte cree un seul lot de stock qui garde sa tracabilite jusqu'a la parcelle : ne pas montrer de lot sans lien vers la parcelle.",
        "autocontrole": "- 0 occurrence de « rendement estime », « rendement calcule », « marge », « rentabilite garantie »",
        "musique": "Nappe organique, percussions discretes, 98 BPM",
        "master": "leopardo_d11_agriculture",
    },
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


def vertical_brief_path(code: str, cible: str) -> Path:
    v = VERTICALS[code]
    return BRIEFS / f"verticale__{slug(v['objet'])}__{slug(cible)}.md"


def render_vertical(code: str, cible: str) -> str:
    """Brief d'une verticale detaillee, rempli depuis le script 6.x du SUITE."""
    v = VERTICALS[code]
    tgt = TARGETS[cible]
    obj = OBJECTS[v["objet"]]

    lines: list[str] = []
    lines.append(f"# Brief video — verticale {v['code']} · {v['titre']} · {tgt['platform']}")
    lines.append("")
    lines.append(f"> Genere par `docs/DISTRIBUTION/scripts/generate-video-brief.py` "
                 f"(`--vertical {v['code']}` × `{cible}`), depuis le script detaille §6 du "
                 f"programme `SCRIPTS_VIDEOS_SUITE.md`.")
    lines.append("> **Brief rempli** : le contenu produit vient du script, pas d'un gabarit. "
                 "Seuls les `[A COMPLETER]` de tournage restent a renseigner.")
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
    lines.append(f"| Verticale | **{v['code']}** — {v['titre']} |")
    lines.append(f"| Objet | `{v['objet']}` — {obj['label']} (module `{v['module']}`) |")
    lines.append(f"| Cible | {v['cible']} |")
    lines.append(f"| Objectif | {v['objectif']} |")
    lines.append(f"| Plateforme | {tgt['platform']} |")
    lines.append(f"| Duree cible | 30 s (max plateforme : {tgt['max']}) |")
    lines.append(f"| Format | 16:9 master — ratios 16:9 / 9:16 / 1:1 ; ratio plateforme {tgt['ratio']} |")
    lines.append("")
    lines.append("## Accroche (3 premieres secondes)")
    lines.append("")
    lines.append(f"> {v['accroche']}")
    lines.append("")
    lines.append("## Plan")
    lines.append("")
    lines.append("| Temps | Bloc | Contenu |")
    lines.append("|---|---|---|")
    for t_, bloc, contenu in v["blocs"]:
        lines.append(f"| {t_} | {bloc} | {contenu} |")
    lines.append("")
    lines.append("**Note de montage.** " + v["note"])
    lines.append("")
    lines.append("## Preuves a l'ecran")
    lines.append("")
    lines.append(f"- Les trois ecrans de {obj['label']}, captures sur l'application livree, sur le jeu de "
                 f"demonstration TechCorp DZ (fictif, capte une seule fois puis derive).")
    lines.append("- Ne jamais montrer une maquette en la faisant passer pour le produit.")
    lines.append("")
    lines.append("## Appel a l'action")
    lines.append("")
    lines.append("Essayer gratuitement 14 jours — lien vers l'essai.")
    lines.append("")
    lines.append("## Lien et UTM")
    lines.append("")
    lines.append("```")
    lines.append(f"utm_source={tgt['source']}")
    lines.append(f"utm_medium={tgt['medium']}")
    lines.append(f"utm_campaign=verticale-{slug(v['objet'])}")
    lines.append("utm_content=[A COMPLETER]")
    lines.append("```")
    lines.append("")
    lines.append("## Prompt pour agent IA de production")
    lines.append("")
    lines.append("A coller tel quel dans un agent de production video. Le brief est deja rempli : "
                 "l'agent produit, il n'improvise pas.")
    lines.append("")
    lines.append("```text")
    lines.append("ROLE : tu es realisateur-monteur video IA pour Leopardo, la suite metier des entreprises de terrain.")
    lines.append(f"Tu produis la video {v['code']} « {v['titre']} » (verticale : {obj['label']}, 30 s, 16:9) "
                 f"pour {tgt['platform']}, a partir du script ci-dessus, sans rien inventer.")
    lines.append("")
    lines.append("LIVRABLE")
    lines.append(f"- Master 3840x2160, 25 fps, H.264 High, AAC 320 kbps ; declinaisons 1920x1080, "
                 f"1080x1920 (9:16), 1080x1080 (1:1)")
    lines.append("- Voix off FR, 150-165 mots/min, ton pose ; sous-titres .srt + .vtt "
                 "(brules en social, separes en web)")
    lines.append("- Palette : fond nuit #0B1220, accent #2563EB, validation #16A34A, "
                 "alerte #DC2626, neutre #94A3B8 ; Inter, 2 graisses max par plan")
    lines.append(f"- Musique : {v['musique']} ; lit a -18 dB sous la voix, montee a -12 dB sur le CTA")
    lines.append("")
    lines.append("CONTRAINTES NON NEGOCIABLES")
    lines.append("1. Categorie = « suite metier des entreprises de terrain ». Jamais « logiciel RH », "
                 "« SaaS RH », « HR software », ni « Business OS ».")
    lines.append("2. Chaque plan produit est une captation de l'application reellement livree, sur donnees "
                 "de demonstration fictives. Aucune maquette inventee, aucune fonction non livree.")
    lines.append("3. Aucun chiffre non mesure et non date : tout chiffre affiche doit exister dans "
                 "docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md avec sa date de mesure.")
    lines.append("4. Aucun client reel, logo ou temoignage sans autorisation ecrite. TechCorp DZ, Riad Atlas, "
                 "Pharmacie Ennour sont fictifs et le restent a l'ecran.")
    lines.append("5. Aucune allegation de conformite legale, de certification ou de label non obtenu.")
    lines.append("6. Le film reste comprehensible sans son : chaque plan porte son texte a l'ecran.")
    lines.append(f"7. {v['contrainte']}")
    lines.append("")
    lines.append("SORTIES")
    lines.append(f"- {v['master']}_{{lang}}_{{ratio}}_v1.mp4 / .srt / .vtt")
    lines.append("- miniatures 1280x720 et 1080x1920 ; shot-list.csv (timecode, plan, source de capture, statut)")
    lines.append("")
    lines.append("AUTO-CONTROLE")
    lines.append("- duree conforme au script a +/- 2 %")
    lines.append("- 0 occurrence de « logiciel RH », « SaaS RH », « Business OS »")
    lines.append(v["autocontrole"])
    lines.append("- chaque chiffre a l'ecran retrouve dans METRIQUES_VITRINE.md avec sa date")
    lines.append("- CTA et URL testes sur deux telephones ; versions 16:9 / 9:16 / 1:1 exportees")
    lines.append("```")
    lines.append("")
    lines.append("## Suivi")
    lines.append("")
    lines.append("Ajouter une ligne dans `docs/DISTRIBUTION/SUIVI.md` :")
    lines.append("")
    lines.append(f"`| Verticale {v['code']} — {v['titre']} | verticale | {v['objet']} | "
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
        # 2. Les briefs de verticales sont remplis depuis le script, pas vides.
        ("verticale : brief rempli", "[A COMPLETER]" not in render_vertical("D1", "youtube")
         .split("## Plan")[1].split("## Preuves")[0]),
        ("verticale : accroche du script", VERTICALS["D7"]["accroche"]
         in render_vertical("D7", "linkedin")),
        ("verticale : 4 blocs de 30 s", render_vertical("D4", "youtube").count("| 10-24 s |") == 1),
        ("verticale : contrainte propre au module",
         "housekeeping" in render_vertical("D3", "youtube")
         and "rendement estime" in render_vertical("D11", "youtube")
         and "prix garanti" in render_vertical("D8", "youtube")),
        ("verticale : cible change l'UTM",
         "utm_source=youtube" in render_vertical("D10", "youtube")
         and "utm_source=tiktok" in render_vertical("D10", "tiktok")),
        ("verticale : slug stable", vertical_brief_path("D5", "youtube").name
         == "verticale__sante__youtube.md"),
        ("les 9 verticales detaillees ont un brief", len(VERTICALS) == 9
         and all(v["objet"] in OBJECTS for v in VERTICALS.values())),
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
    parser.add_argument("--vertical", metavar="CODE",
                        help="brief rempli d'une verticale detaillee (D1, D3, D4, D5, D6, D7, D8, D10, D11)")
    parser.add_argument("--vertical-all", action="store_true",
                        help="genere les 9 briefs de verticales detaillees pour --cible")
    parser.add_argument("--list", action="store_true", help="liste les axes disponibles")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    if args.list:
        print("Categories :", ", ".join(sorted(CATEGORIES)))
        print("Objets     :", ", ".join(sorted(OBJECTS)))
        print("Cibles     :", ", ".join(sorted(TARGETS)))
        print("Verticales :", ", ".join(f"{k} ({v['titre']})" for k, v in VERTICALS.items()))
        return 0

    if args.vertical_all:
        if not args.cible:
            print("ERREUR — --vertical-all exige --cible.", file=sys.stderr)
            return 2
        BRIEFS.mkdir(parents=True, exist_ok=True)
        for code in VERTICALS:
            path = vertical_brief_path(code, args.cible)
            path.write_text(render_vertical(code, args.cible), encoding="utf-8")
        total = write_index()
        print(f"OK : {len(VERTICALS)} brief(s) de verticales genere(s) pour {args.cible}, "
              f"index a jour ({total} briefs).")
        return 0

    if args.vertical:
        if args.vertical not in VERTICALS:
            print(f"ERREUR — verticale inconnue : {args.vertical}. "
                  f"Valeurs : {', '.join(VERTICALS)}.", file=sys.stderr)
            return 2
        if not args.cible:
            print("ERREUR — --vertical exige --cible.", file=sys.stderr)
            return 2
        BRIEFS.mkdir(parents=True, exist_ok=True)
        path = vertical_brief_path(args.vertical, args.cible)
        path.write_text(render_vertical(args.vertical, args.cible), encoding="utf-8")
        total = write_index()
        print(f"OK : {path.relative_to(DISTRIBUTION)}")
        print(f"     index a jour ({total} briefs).")
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
