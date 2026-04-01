"""
error_handlers.py — Décorateur retry exponentiel + détection des états bloquants Facebook

CORRECTIONS v3:
  - Détection CAPTCHA corrigée : page.locator(...).count() > 0
    (frame_locator() retourne toujours un objet truthy, jamais None)
"""
import functools
import time
import signal
import sys
from typing import Callable, Type, Tuple

try:
    from libs.log_emitter import emit
except ImportError:
    from log_emitter import emit


# ──────────────────────────────────────────────
# Décorateur retry exponentiel
# ──────────────────────────────────────────────

def retry(
    max_attempts: int = 3,
    delay: float = 2.0,
    backoff: float = 2.0,
    exceptions: Tuple[Type[Exception], ...] = (Exception,),
):
    """
    Décorateur retry avec backoff exponentiel.

    Usage :
        @retry(max_attempts=3, delay=3)
        def post_in_group(...):
            ...
    """
    def decorator(func: Callable) -> Callable:
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            wait = delay
            last_error = None
            for attempt in range(1, max_attempts + 1):
                try:
                    return func(*args, **kwargs)
                except exceptions as e:
                    last_error = e
                    if attempt == max_attempts:
                        emit("ERROR", "MAX_RETRIES_REACHED",
                             func=func.__name__,
                             attempts=max_attempts,
                             error=str(e))
                        raise
                    emit("WARN", "RETRY",
                         func=func.__name__,
                         attempt=attempt,
                         max=max_attempts,
                         wait_s=round(wait, 1),
                         error=str(e)[:80])
                    time.sleep(wait)
                    wait *= backoff
            raise last_error  # ne devrait pas être atteint
        return wrapper
    return decorator


# ──────────────────────────────────────────────
# Exceptions métier
# ──────────────────────────────────────────────

class FacebookBlockedError(Exception):
    """Compte temporairement bloqué ou checkpoint détecté."""
    pass

class SessionExpiredError(Exception):
    """Session Facebook expirée — login requis."""
    pass

class GroupUnavailableError(Exception):
    """Groupe inaccessible (404, privé, supprimé)."""
    pass

class RateLimitError(Exception):
    """Rate limiting Facebook détecté."""
    pass

class CaptchaDetectedError(Exception):
    """CAPTCHA détecté — intervention manuelle requise."""
    pass


# ──────────────────────────────────────────────
# Détection des états bloquants
# ──────────────────────────────────────────────

def check_page_state(page) -> None:
    """
    Analyse l'état courant de la page Playwright et lève une exception
    appropriée si un état bloquant est détecté.

    À appeler avant chaque action importante.
    """
    url = page.url

    # 1. Session expirée
    if "/login" in url or "login.php" in url:
        emit("ERROR", "SESSION_EXPIRED", url=url)
        raise SessionExpiredError(f"Session expirée, URL: {url}")

    # 2. Checkpoint
    if "/checkpoint" in url:
        emit("ERROR", "ACCOUNT_CHECKPOINT", url=url)
        raise FacebookBlockedError(f"Checkpoint Facebook, URL: {url}")

    # 3. Messages de blocage temporaire dans le DOM
    try:
        content = page.content()
        block_phrases = [
            "You're Temporarily Blocked",
            "Vous êtes temporairement bloqué",
            "Geçici olarak engellendi",
            "محظور مؤقتًا",
            "temporarily restricted",
        ]
        if any(phrase.lower() in content.lower() for phrase in block_phrases):
            emit("ERROR", "ACCOUNT_RATE_LIMITED", url=url)
            raise RateLimitError("Compte temporairement bloqué par Facebook")
    except (FacebookBlockedError, RateLimitError):
        raise
    except Exception:
        pass

    # 4. CAPTCHA — CORRECTION : frame_locator() retourne toujours un objet Locator
    #    truthy → utiliser page.locator().count() > 0 pour tester la présence réelle.
    try:
        captcha_count = page.locator("iframe[src*='recaptcha']").count()
        if captcha_count > 0:
            emit("WARN", "CAPTCHA_DETECTED", url=url)
            raise CaptchaDetectedError("reCAPTCHA détecté sur la page")
    except (CaptchaDetectedError,):
        raise
    except Exception:
        pass


def check_group_accessible(page) -> bool:
    """
    Vérifie si le groupe est accessible (pas de 404, pas de page d'erreur).
    Retourne True si accessible, False sinon.
    """
    url = page.url
    try:
        content = page.content()
        unavailable_phrases = [
            "This content isn't available",
            "Ce contenu n'est pas disponible",
            "Bu içerik mevcut değil",
            "هذا المحتوى غير متاح",
        ]
        if any(phrase.lower() in content.lower() for phrase in unavailable_phrases):
            emit("WARN", "GROUP_UNAVAILABLE", url=url)
            return False
        return True
    except Exception as e:
        emit("WARN", "GROUP_CHECK_ERROR", url=url, error=str(e))
        return False


def setup_graceful_shutdown(cleanup_fn: Callable) -> None:
    """
    Installe un handler SIGTERM pour arrêt propre.
    PyQt peut envoyer SIGTERM pour stopper le module après le groupe en cours.
    """
    def _handler(signum, frame):
        emit("INFO", "SIGTERM_RECEIVED")
        try:
            cleanup_fn()
        except Exception as e:
            emit("WARN", "CLEANUP_ERROR", error=str(e))
        sys.exit(0)

    if sys.platform != "win32":
        signal.signal(signal.SIGTERM, _handler)
