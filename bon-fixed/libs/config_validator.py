"""
config_validator.py — Validation de la configuration de session au démarrage
Vérifie les URLs de groupes, les chemins d'images, les paramètres obligatoires.
"""
import pathlib
import re
from typing import Optional

try:
    from libs.log_emitter import emit
    from libs.config_manager import resolve_media_path
except ImportError:
    from log_emitter import emit
    from config_manager import resolve_media_path

FB_GROUP_RE = re.compile(
    r"^https?://(?:www\.)?facebook\.com/groups/[\w./-]+/?$"
)


class ConfigError(Exception):
    """Erreur de configuration bloquante."""
    pass


class ConfigWarning(Exception):
    """Avertissement de configuration non bloquant."""
    pass


def validate_session_config(config: dict, session_name: str) -> list[str]:
    """
    Valide la configuration d'une session.

    Returns:
        Liste de messages d'avertissement (vide = tout OK)

    Raises:
        ConfigError si une erreur bloquante est détectée
    """
    warnings = []
    errors = []

    # ── Champs obligatoires ─────────────────────────────────
    if not config.get("session_name"):
        errors.append("session_name manquant")

    if not config.get("storage_state"):
        errors.append("storage_state manquant (session non créée ?)")
    elif not pathlib.Path(config["storage_state"]).exists():
        errors.append(
            f"Fichier session introuvable : {config['storage_state']}\n"
            f"  → Lancez : python -m bon login --session {session_name}"
        )

    # ── Groupes ─────────────────────────────────────────────
    groups = config.get("groups", [])
    if not groups:
        warnings.append("Aucun groupe configuré — rien à publier")
    else:
        invalid_groups = []
        for url in groups:
            if not FB_GROUP_RE.match(url):
                invalid_groups.append(url)
        if invalid_groups:
            warnings.append(
                f"{len(invalid_groups)} URL(s) de groupe invalide(s) :\n" +
                "\n".join(f"  • {u}" for u in invalid_groups[:5])
            )

    # ── Posts ────────────────────────────────────────────────
    posts = config.get("posts", [])
    if not posts:
        warnings.append("Aucun post configuré — rien à publier")
    else:
        missing_images = []
        for i, post in enumerate(posts):
            if not post.get("text", "").strip():
                warnings.append(f"Post #{i+1} : texte vide")
            image = post.get("image", "") or (post.get("images", [""])[0] if post.get("images") else "")
            if image:
                resolved = resolve_media_path(image, session_name)
                if not resolved.exists():
                    missing_images.append(str(resolved))
        if missing_images:
            warnings.append(
                f"{len(missing_images)} image(s) introuvable(s) :\n" +
                "\n".join(f"  • {p}" for p in missing_images[:5])
            )

    # ── Paramètres numériques ────────────────────────────────
    max_groups = config.get("max_groups_per_run", 10)
    if not isinstance(max_groups, int) or max_groups < 1:
        warnings.append(f"max_groups_per_run invalide ({max_groups}) → forcé à 10")

    delay = config.get("delay_between_groups", [60, 120])
    if not (isinstance(delay, list) and len(delay) == 2 and delay[0] <= delay[1]):
        warnings.append(f"delay_between_groups invalide ({delay}) → valeur défaut [60, 120]")

    max_runs = config.get("max_runs_per_day", 2)
    if not isinstance(max_runs, int) or max_runs < 1:
        warnings.append(f"max_runs_per_day invalide ({max_runs}) → forcé à 2")

    # ── Émission des logs ────────────────────────────────────
    for w in warnings:
        emit("WARN", "CONFIG_WARNING", session=session_name, msg=w)

    if errors:
        error_text = "\n".join(f"  ✗ {e}" for e in errors)
        emit("ERROR", "CONFIG_INVALID", session=session_name, count=len(errors))
        raise ConfigError(
            f"Configuration invalide pour '{session_name}' :\n{error_text}"
        )

    if not warnings:
        emit("INFO", "CONFIG_OK", session=session_name,
             groups=len(groups), posts=len(posts))

    return warnings


def validate_selectors(selectors_data: dict) -> list[str]:
    """
    Vérifie que le fichier selectors.json contient bien les clés essentielles.

    Returns:
        Liste d'avertissements (vide = OK)
    """
    warnings = []
    required_keys = [
        "display_input", "input", "submit",
        "show_image_input", "add_image",
    ]
    sel = selectors_data.get("selectors", selectors_data)  # support ancien format
    for key in required_keys:
        if key not in sel:
            warnings.append(f"Sélecteur manquant : '{key}'")

    version = selectors_data.get("version", "legacy")
    if version == "legacy":
        warnings.append(
            "Sélecteurs en format legacy (ancien format plat). "
            "Migrez vers le format v2 avec listes de fallback."
        )

    for w in warnings:
        emit("WARN", "SELECTORS_WARNING", msg=w)

    return warnings
