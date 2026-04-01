"""
log_emitter.py — Émetteur de logs structurés JSON Lines
Format lisible par l'app PyQt via un simple tail du fichier activity.jsonl
"""
import json
import datetime
import pathlib
import sys

# Import du répertoire de logs (évite import circulaire)
try:
    from libs.config_manager import LOGS_DIR
except ImportError:
    LOGS_DIR = pathlib.Path("logs")
    LOGS_DIR.mkdir(exist_ok=True)

LOG_FILE = LOGS_DIR / "activity.jsonl"
PID_FILE = LOGS_DIR / "running.pid"


def emit(level: str, event: str, **kwargs) -> None:
    """
    Émet un événement de log structuré en JSON Lines.
    Format : {"ts": "...", "level": "INFO", "event": "POST_PUBLISHED", ...}

    Niveaux : DEBUG, INFO, SUCCESS, WARN, ERROR
    """
    entry = {
        "ts": datetime.datetime.now().isoformat(timespec="seconds"),
        "level": level,
        "event": event,
        **kwargs,
    }
    line = json.dumps(entry, ensure_ascii=False)

    # Écriture dans le fichier de log
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception as e:
        print(f"[LOG] Impossible d'écrire dans {LOG_FILE}: {e}", file=sys.stderr)

    # Affichage console pour debug
    _console_print(level, event, kwargs)


def _console_print(level: str, event: str, details: dict) -> None:
    """Affichage coloré en console."""
    colors = {
        "DEBUG":   "\033[37m",    # Gris
        "INFO":    "\033[36m",    # Cyan
        "SUCCESS": "\033[32m",    # Vert
        "WARN":    "\033[33m",    # Jaune
        "ERROR":   "\033[31m",    # Rouge
    }
    reset = "\033[0m"
    color = colors.get(level, "")
    detail_str = " | ".join(f"{k}={v}" for k, v in details.items()) if details else ""
    msg = f"{color}[{level}] {event}{reset}"
    if detail_str:
        msg += f"  {detail_str}"
    print(msg)


# Raccourcis pratiques
def log_info(event: str, **kwargs) -> None:
    emit("INFO", event, **kwargs)

def log_success(event: str, **kwargs) -> None:
    emit("SUCCESS", event, **kwargs)

def log_warn(event: str, **kwargs) -> None:
    emit("WARN", event, **kwargs)

def log_error(event: str, **kwargs) -> None:
    emit("ERROR", event, **kwargs)

def log_debug(event: str, **kwargs) -> None:
    emit("DEBUG", event, **kwargs)


def write_pid() -> None:
    """Écrit le PID du processus courant (signale à PyQt que le module tourne)."""
    import os
    try:
        with open(PID_FILE, "w") as f:
            f.write(str(os.getpid()))
    except Exception as e:
        emit("WARN", "PID_WRITE_FAILED", error=str(e))


def clear_pid() -> None:
    """Supprime le fichier PID à l'arrêt propre du module."""
    try:
        PID_FILE.unlink(missing_ok=True)
    except Exception:
        pass


def get_recent_logs(n: int = 50) -> list[dict]:
    """Retourne les n derniers événements de log (pour affichage PyQt au démarrage)."""
    try:
        with open(LOG_FILE, "r", encoding="utf-8") as f:
            lines = f.readlines()
        result = []
        for line in lines[-n:]:
            try:
                result.append(json.loads(line.strip()))
            except json.JSONDecodeError:
                pass
        return result
    except FileNotFoundError:
        return []
