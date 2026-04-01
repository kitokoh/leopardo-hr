"""
automation/selenium_engine.py — Moteur Selenium (FALLBACK UNIQUEMENT)

⚠️ Ce module est conservé uniquement comme fallback de secours.
    Playwright est le moteur principal recommandé.
    
Selenium est obsolète, lent et facilement détectable par Facebook.
À n'utiliser qu'en dernier recours si Playwright échoue.
"""

from typing import Optional, List
import pathlib

try:
    from selenium import webdriver
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
    from selenium.webdriver.chrome.options import Options
    from selenium.common.exceptions import TimeoutException
    SELENIUM_AVAILABLE = True
except ImportError:
    SELENIUM_AVAILABLE = False


class SeleniumEngine:
    """
    Moteur Selenium — FALLBACK UNIQUEMENT.
    
    Interface compatible avec PlaywrightWrapper mais utilisant Selenium.
    Plus lent et plus détectable que Playwright.
    """
    
    def __init__(self, headless: bool = False, **kwargs):
        if not SELENIUM_AVAILABLE:
            raise RuntimeError("Selenium n'est pas installé. pip install selenium")
        
        self.headless = headless
        self.driver = None
        self._wait = None
    
    def start(self):
        """Démarre Chrome avec Selenium."""
        options = Options()
        if self.headless:
            options.add_argument("--headless")
        options.add_argument("--no-sandbox")
        options.add_argument("--disable-dev-shm-usage")
        options.add_argument("--disable-blink-features=AutomationControlled")
        options.add_experimental_option("excludeSwitches", ["enable-automation"])
        options.add_experimental_option("useAutomationExtension", False)
        options.add_argument("--window-size=1280,720")
        
        self.driver = webdriver.Chrome(options=options)
        self.driver.execute_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")
        self._wait = WebDriverWait(self.driver, 30)
        return self
    
    def stop(self):
        """Arrête le navigateur."""
        if self.driver:
            self.driver.quit()
    
    def open_url(self, url: str, timeout: int = 30000) -> bool:
        """Ouvre une URL."""
        try:
            self.driver.set_page_load_timeout(timeout / 1000)
            self.driver.get(url)
            return True
        except Exception as e:
            print(f"[ERROR] open_url failed: {e}")
            return False
    
    def write_post(self, text: str) -> bool:
        """Écrit un texte dans la zone de saisie."""
        try:
            # Attendre et cliquer sur le bouton pour ouvrir la zone
            btn = self._wait.until(EC.element_to_be_clickable(
                (By.CSS_SELECTOR, "[aria-label*='Write something'][role='button']")
            ))
            btn.click()
            
            # Saisir le texte
            field = self._wait.until(EC.presence_of_element_located(
                (By.CSS_SELECTOR, "div[role='textbox'][contenteditable='true']")
            ))
            field.send_keys(text)
            return True
        except Exception as e:
            print(f"[ERROR] write_post failed: {e}")
            return False
    
    def upload_image(self, image_path: str) -> bool:
        """Upload une image."""
        try:
            # Cliquer sur le bouton photo
            show_btn = self._wait.until(EC.element_to_be_clickable(
                (By.CSS_SELECTOR, "[aria-label*='Photo']")
            ))
            show_btn.click()
            
            # Trouver l'input file et uploader
            file_input = self._wait.until(EC.presence_of_element_located(
                (By.CSS_SELECTOR, "input[type='file'][accept*='image']")
            ))
            file_input.send_keys(str(image_path))
            return True
        except Exception as e:
            print(f"[ERROR] upload_image failed: {e}")
            return False
    
    def click_publish(self) -> bool:
        """Clique sur Publier."""
        try:
            btn = self._wait.until(EC.element_to_be_clickable(
                (By.CSS_SELECTOR, "[role='button'][aria-label='Post'], [role='button'][aria-label='Publier']")
            ))
            btn.click()
            return True
        except Exception as e:
            print(f"[ERROR] click_publish failed: {e}")
            return False
    
    def screenshot(self, path: str) -> str:
        """Prend un screenshot."""
        self.driver.save_screenshot(path)
        return path
    
    def get_html(self) -> str:
        """Retourne le HTML de la page."""
        return self.driver.page_source
