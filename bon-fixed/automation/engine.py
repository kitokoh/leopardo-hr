"""
automation/engine.py — Interface unifiée pour moteurs d'automatisation

Cette couche abstraite permet de changer de moteur (Playwright, Selenium)
sans modifier le code métier du projet.

Usage:
    from automation.engine import AutomationEngine
    
    engine = AutomationEngine(use_playwright=True)
    engine.open_group("https://facebook.com/groups/123")
    engine.write_post("Mon texte")
    engine.upload_image("/path/to/image.jpg")
    engine.click_publish()
    engine.close()
"""

from typing import Optional, List, Any
import pathlib


class AutomationEngine:
    """
    Interface unique pour toutes les actions d'automatisation Facebook.
    
    Délègue les opérations au moteur sélectionné (Playwright par défaut).
    """
    
    def __init__(self, use_playwright: bool = True, headless: bool = False, **kwargs):
        """
        Initialise le moteur d'automatisation.
        
        Args:
            use_playwright: True pour Playwright, False pour Selenium (fallback)
            headless: Mode sans fenêtre
            **kwargs: Arguments spécifiques au moteur
        """
        self.use_playwright = use_playwright
        self.headless = headless
        self._engine = None
        self._page = None
        self._context = None
        
        if use_playwright:
            self._init_playwright(headless, **kwargs)
        else:
            self._init_selenium(headless, **kwargs)
    
    def _init_playwright(self, headless: bool, **kwargs):
        """Initialise le moteur Playwright."""
        try:
            from libs.playwright_engine import PlaywrightEngine
            self._engine = PlaywrightEngine(headless=headless, **kwargs)
            self._engine.start()
        except ImportError as e:
            print(f"[ERROR] Playwright non disponible: {e}")
            print("[FALLBACK] Tentative avec Selenium...")
            self.use_playwright = False
            self._init_selenium(headless, **kwargs)
    
    def _init_selenium(self, headless: bool, **kwargs):
        """Initialise le moteur Selenium (fallback)."""
        try:
            from automation.selenium_engine import SeleniumEngine
            self._engine = SeleniumEngine(headless=headless, **kwargs)
            self._engine.start()
        except ImportError as e:
            raise RuntimeError(f"Aucun moteur disponible. Playwright: {e}")
    
    def open_group(self, url: str) -> bool:
        """Ouvre la page d'un groupe Facebook."""
        if self.use_playwright:
            return self._engine.navigate(self._get_page(), url)
        return self._engine.open_url(url)
    
    def write_post(self, text: str) -> bool:
        """Écrit le texte d'un post."""
        if self.use_playwright:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            try:
                # Ouvrir la zone de saisie
                btn = selectors.find(self._get_page(), "display_input")
                btn.click()
                
                # Saisir le texte
                field = selectors.find(self._get_page(), "input")
                field.press_sequentially(text)
                return True
            except Exception as e:
                print(f"[ERROR] write_post failed: {e}")
                return False
        return self._engine.write_post(text)
    
    def upload_image(self, image_path: str) -> bool:
        """Upload une image dans le post."""
        if self.use_playwright:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            page = self._get_page()
            
            try:
                # Ouvrir le sélecteur d'images
                show_btn = selectors.find(page, "show_image_input")
                show_btn.click()
                
                # Upload le fichier
                add_sel = selectors.get_candidates("add_image")[0]
                page.set_input_files(add_sel, image_path)
                return True
            except Exception as e:
                print(f"[ERROR] upload_image failed: {e}")
                return False
        return self._engine.upload_image(image_path)
    
    def click_publish(self) -> bool:
        """Clique sur le bouton de publication."""
        if self.use_playwright:
            from libs.selector_registry import SelectorRegistry
            selectors = SelectorRegistry()
            try:
                btn = selectors.find(self._get_page(), "submit")
                btn.click()
                return True
            except Exception as e:
                print(f"[ERROR] click_publish failed: {e}")
                return False
        return self._engine.click_publish()
    
    def _get_page(self):
        """Retourne l'objet page courant."""
        if self.use_playwright:
            if self._page is None:
                self._context, self._page = self._engine.new_context()
            return self._page
        return self._engine.driver
    
    def close(self):
        """Ferme proprement le moteur."""
        if self._engine:
            self._engine.stop()
    
    def __enter__(self):
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        self.close()
