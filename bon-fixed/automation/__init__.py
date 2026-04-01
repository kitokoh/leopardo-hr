"""
automation/__init__.py — Package automation moderne

CORRECTIONS v3:
  - Selenium importé de façon optionnelle (lazy) — plus de crash si absent
  - Playwright est le seul moteur requis
  - SeleniumEngine n'est exposé que si selenium est installé
"""

from automation.engine import AutomationEngine
from automation.playwright_engine import PlaywrightWrapper
from automation.selector_tester import test_selector
from automation.selector_health import SelectorHealthManager, get_health_manager
from automation.anti_block import AntiBlockManager, get_anti_block_manager

__all__ = [
    "AutomationEngine",
    "PlaywrightWrapper",
    "test_selector",
    "SelectorHealthManager",
    "get_health_manager",
    "AntiBlockManager",
    "get_anti_block_manager",
]

# Selenium optionnel — exposé uniquement s'il est installé
try:
    from automation.selenium_engine import SeleniumEngine
    __all__.append("SeleniumEngine")
except ImportError:
    pass  # Selenium non installé — fonctionnement normal avec Playwright

__version__ = "5.1.0"
__author__ = "BON Team"
