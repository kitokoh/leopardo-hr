"""
automation/anti_block.py — Gestion des limitations anti-blocage

CORRECTIONS v4 :
  - can_post_now() : vérifie la limite horaire (max_groups_per_hour)
  - record_post(text, images) : méthode publique unifiée pour enregistrer un post
  - can_use_image(path) : vérifie que l'image n'est pas sur-utilisée (max 2×)
  - Singleton get_anti_block_manager() exposé (comme get_health_manager)
  - Long pause après N publications
  - Nettoyage automatique des données > 24h
"""

import random
import time
import json
import threading
import pathlib
from datetime import datetime, timedelta
from typing import List, Optional


class AntiBlockManager:
    """
    Gère les limitations anti-blocage Facebook.

    Règles implémentées :
    - max_groups_per_hour : Maximum 5 groupes par heure
    - delay_between_posts : Pause aléatoire 30–120 secondes (géré par timing_humanizer)
    - long_pause_after    : Pause longue après N publications
    - no_duplicate_text   : Pas de même texte deux fois de suite (géré dans Scraper._pick_post)
    - no_overused_image   : Pas de même image plus de 2 fois par session
    """

    def __init__(self, state_file: str = None):
        if state_file is None:
            try:
                from libs.config_manager import LOGS_DIR
                state_file = str(LOGS_DIR / "anti_block_state.json")
            except ImportError:
                state_file = "logs/anti_block_state.json"

        self.state_file = pathlib.Path(state_file)
        self.state_file.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()

        # Configuration
        self.max_groups_per_hour      = 5
        self.max_image_uses           = 2
        self.long_pause_after_posts   = 3
        self.long_pause_min_minutes   = 30
        self.long_pause_max_minutes   = 60

        self.state = self._load()

    # ──────────────────────────────────────────
    # Persistance
    # ──────────────────────────────────────────

    def _load(self) -> dict:
        """Charge l'état depuis le fichier JSON."""
        if self.state_file.exists():
            try:
                with open(self.state_file, "r", encoding="utf-8") as f:
                    return json.load(f)
            except Exception:
                pass
        return self._empty_state()

    def _save(self) -> None:
        """Sauvegarde l'état sur disque."""
        self._cleanup_old_data()
        try:
            with open(self.state_file, "w", encoding="utf-8") as f:
                json.dump(self.state, f, indent=2, ensure_ascii=False)
        except Exception:
            pass  # Ne pas crasher si le disque est plein

    @staticmethod
    def _empty_state() -> dict:
        return {
            "posts_this_hour": [],   # [{"time": ISO, "text": ..., "images": [...]}]
            "image_uses": {},        # {path: count}
            "long_pause_until": None,
        }

    def _cleanup_old_data(self) -> None:
        """Supprime les entrées de plus d'une heure de posts_this_hour."""
        cutoff = datetime.now() - timedelta(hours=1)
        self.state["posts_this_hour"] = [
            p for p in self.state.get("posts_this_hour", [])
            if datetime.fromisoformat(p["time"]) > cutoff
        ]

    # ──────────────────────────────────────────
    # API publique
    # ──────────────────────────────────────────

    def can_post_now(self) -> bool:
        """
        Retourne True si on peut poster maintenant (limite horaire non atteinte
        et pas en pause longue).
        """
        with self._lock:
            self._cleanup_old_data()

            # Vérifier la pause longue
            pause_until = self.state.get("long_pause_until")
            if pause_until:
                try:
                    until_dt = datetime.fromisoformat(pause_until)
                    if datetime.now() < until_dt:
                        remaining = (until_dt - datetime.now()).total_seconds() / 60
                        try:
                            from libs.log_emitter import emit
                            emit("WARN", "ANTI_BLOCK_LONG_PAUSE",
                                 remaining_minutes=round(remaining, 1))
                        except ImportError:
                            pass
                        return False
                    else:
                        self.state["long_pause_until"] = None
                except (ValueError, TypeError):
                    self.state["long_pause_until"] = None

            # Vérifier la limite horaire
            posts_count = len(self.state.get("posts_this_hour", []))
            return posts_count < self.max_groups_per_hour

    def can_use_image(self, image_path: str) -> bool:
        """
        Retourne True si l'image n'a pas encore atteint sa limite d'usages.
        """
        with self._lock:
            uses = self.state.get("image_uses", {}).get(image_path, 0)
            return uses < self.max_image_uses

    def record_post(self, text: str = "", images: List[str] = None) -> None:
        """
        Enregistre un post publié :
        - Incrémente le compteur horaire
        - Incrémente l'usage de chaque image
        - Déclenche une pause longue si seuil atteint
        """
        images = images or []
        now = datetime.now()

        with self._lock:
            self._cleanup_old_data()

            # Ajouter à la liste horaire
            self.state.setdefault("posts_this_hour", []).append({
                "time": now.isoformat(),
                "text": text[:100],  # on ne stocke pas tout le texte
                "images": [p[-40:] for p in images],  # chemin raccourci
            })

            # Incrémenter l'usage des images
            image_uses = self.state.setdefault("image_uses", {})
            for img in images:
                image_uses[img] = image_uses.get(img, 0) + 1

            # Pause longue si seuil atteint
            total_this_hour = len(self.state["posts_this_hour"])
            if total_this_hour > 0 and total_this_hour % self.long_pause_after_posts == 0:
                pause_min = random.randint(
                    self.long_pause_min_minutes,
                    self.long_pause_max_minutes
                )
                until = now + timedelta(minutes=pause_min)
                self.state["long_pause_until"] = until.isoformat()
                try:
                    from libs.log_emitter import emit
                    emit("INFO", "ANTI_BLOCK_LONG_PAUSE_SCHEDULED",
                         after_posts=total_this_hour,
                         pause_minutes=pause_min,
                         until=until.strftime("%H:%M"))
                except ImportError:
                    pass

            self._save()

    def reset_image_uses(self) -> None:
        """Remet à zéro le compteur d'usage des images (par exemple au début d'une nouvelle journée)."""
        with self._lock:
            self.state["image_uses"] = {}
            self._save()

    def get_hourly_post_count(self) -> int:
        """Retourne le nombre de posts effectués dans l'heure écoulée."""
        with self._lock:
            self._cleanup_old_data()
            return len(self.state.get("posts_this_hour", []))

    def get_image_use_count(self, image_path: str) -> int:
        """Retourne le nombre de fois qu'une image a été utilisée."""
        with self._lock:
            return self.state.get("image_uses", {}).get(image_path, 0)


# ──────────────────────────────────────────
# Singleton thread-safe
# ──────────────────────────────────────────

_anti_block_manager: Optional[AntiBlockManager] = None
_abm_lock = threading.Lock()


def get_anti_block_manager() -> AntiBlockManager:
    global _anti_block_manager
    if _anti_block_manager is None:
        with _abm_lock:
            if _anti_block_manager is None:
                _anti_block_manager = AntiBlockManager()
    return _anti_block_manager
