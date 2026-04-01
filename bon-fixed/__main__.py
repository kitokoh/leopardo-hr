"""
__main__.py — Point d'entrée du module BON
Tourne en autonome dans son propre venv.
L'app PyQt lance ce module via subprocess et lit ses logs (activity.jsonl).

Commandes :
  python -m bon post --session <nom>          Publier (1 image par groupe)
  python -m bon post-multi --session <nom>    Publier (multi-images)
  python -m bon marketplace --session <nom>   Publier dans Marketplace
  python -m bon save-groups --session <nom> --keyword <mot>
  python -m bon login --session <nom>         Créer une session (login manuel)
  python -m bon list-sessions                 Lister les sessions
  python -m bon verify --session <nom>        Vérifier l'état d'une session
"""
import sys
import argparse
import pathlib

# ── Vérification licence ───────────────────────────────────────────────────────
try:
    from check_license import is_license_valid
    if not is_license_valid():
        print(
            "\n✗ Licence invalide ou expirée.\n"
            "  Vérifiez le fichier python.txt dans le répertoire du projet.\n",
            file=sys.stderr,
        )
        sys.exit(1)
except Exception as _lic_err:
    # En cas d'erreur inattendue dans le module licence, on ne bloque pas le démarrage
    print(f"[WARN] Vérification de licence ignorée : {_lic_err}", file=sys.stderr)

# ── Vérification Playwright installé ──────────────────────────────────────────
try:
    from playwright.sync_api import sync_playwright  # noqa: F401
except ImportError:
    print(
        "\n✗ Playwright n'est pas installé.\n"
        "  Exécutez d'abord : python install.py\n"
        "  Ou manuellement : pip install playwright && playwright install chromium\n",
        file=__import__("sys").stderr,
    )
    __import__("sys").exit(1)
# ──────────────────────────────────────────────────────────────────────────────

from libs.playwright_engine import PlaywrightEngine
from libs.selector_registry import SelectorRegistry
from libs.session_manager import SessionManager
from libs.scraper import Scraper
from libs.config_manager import CONFIG_DIR, load_json
from libs.log_emitter import emit, write_pid, clear_pid
from libs.error_handlers import setup_graceful_shutdown
from libs.timing_humanizer import (
    check_session_limit, check_cooldown, update_session_run_stats
)
from libs.config_validator import validate_session_config, ConfigError


# ──────────────────────────────────────────────
# Parseur d'arguments
# ──────────────────────────────────────────────

def parse_args():
    parser = argparse.ArgumentParser(
        description="BON — Facebook Groups Publisher (autonome)"
    )
    sub = parser.add_subparsers(dest="command")

    # post
    p = sub.add_parser("post", help="Publier dans les groupes (1 image)")
    p.add_argument("--session", required=True, help="Nom de la session/compte")
    p.add_argument("--headless", action="store_true", help="Mode sans fenêtre")

    # post-multi
    p = sub.add_parser("post-multi", help="Publier avec plusieurs images")
    p.add_argument("--session", required=True)
    p.add_argument("--headless", action="store_true")

    # marketplace
    p = sub.add_parser("marketplace", help="Publier dans Facebook Marketplace")
    p.add_argument("--session", required=True)
    p.add_argument("--headless", action="store_true")

    # save-groups
    p = sub.add_parser("save-groups", help="Rechercher et sauvegarder des groupes")
    p.add_argument("--session", required=True)
    p.add_argument("--keyword", required=True, help="Mot-clé de recherche")
    p.add_argument("--headless", action="store_true")

    # login
    p = sub.add_parser("login", help="Créer une session via login manuel")
    p.add_argument("--session", required=True, help="Nom à donner au compte")

    # list-sessions
    sub.add_parser("list-sessions", help="Lister les sessions disponibles")

    # verify
    p = sub.add_parser("verify", help="Vérifier l'état d'une session")
    p.add_argument("--session", required=True)

    return parser.parse_args()


# ──────────────────────────────────────────────
# Helpers partagés
# ──────────────────────────────────────────────

def _load_and_validate_session(session_name: str) -> tuple:
    """
    Charge et valide la config d'une session.
    Returns: (session_manager, session_config)
    Exits avec code 1 si invalide.
    """
    sm = SessionManager()

    if not sm.session_exists(session_name):
        emit("ERROR", "SESSION_NOT_FOUND", session=session_name)
        emit("INFO", "HINT",
             msg=f"Créez la session avec : python -m bon login --session {session_name}")
        sys.exit(1)

    config = sm.get_config(session_name)

    # Avertir si la session semble expirée (storage_state absent)
    storage = config.get("storage_state", "")
    if storage and not pathlib.Path(storage).exists():
        emit("WARN", "SESSION_STATE_MISSING",
             session=session_name,
             hint=f"Relancez : python -m bon login --session {session_name}")

    try:
        warnings = validate_session_config(config, session_name)
        if warnings:
            emit("WARN", "CONFIG_WARNINGS", count=len(warnings))
    except ConfigError as e:
        print(f"\n✗ {e}")
        sys.exit(1)

    return sm, config


def _check_frequency_limits(config: dict, session_name: str) -> None:
    """
    Vérifie les limites de fréquence (cooldown + runs/jour).
    Exits avec code 0 si limite atteinte (normal, pas une erreur).

    CORRECTION : remet run_count_today à 0 si last_run_date != aujourd'hui
    pour éviter un blocage injuste au lendemain.
    """
    from datetime import date
    today = date.today().isoformat()

    # Si le dernier run était un autre jour → le compteur est périmé
    if config.get("last_run_date", "") != today:
        config["run_count_today"] = 0

    # Cooldown entre runs
    last_run = config.get("last_run_ts")
    cooldown_s = int(config.get("cooldown_between_runs_s", 7200))
    if not check_cooldown(last_run, cooldown_s):
        emit("WARN", "COOLDOWN_ACTIVE_EXIT", session=session_name)
        sys.exit(0)

    # Limite journalière
    run_count = int(config.get("run_count_today", 0))
    max_runs  = int(config.get("max_runs_per_day", 3))
    if not check_session_limit(run_count, max_runs):
        emit("WARN", "DAILY_LIMIT_REACHED_EXIT", session=session_name)
        sys.exit(0)


def _build_engine_and_scraper(args, config: dict, session_name: str):
    """Crée et retourne (engine, selectors, scraper)."""
    headless = getattr(args, "headless", False)

    selectors_path = CONFIG_DIR / "selectors.json"
    if not selectors_path.exists():
        selectors_path = pathlib.Path("config") / "selectors.json"

    selectors = SelectorRegistry(selectors_path)
    selectors.update_from_cdn()  # silencieux si CDN non configuré

    engine = PlaywrightEngine(headless=headless)
    scraper = Scraper(engine, selectors, config, session_name)
    return engine, selectors, scraper


# ──────────────────────────────────────────────
# Commandes
# ──────────────────────────────────────────────

def run_post(args, multi_image: bool = False) -> None:
    """Publie dans les groupes (simple ou multi-images)."""
    sm, config = _load_and_validate_session(args.session)
    _check_frequency_limits(config, args.session)

    engine, _, scraper = _build_engine_and_scraper(args, config, args.session)

    def cleanup():
        try:
            scraper.close()
            engine.stop()
        except Exception:
            pass
        clear_pid()

    engine.start()
    write_pid()
    setup_graceful_shutdown(cleanup)

    try:
        with scraper:
            if multi_image:
                stats = scraper.post_in_groups_multi()
            else:
                stats = scraper.post_in_groups()

            emit("SUCCESS", "RUN_COMPLETE", session=args.session, **stats)

            # Mettre à jour les stats de fréquence et sauvegarder
            updated_config = update_session_run_stats(config)
            sm.save_config(args.session, updated_config)

    except KeyboardInterrupt:
        emit("INFO", "INTERRUPTED_BY_USER")
    except Exception as e:
        emit("ERROR", "RUN_FAILED", session=args.session, error=str(e))
        sys.exit(1)
    finally:
        engine.stop()
        clear_pid()


def run_marketplace(args) -> None:
    """Publie une annonce dans Marketplace."""
    sm, config = _load_and_validate_session(args.session)

    if not config.get("marketplace"):
        emit("ERROR", "MARKETPLACE_NOT_CONFIGURED", session=args.session,
             hint="Ajoutez la clé 'marketplace' dans la config session")
        sys.exit(1)

    # CORRECTION : appliquer les mêmes limites de fréquence que run_post
    _check_frequency_limits(config, args.session)

    engine, _, scraper = _build_engine_and_scraper(args, config, args.session)
    engine.start()
    write_pid()

    def cleanup():
        try:
            scraper.close()
            engine.stop()
        except Exception:
            pass
        clear_pid()

    setup_graceful_shutdown(cleanup)

    try:
        with scraper:
            success = scraper.post_in_marketplace()
            if success:
                emit("SUCCESS", "MARKETPLACE_DONE", session=args.session)
                # Mettre à jour les stats de fréquence
                updated_config = update_session_run_stats(config)
                sm.save_config(args.session, updated_config)
            else:
                emit("WARN", "MARKETPLACE_SKIPPED", session=args.session)
    except KeyboardInterrupt:
        emit("INFO", "INTERRUPTED_BY_USER")
    except Exception as e:
        emit("ERROR", "MARKETPLACE_FAILED", error=str(e))
        sys.exit(1)
    finally:
        engine.stop()
        clear_pid()


def run_save_groups(args) -> None:
    """Recherche et sauvegarde des groupes."""
    sm, config = _load_and_validate_session(args.session)
    engine, _, scraper = _build_engine_and_scraper(args, config, args.session)
    engine.start()
    write_pid()

    try:
        with scraper:
            links = scraper.save_groups(args.keyword)
            sm.save_config(args.session, scraper.config)
            print(f"\n✓ {len(links)} groupes sauvegardés pour '{args.session}'")
    except Exception as e:
        emit("ERROR", "SAVE_GROUPS_FAILED", error=str(e))
        sys.exit(1)
    finally:
        engine.stop()
        clear_pid()


def run_login(args) -> None:
    """
    Crée une nouvelle session via login manuel dans le navigateur.
    Délègue à SessionManager.create_session().
    """
    sm = SessionManager()
    emit("INFO", "LOGIN_START", session=args.session)

    engine = PlaywrightEngine(headless=False)
    engine.start()

    try:
        # CORRECTION : utilise la propriété publique engine.browser
        ok = sm.create_session(args.session, engine.browser)
        if ok:
            from libs.config_manager import get_session_state_path
            state_path = get_session_state_path(args.session)
            print(f"\n✓ Session '{args.session}' créée avec succès.")
            print(f"  Fichier session : {state_path}")
        else:
            print(f"\n✗ Login non détecté pour '{args.session}'. Réessayez.")
            sys.exit(1)
    except Exception as e:
        emit("ERROR", "LOGIN_ERROR", error=str(e))
        print(f"\n✗ Erreur : {e}")
        sys.exit(1)
    finally:
        engine.stop()


def run_verify(args) -> None:
    """Vérifie si une session est encore valide."""
    sm = SessionManager()
    if not sm.session_exists(args.session):
        print(f"✗ Session '{args.session}' introuvable.")
        sys.exit(1)

    config = sm.get_config(args.session)
    engine = PlaywrightEngine(headless=True)
    engine.start()

    try:
        context, page = engine.new_context(
            storage_state=config.get("storage_state", "")
        )
        page.goto("https://www.facebook.com/", wait_until="domcontentloaded",
                  timeout=20000)

        if "/login" in page.url:
            print(f"✗ Session '{args.session}' expirée.")
            emit("WARN", "SESSION_EXPIRED", session=args.session)
            context.close()
            sys.exit(1)

        print(f"✓ Session '{args.session}' valide.")
        emit("INFO", "SESSION_VALID", session=args.session)
        context.close()
    except Exception as e:
        print(f"✗ Erreur de vérification : {e}")
        sys.exit(1)
    finally:
        engine.stop()


# ──────────────────────────────────────────────
# Main + mode interactif
# ──────────────────────────────────────────────

def main():
    args = parse_args()

    if args.command is None:
        _interactive_mode()
        return

    dispatch = {
        "post":          lambda: run_post(args, multi_image=False),
        "post-multi":    lambda: run_post(args, multi_image=True),
        "marketplace":   lambda: run_marketplace(args),
        "save-groups":   lambda: run_save_groups(args),
        "login":         lambda: run_login(args),
        "verify":        lambda: run_verify(args),
        "list-sessions": lambda: _list_sessions(),
    }

    fn = dispatch.get(args.command)
    if fn:
        fn()
    else:
        print(f"Commande inconnue : {args.command}")
        sys.exit(1)


def _list_sessions():
    sessions = SessionManager().list_sessions()
    if sessions:
        print("Sessions disponibles :")
        for s in sessions:
            print(f"  • {s}")
    else:
        print("Aucune session configurée.")
        print("Créez-en une : python -m bon login --session <nom>")


def _interactive_mode():
    """Mode interactif — pour lancer manuellement depuis un terminal."""
    print("BON — Facebook Groups Publisher")
    print("=" * 40)

    sessions = SessionManager().list_sessions()
    if not sessions:
        print("Aucune session. Créez-en une :")
        print("  python -m bon login --session <nom>")
        return

    print("Sessions :", ", ".join(sessions))
    session = input("Session à utiliser : ").strip()
    if session not in sessions:
        print("Session introuvable.")
        return

    print("\n1) Publier dans les groupes (1 image)")
    print("2) Publier multi-images")
    print("3) Publier dans Marketplace")
    print("4) Sauvegarder des groupes")
    print("5) Quitter")
    choice = input("Choix : ").strip()

    ns = argparse.Namespace(session=session, headless=False)
    if choice == "1":
        run_post(ns, multi_image=False)
    elif choice == "2":
        run_post(ns, multi_image=True)
    elif choice == "3":
        run_marketplace(ns)
    elif choice == "4":
        keyword = input("Mot-clé : ").strip()
        ns.keyword = keyword
        run_save_groups(ns)


if __name__ == "__main__":
    main()
