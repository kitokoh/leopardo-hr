"""
session_manager.py — Gestion des sessions Playwright par compte
Remplace l'ancien système chrome_folder basé sur les profils Chrome
"""
import pathlib
import json
from typing import Optional

try:
    from libs.config_manager import (
        SESSIONS_DIR, CONFIG_DIR, load_json, save_json,
        get_session_config_path, get_session_state_path
    )
    from libs.log_emitter import emit
except ImportError:
    from config_manager import (
        SESSIONS_DIR, CONFIG_DIR, load_json, save_json,
        get_session_config_path, get_session_state_path
    )
    from log_emitter import emit


# Config par défaut pour une nouvelle session
DEFAULT_SESSION_CONFIG = {
    "session_name": "",
    "storage_state": "",          # chemin vers le fichier state Playwright
    "max_groups_per_run": 10,
    "delay_between_groups": [60, 120],
    "max_runs_per_day": 2,
    "cooldown_between_runs_s": 7200,  # 2 heures entre deux runs
    "last_run_ts": None,          # timestamp ISO du dernier lancement
    "last_run_date": None,        # date ISO du dernier lancement (YYYY-MM-DD)
    "run_count_today": 0,
    "posts": [],
    "groups": [],
    "add_comments": False,        # ajouter un commentaire après chaque post
    "comments": [],               # liste de commentaires personnalisés (vide = défauts)
    "marketplace": {},            # config Marketplace (vide = désactivé)
}


class SessionManager:
    """
    Gère les sessions Playwright (création, restauration, vérification).
    Chaque compte Facebook = 1 fichier {name}_state.json (storage_state)
                           + 1 fichier {name}.json (config)
    """

    def list_sessions(self) -> list[str]:
        """Liste tous les noms de sessions disponibles."""
        sessions = [p.stem.replace("_state", "") 
                    for p in SESSIONS_DIR.glob("*_state.json")]
        return sorted(sessions)

    def session_exists(self, session_name: str) -> bool:
        return get_session_state_path(session_name).exists()

    def get_config(self, session_name: str) -> dict:
        """Charge la configuration d'une session (ou retourne les défauts)."""
        path = get_session_config_path(session_name)
        config = load_json(path)
        if not config:
            config = dict(DEFAULT_SESSION_CONFIG)
            config["session_name"] = session_name
            config["storage_state"] = str(get_session_state_path(session_name))
        return config

    def save_config(self, session_name: str, config: dict) -> bool:
        """Sauvegarde la configuration d'une session."""
        path = get_session_config_path(session_name)
        return save_json(path, config)

    def create_session(self, session_name: str, browser) -> bool:
        """
        Lance une fenêtre de navigateur pour login manuel, puis sauvegarde la session.
        Appelé depuis l'app PyQt pour ajouter un nouveau compte.

        Args:
            session_name: identifiant du compte (ex: "compte1")
            browser: instance Playwright browser

        Returns:
            True si session créée et sauvegardée avec succès
        """
        state_path = get_session_state_path(session_name)
        emit("INFO", "SESSION_CREATE_START", session=session_name)

        try:
            context = browser.new_context()
            page = context.new_page()
            page.goto("https://www.facebook.com/login")

            print(f"\n[SESSION] Connectez-vous manuellement à Facebook pour '{session_name}'")
            print("[SESSION] Appuyez sur ENTRÉE une fois connecté...")
            input()

            # Vérifier qu'on est bien connecté
            if "/login" in page.url:
                emit("WARN", "SESSION_LOGIN_FAILED", session=session_name)
                context.close()
                return False

            # Sauvegarder l'état de la session
            context.storage_state(path=str(state_path))
            emit("SUCCESS", "SESSION_SAVED",
                 session=session_name, path=str(state_path))

            # Créer la config par défaut
            config = dict(DEFAULT_SESSION_CONFIG)
            config["session_name"] = session_name
            config["storage_state"] = str(state_path)
            self.save_config(session_name, config)

            context.close()
            return True

        except Exception as e:
            emit("ERROR", "SESSION_CREATE_ERROR", session=session_name, error=str(e))
            return False

    def restore_context(self, session_name: str, browser):
        """
        Crée un contexte Playwright à partir d'une session sauvegardée.
        Remplace l'ancienne gestion du chrome_folder.

        Returns:
            (context, page) ou lève une exception si session introuvable
        """
        state_path = get_session_state_path(session_name)
        if not state_path.exists():
            raise FileNotFoundError(
                f"Session '{session_name}' introuvable : {state_path}"
            )
        emit("INFO", "SESSION_RESTORE", session=session_name)
        context = browser.new_context(storage_state=str(state_path))
        page = context.new_page()
        return context, page

    def check_session_valid(self, page) -> bool:
        """
        Vérifie si la session est encore valide (non expirée).
        À appeler après avoir chargé une page Facebook.
        """
        url = page.url
        if "/login" in url or "login.php" in url:
            emit("WARN", "SESSION_EXPIRED_DETECTED")
            return False
        return True

    def save_state(self, context, session_name: str) -> bool:
        """Sauvegarde l'état courant d'une session (après modifications cookies, etc.)."""
        state_path = get_session_state_path(session_name)
        try:
            context.storage_state(path=str(state_path))
            emit("INFO", "SESSION_STATE_SAVED", session=session_name)
            return True
        except Exception as e:
            emit("ERROR", "SESSION_STATE_SAVE_ERROR",
                 session=session_name, error=str(e))
            return False

    def delete_session(self, session_name: str) -> bool:
        """Supprime une session (état + config)."""
        ok = True
        for path in [get_session_state_path(session_name),
                     get_session_config_path(session_name)]:
            try:
                path.unlink(missing_ok=True)
            except Exception as e:
                emit("WARN", "SESSION_DELETE_ERROR",
                     session=session_name, file=str(path), error=str(e))
                ok = False
        if ok:
            emit("INFO", "SESSION_DELETED", session=session_name)
        return ok
