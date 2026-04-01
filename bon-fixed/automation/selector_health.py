"""
automation/selector_health.py — Gestion de la santé des sélecteurs

CORRECTIONS v3:
  - record_success(selector_key, used_selector) — 2e argument optionnel avec default
  - record_failure(selector_key, reason, tried_selectors) — tried_selectors optionnel
  - Singleton thread-safe
  - Persistence sur fichier JSON (les stats DB sont gérées par database.py séparément)
"""

import json
import pathlib
import threading
from datetime import datetime
from typing import Optional, List


class SelectorHealthManager:
    """
    Gère la santé des sélecteurs Facebook.

    - Enregistre les tentatives (succès/échec)
    - Calcule les taux de réussite
    - Détecte les sélecteurs dégradés (< seuil configurable)
    - Sauvegarde sur disque en JSON Lines
    """

    def __init__(self, health_file: str = None):
        if health_file is None:
            try:
                from libs.config_manager import LOGS_DIR
                health_file = str(LOGS_DIR / "selector_health.json")
            except ImportError:
                health_file = "logs/selector_health.json"

        self.health_file = pathlib.Path(health_file)
        self.health_file.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()
        self.data  = self._load()

    def _load(self) -> dict:
        if self.health_file.exists():
            try:
                with open(self.health_file, "r", encoding="utf-8") as f:
                    return json.load(f)
            except Exception:
                pass
        return {}

    def _save(self):
        with open(self.health_file, "w", encoding="utf-8") as f:
            json.dump(self.data, f, indent=2, ensure_ascii=False)

    # ──────────────────────────────────────────
    # API publique — signatures corrigées
    # ──────────────────────────────────────────

    def record_success(self, selector_key: str,
                       used_selector: str = "") -> None:
        """
        Enregistre un succès.

        Args:
            selector_key:  clé du sélecteur (ex: "display_input")
            used_selector: valeur CSS/XPath réellement utilisée (optionnel)
        """
        now = datetime.now().isoformat()
        with self._lock:
            entry = self.data.setdefault(selector_key, self._empty_entry())
            entry["total_attempts"]      += 1
            entry["successful_attempts"] += 1
            entry["last_success"]         = now
            if used_selector:
                entry["working_selector"] = used_selector
            entry["success_rate"] = self._rate(entry)
            self._save()

    def record_failure(self, selector_key: str,
                       reason: str = "",
                       tried_selectors: List[str] = None) -> None:
        """
        Enregistre un échec.

        Args:
            selector_key:     clé du sélecteur
            reason:           message d'erreur
            tried_selectors:  liste des sélecteurs essayés (optionnel)
        """
        now = datetime.now().isoformat()
        tried_selectors = tried_selectors or []
        with self._lock:
            entry = self.data.setdefault(selector_key, self._empty_entry())
            entry["total_attempts"]   += 1
            entry["failed_attempts"]  += 1
            entry["last_failure_reason"] = reason

            # Mémoriser les sélecteurs alternatifs testés
            alts = entry.setdefault("alternative_selectors", [])
            for sel in tried_selectors:
                if sel not in alts:
                    alts.append(sel)

            entry["success_rate"] = self._rate(entry)
            self._save()

    # ──────────────────────────────────────────
    # Helpers
    # ──────────────────────────────────────────

    @staticmethod
    def _empty_entry() -> dict:
        return {
            "working_selector":    None,
            "success_rate":        100,
            "last_success":        None,
            "total_attempts":      0,
            "successful_attempts": 0,
            "failed_attempts":     0,
            "last_failure_reason": None,
            "alternative_selectors": [],
        }

    @staticmethod
    def _rate(entry: dict) -> int:
        total = entry["total_attempts"]
        if total == 0:
            return 100
        return round(entry["successful_attempts"] * 100 / total)

    # ──────────────────────────────────────────
    # Requêtes
    # ──────────────────────────────────────────

    def is_healthy(self, selector_key: str, min_rate: int = 50) -> bool:
        entry = self.data.get(selector_key)
        if not entry:
            return True  # Aucune donnée = considéré sain
        return entry.get("success_rate", 0) >= min_rate

    def get_working_selector(self, selector_key: str) -> Optional[str]:
        entry = self.data.get(selector_key)
        return entry["working_selector"] if entry else None

    def get_alternatives(self, selector_key: str) -> List[str]:
        entry = self.data.get(selector_key)
        return entry.get("alternative_selectors", []) if entry else []

    def get_stats(self, selector_key: str) -> Optional[dict]:
        return self.data.get(selector_key)

    def get_all_stats(self) -> dict:
        return dict(self.data)

    def detect_dead_selectors(self, threshold: int = 30) -> List[str]:
        """Retourne les clés dont le taux de succès est inférieur au seuil."""
        return [
            key for key, entry in self.data.items()
            if entry.get("success_rate", 100) < threshold
        ]

    def generate_report(self) -> str:
        lines = ["=" * 60, "RAPPORT DE SANTÉ DES SÉLECTEURS", "=" * 60, ""]

        for key, entry in sorted(self.data.items()):
            rate   = entry.get("success_rate", 0)
            status = "✓" if rate >= 80 else "⚠" if rate >= 50 else "✗"

            lines += [
                f"{status} {key}",
                f"   Taux de succès : {rate}%",
                f"   Tentatives     : {entry.get('total_attempts', 0)}",
                f"   Succès         : {entry.get('successful_attempts', 0)}",
                f"   Échecs         : {entry.get('failed_attempts', 0)}",
            ]
            if entry.get("last_failure_reason"):
                lines.append(f"   Dernier échec  : {entry['last_failure_reason']}")
            lines.append("")

        dead = self.detect_dead_selectors()
        if dead:
            lines.append("⚠️  SÉLECTEURS MORTS :")
            lines.extend(f"   - {d}" for d in dead)
        else:
            lines.append("✓ Tous les sélecteurs sont opérationnels.")

        lines.append("=" * 60)
        return "\n".join(lines)


# ──────────────────────────────────────────
# Singleton thread-safe
# ──────────────────────────────────────────

_health_manager: Optional[SelectorHealthManager] = None
_hm_lock = threading.Lock()


def get_health_manager() -> SelectorHealthManager:
    global _health_manager
    if _health_manager is None:
        with _hm_lock:
            if _health_manager is None:
                _health_manager = SelectorHealthManager()
    return _health_manager
