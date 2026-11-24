#!/usr/bin/env python3
"""Documente les 78 dernieres routes non couvertes par api/openapi.yaml.

Contexte (#eval-2026-11-24) : apres les vagues edu_manager/travelagency puis
crm/restaurantmanager, la garde CI signalait encore 78 routes non couvertes :

  - api.php                 53  (boutiques publiques travel/restaurant, kiosque,
                                 connecteurs plateforme, kill-switches, marketing-os,
                                 blog vitrine, stats de survey)
  - modules/connectors.php   8  (connecteurs tenant)
  - modules/rh.php           7  (permissions verticales, enrolement kiosque)
  - modules/showcase.php     4  (medias de vitrine)
  - modules/solutions.php    4  (surveys de solutions)
  - modules/marketing.php    1  (brief de production IA)
  - modules/user.php         1  (demandes d'integration, cote manager)

Approche identique a `doc_crm_and_restaurant_openapi.py` : on ne recopie PAS
les routes a la main, on reutilise le parseur de la garde CI
(`dev-hub/tools/check-openapi-route-coverage.py`) — source unique de verite —
puis on emet une operation OpenAPI correcte pour chaque couple
(methode, chemin) manquant.

Ce que la spec dit REELLEMENT, par famille :

  - **Boutiques publiques** (`/public/travel/shop/*`, `/public/travel/tickets/*`,
    `/public/restaurant/shop/*`, `/public/restaurant/kiosk/*`,
    `/public/vitrine/*`, `/solutions/*`) : aucun token Sanctum. Le tenant est
    resolu par un jeton de boutique signe, un slug ou la ressource elle-meme.
    `security: []` — declarer un BearerAuth serait un mensonge.
  - **Webhooks entrants** (`/travel/payments/callback`,
    `/travel/carrier-sync/trips`, `/restaurant/payments/{payment}/callback`,
    `/restaurant/webhooks/delivery-apps/{provider}`) : signature HMAC ou jeton
    transporteur verifies DANS le controleur, fail-closed. Pas de Sanctum.
  - **Console plateforme** (`/platform/*`, `/admin/*`) : guard
    `auth:super_admin_api` + `platform.mfa` + permission `platform.permission:*`.
    Scheme `SuperAdminBearerAuth`.
  - **Tenant** (connecteurs, kiosques, medias de vitrine, marketing IA,
    demandes d'integration) : `BearerAuth`, tenant resolu depuis le token.

Usage :
  python3 scripts/doc_remaining_openapi.py [--check]

`--check` n'ecrit rien et sort en 1 si des operations manquent.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
OPENAPI_FILE = REPO_ROOT / "api" / "openapi.yaml"
TOOLS_DIR = REPO_ROOT / "dev-hub" / "tools"

# Surfaces SANS Sanctum : ni Bearer, ni middleware tenant.
PUBLIC_PREFIXES = (
    "/public/travel/",
    "/public/restaurant/",
    "/public/vitrine/",
    "/solutions",
)

# Webhooks entrants : signature HMAC / jeton transporteur verifies dans le
# controleur. Publics au sens HTTP, mais fail-closed sur la signature.
WEBHOOK_PATHS = {
    ("post", "/travel/payments/callback"),
    ("post", "/travel/carrier-sync/trips"),
    ("post", "/restaurant/payments/{payment}/callback"),
    ("post", "/restaurant/webhooks/delivery-apps/{provider}"),
}

# Console plateforme : guard super_admin_api.
PLATFORM_PREFIXES = ("/platform/", "/admin/")

# (fichier de routes, tag OpenAPI, description du tag)
MODULES = [
    (
        "modules/connectors.php",
        "Connectors",
        "BC-14 INTEGRATION — connecteurs tenant : catalogue des surfaces "
        "externes (WordPress, Shopify, Facebook, TikTok Shop…), connexions "
        "chiffrees, test de connexion et bascule de statut. Lecture ouverte a "
        "tout manager, ecriture reservee au `principal`.",
    ),
    (
        "modules/rh.php",
        "Employees",
        "Permissions verticales par employe et enrolement des bornes de "
        "pointage (kiosques).",
    ),
    (
        "modules/showcase.php",
        "Pages",
        "BC-27 SHOWCASE — medias de la vitrine du tenant (upload, liste, "
        "suppression) et binaire public d'une vitrine publiee.",
    ),
    (
        "modules/solutions.php",
        "Catalog",
        "Solutions sectorielles — catalogue public, questionnaire de "
        "pre-qualification et pack de presentation telechargeable.",
    ),
    (
        "modules/marketing.php",
        "Marketing",
        "Marketing — assistance IA a la redaction de briefs de production.",
    ),
    (
        "modules/user.php",
        "Me",
        "Demandes d'integration d'un utilisateur dans une entreprise "
        "existante, cote manager.",
    ),
]

# Tag par prefixe de chemin, pour les routes declarees dans api.php.
API_PHP_TAG_RULES = [
    ("/public/travel/", "Travel"),
    ("/travel/", "Travel"),
    ("/public/restaurant/", "Restaurant"),
    ("/restaurant/", "Restaurant"),
    ("/public/vitrine/", "Pages"),
    ("/solutions", "Catalog"),
    ("/platform/connectors", "Connectors"),
    ("/platform/feature-kill-switches", "Platform Admin"),
    ("/platform/", "Platform Admin"),
    ("/admin/companies/", "Marketing"),
    ("/admin/blog/", "Marketing"),
    ("/admin/solutions/", "Platform Admin"),
    ("/admin/", "Platform Admin"),
]

# Parametres de chemin numeriques (identifiants de ressource).
NUMERIC_PARAM_HINTS = (
    "id",
    "payment",
    "media",
    "asset",
    "idea",
    "publication",
    "enrollment",
    "company",
)

# Parametres de chemin qui sont des CLES TEXTUELLES, pas des identifiants.
TEXT_PARAM_HINTS = (
    "slug",
    "code",
    "reference",
    "provider",
    "key",
    "devicecode",
    "travelTrip",
)

# Description metier par (methode, chemin) — ce que le code fait vraiment.
DESCRIPTIONS: dict[tuple[str, str], str] = {
    ("get", "/public/travel/shop/trips"): (
        "Recherche publique de trajets. Tenant resolu par le jeton de boutique "
        "signe (`travel.public.shop`), throttling `shop-public`. Aucun compte "
        "requis."
    ),
    ("get", "/public/travel/shop/trips/{travelTrip}"): (
        "Detail public d'un trajet publie. 404 fail-closed si le trajet n'est "
        "pas publie ou n'appartient pas au tenant du jeton."
    ),
    ("post", "/public/travel/shop/bookings"): (
        "Creation d'une reservation depuis la boutique publique. Le prix est "
        "recalcule cote serveur : un montant envoye par le client est ignore."
    ),
    ("get", "/public/travel/shop/bookings/{reference}"): (
        "Suivi d'une reservation par sa reference. Aucune donnee d'un autre "
        "passager n'est exposee."
    ),
    ("post", "/public/travel/shop/bookings/{reference}/cancel"): (
        "Annulation en ligne par reference + code de validation du billet, "
        "sans jeton boutique ni compte employe (#7395)."
    ),
    ("post", "/public/travel/payments/initiate"): (
        "Ouverture du tunnel de paiement en ligne d'une reservation publique. "
        "Retourne l'URL de la passerelle ; le statut n'est jamais pose ici."
    ),
    ("get", "/public/travel/tickets/{ticket}/pdf"): (
        "E-billet PDF d'une reservation payee. 404 fail-closed si le billet "
        "n'est pas emis."
    ),
    ("post", "/travel/payments/callback"): (
        "Callback de la passerelle de paiement TravelAgency. Signature HMAC "
        "verifiee dans le controleur, idempotent. Tenant resolu depuis le "
        "payload signe."
    ),
    ("post", "/travel/carrier-sync/trips"): (
        "API entrante de synchronisation des trajets transporteurs. Jeton "
        "`X-Carrier-Token`, upsert idempotent par cle externe."
    ),
    ("post", "/restaurant/payments/{payment}/callback"): (
        "Callback signe de confirmation mobile money de la verticale "
        "restaurant. Signature HMAC par tenant, fail-closed ; le tenant est "
        "resolu depuis le payload signe puis pose via TenantManager (#5272)."
    ),
    ("post", "/restaurant/webhooks/delivery-apps/{provider}"): (
        "Webhook entrant d'une application de livraison (Uber Eats, Glovo…). "
        "Signature HMAC fail-closed par adaptateur, secret par tenant."
    ),
    ("get", "/public/restaurant/shop/menu"): (
        "Menu public de la boutique restaurant. Tenant resolu par le jeton "
        "signe (`restaurant.public.shop`), hook anti-bot CAPTCHA configurable."
    ),
    ("post", "/public/restaurant/shop/orders"): (
        "Commande en ligne publique. Les prix sont recalcules cote serveur ; "
        "un article indisponible est refuse en 422."
    ),
    ("get", "/public/restaurant/shop/orders/{reference}"): (
        "Suivi d'une commande publique par sa reference."
    ),
    ("post", "/public/restaurant/shop/orders/{reference}/pay"): (
        "Ouverture du paiement en ligne d'une commande publique. Le statut de "
        "la commande n'est pose qu'au callback signe."
    ),
    ("get", "/public/restaurant/kiosk/menu"): (
        "Menu du kiosque libre-service (meme jeton boutique que la boutique "
        "web, RESTO-807)."
    ),
    ("post", "/public/restaurant/kiosk/orders"): (
        "Commande passee depuis le kiosque libre-service."
    ),
    ("get", "/public/restaurant/kiosk/orders/{reference}"): (
        "Suivi d'une commande kiosque par sa reference."
    ),
    ("get", "/connectors/catalog"): (
        "Catalogue des connecteurs disponibles (surfaces externes supportees, "
        "champs attendus, capacites). Lecture ouverte a tout manager du tenant."
    ),
    ("get", "/connectors/connections"): (
        "Connexions du tenant. Les secrets sont WRITE-ONLY : la reponse expose "
        "un masque, jamais la valeur stockee."
    ),
    ("get", "/connectors/overview"): (
        "Vue d'ensemble : connecteurs actifs, en erreur et jamais configures."
    ),
    ("post", "/connectors/connections"): (
        "Cree une connexion vers une surface externe. Reserve au `principal` : "
        "connecter un compte externe engage l'entreprise (#7727). Les secrets "
        "sont chiffres au repos et ne sont jamais relus."
    ),
    ("patch", "/connectors/connections/{id}"): (
        "Met a jour une connexion. Un secret absent du payload conserve la "
        "valeur existante ; un secret present la remplace."
    ),
    ("patch", "/connectors/connections/{id}/status"): (
        "Active ou desactive une connexion sans la supprimer."
    ),
    ("delete", "/connectors/connections/{id}"): (
        "Supprime une connexion et ses secrets. Irreversible."
    ),
    ("post", "/connectors/connections/{id}/test"): (
        "Teste la connexion en appelant reellement la surface externe. Le "
        "resultat est journalise ; un echec ne desactive pas la connexion."
    ),
    ("get", "/employees/{employee}/vertical-permissions"): (
        "Permissions verticales effectives d'un employe (verticales activees "
        "pour le tenant, croisees avec les droits de l'employe)."
    ),
    ("put", "/employees/{employee}/vertical-permissions"): (
        "Remplace les permissions verticales d'un employe. Une verticale non "
        "activee pour le tenant est refusee en 422."
    ),
    ("get", "/kiosks/{deviceCode}/sync-status"): (
        "Etat de synchronisation d'une borne de pointage : derniere remontee, "
        "file d'attente locale, enrolements en cours."
    ),
    ("post", "/kiosks/{deviceCode}/enrollments"): (
        "Demarre l'enrolement biometrique d'un employe sur une borne. Retourne "
        "un identifiant d'enrolement a activer."
    ),
    ("post", "/kiosks/{deviceCode}/enrollments/{enrollment}/activate"): (
        "Active un enrolement apres verification du gabarit biometrique."
    ),
    ("post", "/kiosks/{deviceCode}/enrollments/{enrollment}/revoke"): (
        "Revoque un enrolement : le gabarit est supprime, l'employe ne peut "
        "plus pointer sur cette borne."
    ),
    ("get", "/kiosks/{deviceCode}/enrollments/status"): (
        "Etat des enrolements d'une borne (en attente, actifs, revoques)."
    ),
    ("get", "/public/vitrine/{slug}/media/{media}"): (
        "Binaire public d'un media d'une vitrine PUBLIEE. Brouillon → 404 ; "
        "apercu prive via `?token=` (non cache)."
    ),
    ("get", "/showcase/media"): (
        "Liste les medias de la vitrine du tenant. Necessite le feature flag "
        "`company_showcase` (403 sinon)."
    ),
    ("post", "/showcase/media"): (
        "Televerse un media de vitrine (multipart). Edition reservee a "
        "`principal` et `rh`."
    ),
    ("delete", "/showcase/media/{media}"): (
        "Supprime un media de vitrine. Un media reference par une section "
        "publiee est refuse en 409."
    ),
    ("get", "/solutions"): (
        "Catalogue public des solutions sectorielles. Aucune authentification : "
        "pre-qualification avant inscription."
    ),
    ("get", "/solutions/{code}/survey"): (
        "Questions du questionnaire de pre-qualification d'une solution."
    ),
    ("post", "/solutions/{code}/survey"): (
        "Soumet les reponses et retourne la solution recommandee par le moteur "
        "de regles. Throttle strict (10/min) : moteur CPU pur, mais gratuit."
    ),
    ("get", "/solutions/{code}/pack"): (
        "Pack de presentation telechargeable d'une solution."
    ),
    ("post", "/marketing/ai/suggest-production-brief"): (
        "Propose un brief de production marketing a partir d'un objectif. "
        "Retourne une suggestion : rien n'est publie ni facture."
    ),
    ("get", "/company-integration-requests/manage"): (
        "Demandes d'integration d'utilisateurs dans l'entreprise, cote "
        "manager/RH (#5540)."
    ),
    ("get", "/platform/connectors/catalog"): (
        "Catalogue des connecteurs, vu plateforme (toutes surfaces, y compris "
        "celles non proposees aux tenants)."
    ),
    ("get", "/platform/connectors/overview"): (
        "Vue d'ensemble plateforme : connexions par tenant, taux d'echec des "
        "tests, connecteurs les plus utilises."
    ),
    ("get", "/platform/connectors/connections"): (
        "Connexions de TOUS les tenants. Les secrets restent write-only : la "
        "reponse expose un masque."
    ),
    ("post", "/platform/connectors/connections"): (
        "Cree une connexion au nom d'un tenant (support / onboarding)."
    ),
    ("patch", "/platform/connectors/connections/{id}"): (
        "Met a jour une connexion d'un tenant."
    ),
    ("patch", "/platform/connectors/connections/{id}/status"): (
        "Active ou desactive une connexion d'un tenant."
    ),
    ("delete", "/platform/connectors/connections/{id}"): (
        "Supprime une connexion d'un tenant. Irreversible."
    ),
    ("post", "/platform/connectors/connections/{id}/test"): (
        "Teste une connexion d'un tenant depuis la console plateforme."
    ),
    ("get", "/platform/feature-kill-switches"): (
        "Liste les interrupteurs d'urgence actifs. Permission "
        "`killswitch.manage`."
    ),
    ("post", "/platform/feature-kill-switches"): (
        "Active un interrupteur d'urgence : la fonctionnalite est coupee pour "
        "tous les tenants immediatement. Geste audite."
    ),
    ("delete", "/platform/feature-kill-switches/{key}"): (
        "Desactive un interrupteur d'urgence et retablit la fonctionnalite."
    ),
    ("get", "/admin/solutions/survey-stats"): (
        "Agregats des leads `solution_survey` (table `marketing_leads`) : "
        "conversion du wizard vitrine par solution. Permission `crm.view`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/overview"): (
        "Vue d'ensemble Marketing OS d'un tenant. Permission "
        "`marketing_os.view`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/campaigns"): (
        "Campagnes marketing d'un tenant. Permission `marketing_os.view`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/campaigns"): (
        "Cree une campagne. Permission `marketing_os.manage`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/ideas"): (
        "Idees de contenu d'un tenant. Permission `marketing_os.view`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/ideas"): (
        "Cree une idee de contenu. Permission `marketing_os.manage`."
    ),
    ("patch", "/admin/companies/{company}/marketing-os/ideas/{idea}"): (
        "Met a jour une idee de contenu. Permission `marketing_os.manage`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/ideas/{idea}/promote"): (
        "Promeut une idee en campagne. Permission `marketing_os.manage`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/assets"): (
        "Mediatheque marketing d'un tenant. Permission `marketing_os.view`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/assets"): (
        "Televerse un media marketing. Permission `marketing_os.manage`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/assets/{asset}/publications"): (
        "Publications d'un media. Permission `marketing_os.view`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/assets/{asset}/download"): (
        "Telecharge le binaire d'un media marketing (#8675 : la mediatheque "
        "etait en ecriture seule avant cette route)."
    ),
    ("get", "/admin/companies/{company}/marketing-os/assets/{asset}"): (
        "Detail d'un media marketing. Permission `marketing_os.view`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/publications"): (
        "Publications marketing d'un tenant. Permission `marketing_os.view`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/publications"): (
        "Cree une publication. Permission `marketing_os.manage`."
    ),
    ("patch", "/admin/companies/{company}/marketing-os/publications/{publication}/status"): (
        "Change le statut d'une publication (brouillon → planifiee → en ligne). "
        "Permission `marketing_os.manage`."
    ),
    ("post", "/admin/companies/{company}/marketing-os/publications/{publication}/stats/refresh"): (
        "Remonte les statistiques REELLES de la plateforme de publication "
        "(#8530). Aucune valeur inventee : « indisponible » est un etat "
        "explicite."
    ),
    ("get", "/admin/companies/{company}/marketing-os/visibility-objectives"): (
        "Objectifs de visibilite SEO/SEA/AEO/GEO d'un tenant (#8531)."
    ),
    ("post", "/admin/companies/{company}/marketing-os/visibility-objectives"): (
        "Cree un objectif de visibilite. Permission `marketing_os.manage`."
    ),
    ("get", "/admin/companies/{company}/marketing-os/publishing-capabilities"): (
        "Matrice des capacites REELLES de publication par plateforme (#8532) : "
        "quelles plateformes autorisent le direct, et pourquoi les autres "
        "restent en mode assiste."
    ),
    ("get", "/admin/blog/posts"): (
        "Articles du blog vitrine, cote Super Admin (#8708). Permission "
        "`marketing_os.view`."
    ),
    ("get", "/admin/blog/posts/{slug}"): (
        "Detail d'un article de blog par son slug."
    ),
    ("post", "/admin/blog/posts"): (
        "Cree un article de blog. Permission `marketing_os.manage`."
    ),
    ("put", "/admin/blog/posts/{slug}"): (
        "Met a jour un article de blog. Permission `marketing_os.manage`."
    ),
    ("delete", "/admin/blog/posts/{slug}"): (
        "Supprime un article de blog. Permission `marketing_os.manage`."
    ),
}

# Corps de requete : (methode, chemin) -> (description, schema inline ou None).
# `None` = objet libre, decrit honnetement plutot que fige a tort.
REQUEST_BODIES: dict[tuple[str, str], str] = {
    ("post", "/public/travel/shop/bookings"): (
        "Trajet, date, nombre de passagers et coordonnees du passager. Le prix "
        "est recalcule cote serveur."
    ),
    ("post", "/public/travel/payments/initiate"): (
        "Reference de reservation et moyen de paiement souhaite."
    ),
    ("post", "/public/restaurant/shop/orders"): (
        "Lignes de commande (`product_id`, `quantity`), mode de service "
        "(`dine_in` | `takeaway` | `delivery`) et coordonnees. Les prix sont "
        "recalcules cote serveur."
    ),
    ("post", "/public/restaurant/shop/orders/{reference}/pay"): (
        "Moyen de paiement souhaite pour la commande."
    ),
    ("post", "/public/restaurant/kiosk/orders"): (
        "Lignes de commande saisies au kiosque et numero de table."
    ),
    ("post", "/connectors/connections"): (
        "`connector_key` (cle du catalogue), `label`, et `credentials` "
        "(objet libre propre au connecteur). Les secrets sont chiffres au "
        "repos et ne sont jamais relus."
    ),
    ("patch", "/connectors/connections/{id}"): (
        "`label` et/ou `credentials`. Un secret absent conserve la valeur "
        "existante ; un secret present la remplace."
    ),
    ("patch", "/connectors/connections/{id}/status"): (
        "`status` : `active` ou `disabled`."
    ),
    ("put", "/employees/{employee}/vertical-permissions"): (
        "Liste des verticales autorisees pour l'employe. Une verticale non "
        "activee pour le tenant est refusee en 422."
    ),
    ("post", "/kiosks/{deviceCode}/enrollments"): (
        "`employee_id` et gabarit biometrique capture par la borne."
    ),
    ("post", "/showcase/media"): (
        "Multipart : `file` (image ou video) et `alt` optionnel."
    ),
    ("post", "/solutions/{code}/survey"): (
        "Reponses au questionnaire (objet cle → valeur). Le moteur de regles "
        "retourne la solution recommandee."
    ),
    ("post", "/marketing/ai/suggest-production-brief"): (
        "`objective` (texte libre) et contexte optionnel (verticale, audience)."
    ),
    ("post", "/platform/connectors/connections"): (
        "`company_id`, `connector_key`, `label` et `credentials`."
    ),
    ("patch", "/platform/connectors/connections/{id}"): (
        "`label` et/ou `credentials`."
    ),
    ("patch", "/platform/connectors/connections/{id}/status"): (
        "`status` : `active` ou `disabled`."
    ),
    ("post", "/platform/feature-kill-switches"): (
        "`key` de la fonctionnalite, `reason` et duree optionnelle."
    ),
    ("post", "/admin/companies/{company}/marketing-os/campaigns"): (
        "`name`, `objective`, periode et canaux."
    ),
    ("post", "/admin/companies/{company}/marketing-os/ideas"): (
        "`title`, `angle`, `channel` et notes."
    ),
    ("patch", "/admin/companies/{company}/marketing-os/ideas/{idea}"): (
        "Champs modifiables de l'idee (`title`, `angle`, `status`)."
    ),
    ("post", "/admin/companies/{company}/marketing-os/assets"): (
        "Multipart : `file` et metadonnees (`title`, `kind`)."
    ),
    ("post", "/admin/companies/{company}/marketing-os/publications"): (
        "`asset_id`, `platform`, `scheduled_at` et texte de publication."
    ),
    ("patch", "/admin/companies/{company}/marketing-os/publications/{publication}/status"): (
        "`status` cible de la publication."
    ),
    ("post", "/admin/companies/{company}/marketing-os/visibility-objectives"): (
        "`objective_type` (SEO/SEA/AEO/GEO), cible et echeance."
    ),
    ("post", "/admin/blog/posts"): (
        "`title`, `slug`, `body` (markdown), `status` et metadonnees SEO."
    ),
    ("put", "/admin/blog/posts/{slug}"): (
        "Champs modifiables de l'article."
    ),
}


def load_parser():
    """Importe le parseur de la garde CI (source unique de verite)."""
    sys.path.insert(0, str(TOOLS_DIR))
    import importlib

    return importlib.import_module("check-openapi-route-coverage")


def spec_path(raw_path: str) -> str:
    """`/v1/crm/channels` -> `/crm/channels` (la spec omet /v1)."""
    for prefix in ("/v1/api/v1", "/api/v1", "/api", "/v1"):
        if raw_path.startswith(prefix):
            stripped = raw_path[len(prefix):]
            return stripped if stripped.startswith("/") else "/" + stripped
    return raw_path


def slugify(path: str) -> str:
    s = path.strip("/").replace("{param}", "param")
    s = re.sub(r"[^a-zA-Z0-9]+", "_", s)
    return s.strip("_").lower()


def is_public(path: str) -> bool:
    return any(path.startswith(p) for p in PUBLIC_PREFIXES)


def is_webhook(verb: str, path: str) -> bool:
    return (verb, path) in WEBHOOK_PATHS


def is_platform(path: str) -> bool:
    return any(path.startswith(p) for p in PLATFORM_PREFIXES)


def tag_for(file: str, path: str) -> str:
    for module_file, tag, _ in MODULES:
        if file == module_file:
            return tag
    for prefix, tag in API_PHP_TAG_RULES:
        if path.startswith(prefix):
            return tag
    return "Platform Admin"


def param_schema(name: str) -> str:
    lowered = name.lower()
    if lowered in TEXT_PARAM_HINTS:
        return "string"
    if lowered in NUMERIC_PARAM_HINTS or lowered.endswith("_id"):
        return "integer"
    return "string"


def render_operation(verb: str, path: str, tag: str, params: list[str]) -> str:
    """Emet le bloc YAML d'une operation (indente pour `paths:`)."""
    op_id = f"api_{verb}_{slugify(path)}"
    lines: list[str] = []
    lines.append(f"    {verb}:")
    lines.append(f"      operationId: {op_id}")
    lines.append(f"      tags: [{tag}]")
    lines.append(f"      summary: {verb.upper()} {path}")

    description = DESCRIPTIONS.get((verb, path))
    if description is None:
        description = (
            "Tenant-scoped : le `company_id` est resolu depuis le token, jamais "
            "depuis la requete. Un identifiant appartenant a un autre tenant "
            "repond 404 (jamais 403, pour ne pas divulguer son existence)."
        )
    lines.append("      description: |")
    for chunk in _wrap(description):
        lines.append(f"        {chunk}")

    if is_public(path):
        lines.append("      security: []")
    elif is_webhook(verb, path):
        lines.append("      security: []")
    elif is_platform(path):
        lines.append("      security:")
        lines.append("      - SuperAdminBearerAuth: []")
    else:
        lines.append("      security:")
        lines.append("      - BearerAuth: []")

    if params:
        lines.append("      parameters:")
        for name in params:
            lines.append(f"      - name: {name}")
            lines.append("        in: path")
            lines.append("        required: true")
            lines.append("        schema:")
            lines.append(f"          type: {param_schema(name)}")

    body = REQUEST_BODIES.get((verb, path))
    if body is not None:
        lines.append("      requestBody:")
        lines.append("        required: true")
        # `description` doit vivre dans le SCHEMA, pas dans le Media Type
        # Object : en OpenAPI 3.0 un Media Type n'accepte que `schema`,
        # `example`, `examples` et `encoding` (Redocly : « Property
        # `description` is not expected here »). Le Schema Object, lui,
        # l'accepte.
        lines.append("        content:")
        lines.append("          application/json:")
        lines.append("            schema:")
        lines.append("              type: object")
        lines.append("              additionalProperties: true")
        lines.append("              description: |")
        for chunk in _wrap(body):
            lines.append(f"                {chunk}")

    lines.append("      responses:")
    if verb == "post":
        lines.append("        '201':")
        lines.append("          description: Ressource creee")
    else:
        lines.append("        '200':")
        lines.append("          description: Succes")

    if is_public(path) or is_webhook(verb, path):
        # Aucun token : le vrai 4xx est le 404 fail-closed (slug inconnu,
        # ressource non publiee) ou le 401 de signature invalide d'un webhook.
        if is_webhook(verb, path):
            lines.append("        '401':")
            lines.append("          description: Signature ou jeton invalide (fail-closed).")
        else:
            lines.append("        '404':")
            lines.append("          $ref: '#/components/responses/Error404'")
    else:
        lines.append("        '401':")
        lines.append("          $ref: '#/components/responses/Error401'")
        lines.append("        '403':")
        lines.append("          $ref: '#/components/responses/Error403'")
        if params:
            lines.append("        '404':")
            lines.append("          $ref: '#/components/responses/Error404'")

    if verb in ("post", "put", "patch"):
        lines.append("        '422':")
        lines.append("          $ref: '#/components/responses/Error422'")

    return "\n".join(lines)


def _wrap(text: str, width: int = 76) -> list[str]:
    words = text.split()
    out: list[str] = []
    cur = ""
    for w in words:
        if cur and len(cur) + 1 + len(w) > width:
            out.append(cur)
            cur = w
        else:
            cur = f"{cur} {w}".strip()
    if cur:
        out.append(cur)
    return out


def named_path(normalized: str, params: list[str]) -> str:
    """Reinjecte les noms reels des parametres dans un chemin normalise."""
    out = normalized
    for name in params:
        out = out.replace("{param}", "{" + name + "}", 1)
    return out


def collect_routes(parser) -> dict[str, dict[str, dict]]:
    """Routes cibles, groupees par tag."""
    wanted_files = {f for f, _, _ in MODULES}
    out: dict[str, dict[str, dict]] = {}

    for route in parser.parse_routes():
        file = route["file"]
        if file not in wanted_files and file != "api.php":
            continue
        params = list(route.get("params") or [])
        path = named_path(spec_path(route["path"]), params)
        tag = tag_for(file, path)
        entry = out.setdefault(tag, {}).setdefault(path, {"verbs": [], "params": params})
        if route["method"] not in entry["verbs"]:
            entry["verbs"].append(route["method"])

    return out


def normalize_path(path: str) -> str:
    """Forme de comparaison d'un chemin de spec.

    Deux normalisations, celles de la garde CI :

      - `{type}/{id}` -> `{param}/{param}` : un chemin deja documente sous une
        autre forme de parametre est couvert (sinon le generateur re-documente
        le chemin et Redocly signale deux chemins identiques a un nom de
        parametre pres) ;
      - prefixe `/v1` retire : la spec porte encore 107 chemins en forme
        legacy `/v1/...` (le reste est sans prefixe, les `servers` portant
        `/api/v1`). Sans ce retrait, `/v1/audit-logs/export` et
        `/audit-logs/export` etaient traites comme deux chemins distincts et
        l'`operationId` genere entrait en collision.
    """
    path = re.sub(r"\{[^}]+\}", "{param}", path)
    if path.startswith("/v1/"):
        path = path[len("/v1"):]
    return path


def existing_operations(text: str) -> set[tuple[str, str]]:
    """Operations deja presentes, chemins NORMALISES (cf. `normalize_path`)."""
    ops: set[tuple[str, str]] = set()
    current: str | None = None
    for line in text.splitlines():
        m = re.match(r"^  (/[^:]*):", line)
        if m:
            current = normalize_path(m.group(1))
            continue
        if current:
            m = re.match(r"^    (get|post|put|patch|delete|options|head):", line)
            if m:
                ops.add((m.group(1), current))
    return ops


def insert_paths(text: str, blocks: dict[str, list[str]]) -> str:
    """Insere les operations manquantes dans la section `paths:`."""
    lines = text.splitlines()
    try:
        start = lines.index("paths:")
    except ValueError:
        raise SystemExit("Section `paths:` introuvable dans openapi.yaml")

    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i] and not lines[i].startswith(" "):
            end = i
            break

    body = lines[start + 1:end]

    entries: list[tuple[str, list[str]]] = []
    current_path: str | None = None
    current_lines: list[str] = []
    for line in body:
        m = re.match(r"^  (/[^:]*):", line)
        if m:
            if current_path is not None:
                entries.append((current_path, current_lines))
            current_path = m.group(1)
            current_lines = [line]
        elif current_path is not None:
            current_lines.append(line)
        else:
            entries.append(("", [line]))
    if current_path is not None:
        entries.append((current_path, current_lines))

    # Comparaison sur la forme NORMALISEE : un chemin deja present sous
    # `{type}/{id}` doit recevoir les verbes manquants, pas etre duplique sous
    # `{param}/{param}` (Redocly : « path already exists which differs only by
    # path parameter name(s) »).
    known = {normalize_path(p): p for p, _ in entries if p}
    for path, op_lines in blocks.items():
        key = normalize_path(path)
        if key in known:
            target = known[key]
            for idx, (existing_path, chunk) in enumerate(entries):
                if existing_path == target:
                    entries[idx] = (existing_path, chunk + op_lines)
                    break
        else:
            entries.append((path, [f"  {path}:"] + op_lines))
            known[key] = path

    head = [e for e in entries if not e[0]]
    tail = sorted([e for e in entries if e[0]], key=lambda e: e[0])

    new_body: list[str] = []
    for _, chunk in head + tail:
        new_body.extend(chunk)

    return "\n".join(lines[: start + 1] + new_body + lines[end:]) + "\n"


def ensure_tags(text: str) -> str:
    lines = text.splitlines()
    try:
        start = lines.index("tags:")
    except ValueError:
        raise SystemExit("Section `tags:` introuvable dans openapi.yaml")

    end = len(lines)
    for i in range(start + 1, len(lines)):
        if lines[i] and not lines[i].startswith(" "):
            end = i
            break

    # Les tags de ce fichier sont a l'INDENT 0 (`- name: X`), pas sous `tags:`.
    # L'ancien motif `^- name:` etait donc correct, mais il ne parcourait que
    # `lines[start+1:end]` — or `tags:` est la PREMIERE cle du document et
    # `end` tombait sur `info:`. On balaie tout le document.
    existing = set()
    for line in lines:
        m = re.match(r"^- name: (.+)$", line)
        if m:
            existing.add(m.group(1).strip())

    additions: list[str] = []
    for _, tag, description in MODULES:
        if tag in existing:
            continue
        additions.append(f"- name: {tag}")
        # YAML a quotes simples : une apostrophe doit etre doublee, sinon le
        # scalaire se termine au milieu de la phrase et le document devient
        # invalide (observe au premier essai : `d'integration`).
        additions.append(f"  description: '{description.replace(chr(39), chr(39) * 2)}'")
        existing.add(tag)

    if not additions:
        return text

    return "\n".join(lines[:end] + additions + lines[end:]) + "\n"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="ne rien ecrire, sortir 1 si incomplet")
    args = ap.parse_args()

    parser = load_parser()
    routes = collect_routes(parser)

    text = OPENAPI_FILE.read_text(encoding="utf-8")
    ops = existing_operations(text)

    blocks: dict[str, list[str]] = {}
    missing = 0
    for tag, paths in routes.items():
        for path, entry in sorted(paths.items()):
            # `ops` porte des chemins NORMALISES (cf. `existing_operations`) :
            # comparer le chemin nomme tel quel re-documentait des operations
            # deja presentes sous une autre forme de parametre.
            key = normalize_path(path)
            todo = [v for v in entry["verbs"] if (v, key) not in ops]
            if not todo:
                continue
            missing += len(todo)
            blocks[path] = [
                render_operation(v, path, tag, entry["params"]) for v in sorted(todo)
            ]

    total = sum(len(e["verbs"]) for paths in routes.values() for e in paths.values())
    print(f"routes cibles : {total}")
    print(f"operations manquantes : {missing}")

    if args.check:
        return 1 if missing else 0

    if not blocks:
        print("Rien a inserer.")
        return 0

    text = insert_paths(text, blocks)
    text = ensure_tags(text)
    OPENAPI_FILE.write_text(text, encoding="utf-8")
    print(f"{len(blocks)} chemins inseres dans {OPENAPI_FILE.relative_to(REPO_ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
