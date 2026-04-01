"""
automation/selector_tester.py — Outil de test de sélecteurs

Permet de tester rapidement un sélecteur sur une page Facebook donnée.
Affiche immédiatement si l'élément existe, avec capture et DOM.

Usage:
    python -m automation.selector_tester --url "https://facebook.com" --selector "[role='button']"
    
Ou via interface interactive:
    python -m automation.selector_tester
"""

import argparse
import json
import pathlib
from datetime import datetime


def test_selector(url: str, selector: str, headless: bool = False, timeout: int = 10000):
    """
    Teste un sélecteur sur une URL donnée.
    
    Args:
        url: URL Facebook à tester
        selector: Sélecteur CSS/XPath à tester
        headless: Mode sans fenêtre
        timeout: Timeout en ms
    
    Returns:
        dict avec résultats du test
    """
    from playwright.sync_api import sync_playwright
    
    results = {
        "url": url,
        "selector": selector,
        "timestamp": datetime.now().isoformat(),
        "found": False,
        "element_info": None,
        "screenshot": None,
        "html_snippet": None,
        "error": None
    }
    
    try:
        playwright = sync_playwright().start()
        browser = playwright.chromium.launch(headless=headless)
        context = browser.new_context(viewport={"width": 1280, "height": 720})
        page = context.new_page()
        
        # Navigation
        print(f"[TEST] Navigation vers: {url[:80]}...")
        page.goto(url, wait_until="domcontentloaded", timeout=30000)
        
        # Attendre un peu que la page charge
        page.wait_for_timeout(3000)
        
        # Prendre screenshot de la page
        screenshots_dir = pathlib.Path("logs/screenshots")
        screenshots_dir.mkdir(parents=True, exist_ok=True)
        screenshot_path = screenshots_dir / f"test_{datetime.now().strftime('%Y%m%d_%H%M%S')}.png"
        page.screenshot(path=str(screenshot_path))
        results["screenshot"] = str(screenshot_path)
        print(f"[TEST] Screenshot sauvegardé: {screenshot_path}")
        
        # Tester le sélecteur
        print(f"[TEST] Recherche du sélecteur: {selector}")
        try:
            element = page.wait_for_selector(selector, timeout=timeout)
            if element:
                results["found"] = True
                
                # Récupérer infos élément
                tag = element.evaluate("el => el.tagName")
                text = element.evaluate("el => el.innerText[:200]")
                aria_label = element.evaluate("el => el.getAttribute('aria-label')")
                role = element.evaluate("el => el.getAttribute('role')")
                
                results["element_info"] = {
                    "tag": tag,
                    "text_preview": text,
                    "aria_label": aria_label,
                    "role": role
                }
                
                print(f"[SUCCESS] Élément trouvé!")
                print(f"  Tag: {tag}")
                print(f"  Texte: {text[:100]}...")
                print(f"  Aria-label: {aria_label}")
                print(f"  Role: {role}")
                
                # Sauvegarder snippet HTML
                html_dir = pathlib.Path("logs/html")
                html_dir.mkdir(parents=True, exist_ok=True)
                html_path = html_dir / f"element_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
                outer_html = element.evaluate("el => el.outerHTML")
                html_path.write_text(outer_html, encoding="utf-8")
                results["html_snippet"] = str(html_path)
                print(f"[TEST] Snippet HTML sauvegardé: {html_path}")
                
                # Essayer de cliquer (optionnel)
                try:
                    element.click(timeout=3000)
                    print("[TEST] Clic réussi sur l'élément!")
                    results["clickable"] = True
                except Exception as e:
                    print(f"[TEST] Élément non clickable: {e}")
                    results["clickable"] = False
            else:
                print("[FAIL] Sélecteur trouvé mais élément null")
                results["error"] = "Element is null"
                
        except Exception as e:
            print(f"[FAIL] Sélecteur introuvable: {e}")
            results["error"] = str(e)
            
            # Sauvegarder HTML complet pour débogage
            html_dir = pathlib.Path("logs/html")
            html_dir.mkdir(parents=True, exist_ok=True)
            html_path = html_dir / f"page_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
            page.content().write_text(html_path.read_text() if html_path.exists() else page.content(), encoding="utf-8")
            print(f"[TEST] HTML complet sauvegardé: {html_path}")
        
        browser.close()
        playwright.stop()
        
    except Exception as e:
        results["error"] = str(e)
        print(f"[ERROR] Échec du test: {e}")
    
    return results


def interactive_mode():
    """Mode interactif avec prompts."""
    print("=" * 60)
    print("SELECTOR TESTER — Testeur de sélecteurs Facebook")
    print("=" * 60)
    
    url = input("\nURL Facebook à tester: ").strip()
    if not url:
        url = "https://www.facebook.com/"
    
    while True:
        print("\n--- Nouveau test ---")
        selector = input("Sélecteur à tester (ou 'quit' pour quitter): ").strip()
        
        if selector.lower() in ("quit", "exit", "q"):
            break
        
        if not selector:
            print("[WARN] Sélecteur vide, réessayez.")
            continue
        
        headless_input = input("Mode headless? (y/n, défaut n): ").strip().lower()
        headless = headless_input == "y"
        
        timeout_input = input("Timeout en ms (défaut 10000): ").strip()
        timeout = int(timeout_input) if timeout_input.isdigit() else 10000
        
        results = test_selector(url, selector, headless, timeout)
        
        print("\n" + "=" * 60)
        print("RÉSULTATS:")
        print(json.dumps(results, indent=2, ensure_ascii=False))
        print("=" * 60)


def main():
    parser = argparse.ArgumentParser(
        description="Testeur de sélecteurs Facebook"
    )
    parser.add_argument("--url", type=str, help="URL Facebook à tester")
    parser.add_argument("--selector", type=str, help="Sélecteur CSS/XPath à tester")
    parser.add_argument("--headless", action="store_true", help="Mode sans fenêtre")
    parser.add_argument("--timeout", type=int, default=10000, help="Timeout en ms")
    parser.add_argument("--json", action="store_true", help="Sortie JSON uniquement")
    
    args = parser.parse_args()
    
    if args.url and args.selector:
        # Mode commande
        results = test_selector(args.url, args.selector, args.headless, args.timeout)
        if args.json:
            print(json.dumps(results, indent=2, ensure_ascii=False))
        else:
            print(f"\nRésultats: {json.dumps(results, indent=2, ensure_ascii=False)}")
    else:
        # Mode interactif
        interactive_mode()


if __name__ == "__main__":
    main()
