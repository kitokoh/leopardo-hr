"""
config_manager.py — Gestion des chemins cross-platform (Windows / Linux / macOS)
"""
import sys
import json
import pathlib
from typing import Optional


def get_app_dir() -> pathlib.Path:
    """Retourne le répertoire de données de l'application selon l'OS."""
    if sys.platform == "win32":
        base = pathlib.Path.home() / "AppData" / "Roaming" / "bon"
    elif sys.platform == "darwin":
        base = pathlib.Path.home() / "Library" / "Application Support" / "bon"
    else:  # Linux et autres Unix
        base = pathlib.Path.home() / ".config" / "bon"
    base.mkdir(parents=True, exist_ok=True)
    return base


# Répertoires standardisés de l'application
APP_DIR = get_app_dir()
SESSIONS_DIR = APP_DIR / "sessions"    # storage_state Playwright par compte
LOGS_DIR = APP_DIR / "logs"            # activity.jsonl
MEDIA_DIR = APP_DIR / "media"          # images des posts
CONFIG_DIR = APP_DIR / "config"        # selectors.json, data.json

# Créer les sous-dossiers si nécessaire
for _dir in (SESSIONS_DIR, LOGS_DIR, MEDIA_DIR, CONFIG_DIR):
    _dir.mkdir(parents=True, exist_ok=True)


def load_json(path: pathlib.Path) -> dict:
    """Charge un fichier JSON de façon sécurisée."""
    try:
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return {}
    except json.JSONDecodeError as e:
        print(f"[CONFIG] Erreur JSON dans {path}: {e}")
        return {}


def save_json(path: pathlib.Path, data: dict) -> bool:
    """Sauvegarde un dictionnaire en JSON."""
    try:
        path.parent.mkdir(parents=True, exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=4, ensure_ascii=False)
        return True
    except Exception as e:
        print(f"[CONFIG] Erreur sauvegarde {path}: {e}")
        return False


def list_sessions() -> list[str]:
    """Liste les noms de sessions disponibles."""
    return [p.stem for p in SESSIONS_DIR.glob("*_state.json")]


def get_session_config_path(session_name: str) -> pathlib.Path:
    """Retourne le chemin du fichier de config d'une session."""
    return SESSIONS_DIR / f"{session_name}.json"


def get_session_state_path(session_name: str) -> pathlib.Path:
    """Retourne le chemin du storage_state Playwright d'une session."""
    return SESSIONS_DIR / f"{session_name}_state.json"


def resolve_media_path(relative_or_absolute: str, session_name: Optional[str] = None) -> pathlib.Path:
    """
    Résout un chemin d'image : absolu → retourné tel quel,
    relatif → résolu depuis MEDIA_DIR / session_name.
    Gère correctement les chemins Windows legacy (antislash) sur Linux/macOS.
    """
    s = relative_or_absolute.strip()

    # Extraire uniquement le nom de fichier depuis un chemin Windows legacy
    # (ex: C:\Users\...\media\7.png  →  7.png)
    # PureWindowsPath parse les antislash correctement sur tous les OS.
    if "\\" in s:
        filename = pathlib.PureWindowsPath(s).name
    else:
        filename = pathlib.Path(s).name

    # Si c'est un chemin absolu natif ET qu'il existe → l'utiliser directement
    p = pathlib.Path(s)
    if p.is_absolute() and p.exists():
        return p

    # Chercher dans MEDIA_DIR / session_name / filename
    if session_name:
        candidate = MEDIA_DIR / session_name / filename
        if candidate.exists():
            return candidate

    # Chercher dans MEDIA_DIR / filename
    candidate = MEDIA_DIR / filename
    if candidate.exists():
        return candidate

    # Fallback : retourner MEDIA_DIR / filename (chemin attendu, peut ne pas exister)
    return MEDIA_DIR / filename
