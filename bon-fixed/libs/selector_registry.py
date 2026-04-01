"""
selector_registry.py — Registre de sélecteurs avec fallback automatique et mise à jour CDN
Stratégie 4 niveaux : aria-label → data-testid → XPath sémantique → CSS obfusqué (dernier recours)
"""
import json
import pathlib
import requests
from typing import Optional

try:
    from libs.log_emitter import emit
    from libs.config_manager import CONFIG_DIR, load_json, save_json
except ImportError:
    from log_emitter import emit
    from config_manager import CONFIG_DIR, load_json, save_json

# URL CDN pour mise à jour automatique des sélecteurs.
# Configurable via variable d'environnement BON_SELECTORS_CDN_URL
# ou en éditant cette ligne directement.
import os as _os
SELECTORS_CDN_URL = _os.environ.get(
    "BON_SELECTORS_CDN_URL",
    ""  # Laisser vide désactive le CDN (mode local uniquement)
)
CDN_TIMEOUT = 5  # secondes
# Âge max des sélecteurs avant alerte (jours). 0 = désactive l'alerte.
SELECTORS_MAX_AGE_DAYS = int(_os.environ.get("BON_SELECTORS_MAX_AGE_DAYS", "30"))


class SelectorNotFound(Exception):
    """Levée quand aucun sélecteur ne correspond pour une clé donnée."""
    def __init__(self, key: str, tried: list):
        self.key = key
        self.tried = tried
        super().__init__(f"Sélecteur introuvable pour '{key}'. Essayés: {tried}")


class SelectorRegistry:
    """
    Registre de sélecteurs Playwright avec fallback automatique.

    Format selectors.json attendu :
    {
        "version": "2026-03",
        "selectors": {
            "post_button": {
                "selectors": [
                    "[role='button'][aria-label*='Post']",
                    "[data-testid='react-composer-post-button']",
                    "//div[contains(@aria-label,'Post')]"
                ]
            }
        }
    }
    """

    def __init__(self, selectors_path: Optional[pathlib.Path] = None):
        if selectors_path is None:
            selectors_path = CONFIG_DIR / "selectors.json"
        self.selectors_path = pathlib.Path(selectors_path)
        self._data: dict = {}
        self._load()

    def _load(self) -> None:
        """Charge le fichier de sélecteurs depuis le disque."""
        raw = load_json(self.selectors_path)
        if not raw:
            # Fallback sur l'ancien format plat (compatibilité)
            self._data = {"version": "legacy", "selectors": {}}
            emit("WARN", "SELECTORS_EMPTY", path=str(self.selectors_path))
        elif "selectors" in raw:
            # Nouveau format v2 avec version + liste de fallbacks
            self._data = raw
        else:
            # Ancien format plat : convertir à la volée
            self._data = {
                "version": "legacy",
                "selectors": {
                    key: {"selectors": [value]}
                    for key, value in raw.items()
                    if isinstance(value, str)
                }
            }
        version = self._data.get("version", "?")
        emit("INFO", "SELECTORS_LOADED",
             version=version,
             count=len(self._data.get("selectors", {})))
        # Alerte si les sélecteurs sont trop anciens
        self._check_selectors_age(version)

    def _check_selectors_age(self, version: str) -> None:
        """Émet un avertissement si les sélecteurs dépassent SELECTORS_MAX_AGE_DAYS."""
        if SELECTORS_MAX_AGE_DAYS <= 0 or version in ("legacy", "unknown", "?"):
            return
        try:
            import datetime as _dt
            # version format : "YYYY-MM"
            parts = version.split("-")
            if len(parts) >= 2:
                selector_date = _dt.date(int(parts[0]), int(parts[1]), 1)
                age_days = (_dt.date.today() - selector_date).days
                if age_days > SELECTORS_MAX_AGE_DAYS:
                    emit("WARN", "SELECTORS_STALE",
                         version=version, age_days=age_days,
                         max_days=SELECTORS_MAX_AGE_DAYS,
                         hint="Configurez BON_SELECTORS_CDN_URL ou mettez à jour config/selectors.json")
        except Exception:
            pass

    def update_from_cdn(self) -> bool:
        """
        Tente de récupérer une version plus récente des sélecteurs depuis le CDN.
        Retourne True si mise à jour effectuée, False sinon.
        """
        try:
            resp = requests.get(SELECTORS_CDN_URL, timeout=CDN_TIMEOUT)
            resp.raise_for_status()
            remote = resp.json()
            local_version = self._data.get("version", "0000-00")
            remote_version = remote.get("version", "0000-00")
            if remote_version > local_version:
                save_json(self.selectors_path, remote)
                self._data = remote
                emit("SUCCESS", "SELECTORS_UPDATED",
                     old=local_version, new=remote_version)
                return True
            else:
                emit("INFO", "SELECTORS_UP_TO_DATE", version=local_version)
                return False
        except requests.RequestException:
            emit("INFO", "SELECTORS_LOCAL_FALLBACK",
                 reason="CDN inaccessible ou timeout")
            return False
        except Exception as e:
            emit("WARN", "SELECTORS_CDN_ERROR", error=str(e))
            return False

    def get_candidates(self, key: str) -> list[str]:
        """Retourne la liste ordonnée des sélecteurs pour une clé."""
        sel_data = self._data.get("selectors", {}).get(key, {})
        if isinstance(sel_data, dict):
            return sel_data.get("selectors", [])
        elif isinstance(sel_data, list):
            return sel_data
        elif isinstance(sel_data, str):
            return [sel_data]
        return []

    def find(self, page, key: str, timeout: int = 5000):
        """
        Trouve le premier sélecteur fonctionnel pour une clé donnée.
        Essaie les candidats dans l'ordre (du plus stable au moins stable).

        Args:
            page: objet Page de Playwright
            key: clé du sélecteur (ex: "post_button")
            timeout: timeout en ms pour chaque tentative

        Returns:
            L'élément Playwright trouvé

        Raises:
            SelectorNotFound si aucun sélecteur ne fonctionne
        """
        candidates = self.get_candidates(key)
        if not candidates:
            raise SelectorNotFound(key, [])

        tried = []
        for idx, selector in enumerate(candidates):
            try:
                # Playwright supporte CSS, XPath (//...) et aria
                el = page.wait_for_selector(selector, timeout=timeout)
                if el:
                    if idx > 0:
                        emit("WARN", "SELECTOR_FALLBACK",
                             key=key, used_index=idx, selector=selector[:60])
                    return el
            except Exception:
                tried.append(selector[:60])
                continue

        # Aucun sélecteur n'a fonctionné : log + screenshot auto
        emit("ERROR", "SELECTOR_NOT_FOUND", key=key, tried=tried)
        try:
            screenshot_path = str(
                pathlib.Path("errors") / f"selector_fail_{key}.png"
            )
            pathlib.Path("errors").mkdir(exist_ok=True)
            page.screenshot(path=screenshot_path)
            emit("INFO", "SCREENSHOT_SAVED", path=screenshot_path)
        except Exception:
            pass
        raise SelectorNotFound(key, tried)

    def find_all(self, page, key: str, timeout: int = 3000) -> list:
        """
        Trouve tous les éléments correspondant à la première sélecteur fonctionnel.
        Utile pour récupérer plusieurs liens (groupes, etc.)
        """
        candidates = self.get_candidates(key)
        for selector in candidates:
            try:
                page.wait_for_selector(selector, timeout=timeout)
                elements = page.query_selector_all(selector)
                if elements:
                    return elements
            except Exception:
                continue
        return []

    @property
    def version(self) -> str:
        return self._data.get("version", "unknown")
