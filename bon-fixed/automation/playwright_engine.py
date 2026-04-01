"""
automation/playwright_engine.py — Wrapper Playwright avec interface unifiée

Ce module fournit une interface compatible avec l'ancien SeleniumEngine
mais utilisant Playwright en interne.
"""

from typing import Optional, List
import pathlib


class PlaywrightWrapper:
    """
    Wrapper Playwright avec interface compatible SeleniumEngine.
    Permet une transition progressive depuis Selenium.
    """
    
    def __init__(self, headless: bool = False, **kwargs):
        self.headless = headless
        self.driver = None  # Compatibilité nom Selenium
        self._page = None
        self._context = None
        self._browser = None
        self._playwright = None
    
    def start(self):
        """Démarre le navigateur."""
        from playwright.sync_api import sync_playwright
        self._playwright = sync_playwright().start()
        self._browser = self._playwright.chromium.launch(headless=self.headless)
        self._context = self._browser.new_context(
            viewport={"width": 1280, "height": 720}
        )
        self._page = self._context.new_page()
        self.driver = self._page  # Pour compatibilité
        return self
    
    def stop(self):
        """Arrête le navigateur."""
        if self._context:
            self._context.close()
        if self._browser:
            self._browser.close()
        if self._playwright:
            self._playwright.stop()
    
    def open_url(self, url: str, timeout: int = 30000) -> bool:
        """Ouvre une URL."""
        try:
            self._page.goto(url, wait_until="domcontentloaded", timeout=timeout)
            return True
        except Exception as e:
            print(f"[ERROR] open_url failed: {e}")
            return False
    
    def write_post(self, text: str) -> bool:
        """Écrit un texte dans la zone de saisie."""
        try:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            
            # Ouvrir la zone
            btn = selectors.find(self._page, "display_input")
            btn.click()
            
            # Saisir
            field = selectors.find(self._page, "input")
            field.press_sequentially(text, delay=50)
            return True
        except Exception as e:
            print(f"[ERROR] write_post failed: {e}")
            return False
    
    def upload_image(self, image_path: str) -> bool:
        """Upload une image."""
        try:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            
            show_btn = selectors.find(self._page, "show_image_input")
            show_btn.click()
            
            add_sel = selectors.get_candidates("add_image")[0]
            self._page.set_input_files(add_sel, image_path)
            return True
        except Exception as e:
            print(f"[ERROR] upload_image failed: {e}")
            return False
    
    def click_publish(self) -> bool:
        """Clique sur Publier."""
        try:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            
            btn = selectors.find(self._page, "submit")
            btn.click()
            return True
        except Exception as e:
            print(f"[ERROR] click_publish failed: {e}")
            return False
    
    def screenshot(self, path: str) -> str:
        """Prend un screenshot."""
        self._page.screenshot(path=path)
        return path
    
    def get_html(self) -> str:
        """Retourne le HTML de la page."""
        return self._page.content()
