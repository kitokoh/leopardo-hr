"""
timing_humanizer.py — Délais humains, frappe réaliste, limites de fréquence
Simule un comportement humain normal pour éviter la détection bot.
"""
import random
import time
from datetime import datetime, date
from typing import Optional

try:
    from libs.log_emitter import emit
except ImportError:
    from log_emitter import emit

MAX_GROUPS_PER_SESSION   = 15
DELAY_BETWEEN_GROUPS     = (45, 120)
MAX_RUNS_PER_DAY         = 3
COOLDOWN_BETWEEN_RUNS_S  = 7200


def human_delay(base: float = 2.0, variance: float = 0.5) -> None:
    """Pause gaussienne centrée sur base secondes."""
    delay = random.gauss(base, variance)
    time.sleep(max(0.3, delay))


def human_delay_between_groups(min_s: float = 45, max_s: float = 120) -> None:
    """Délai humain entre deux groupes consécutifs."""
    delay = random.uniform(min_s, max_s)
    emit("INFO", "INTER_GROUP_WAIT", seconds=round(delay, 1))
    time.sleep(delay)


def post_publication_wait() -> None:
    """Attente post-publication (15–25s) pour laisser Facebook traiter."""
    time.sleep(random.uniform(15, 25))


def jitter(base_ms: int = 500) -> float:
    """Délai aléatoire en secondes autour de base_ms ms."""
    return random.uniform(base_ms * 0.7, base_ms * 1.3) / 1000


def human_type(page, selector: str, text: str) -> None:
    """
    Frappe un texte via press_sequentially() — API correcte Playwright.
    Timing variable 40–160ms par caractère.
    """
    el = page.locator(selector).first
    el.click()
    time.sleep(random.uniform(0.3, 0.8))
    el.press_sequentially(text, delay=random.randint(40, 160))
    time.sleep(random.uniform(0.2, 0.5))


def random_scroll(page, min_scrolls: int = 1, max_scrolls: int = 4) -> None:
    """Scroll souris aléatoire — plus réaliste que keyboard.press('End')."""
    for _ in range(random.randint(min_scrolls, max_scrolls)):
        page.mouse.wheel(0, random.randint(200, 700))
        time.sleep(random.uniform(0.4, 1.2))


def human_scroll_to_bottom(page, stable_count: int = 3) -> None:
    """Scroll jusqu'à stabilisation de la hauteur de page."""
    prev_height, stable = 0, 0
    while stable < stable_count:
        random_scroll(page, 2, 5)
        try:
            new_height = page.evaluate("document.body.scrollHeight")
        except Exception:
            new_height = prev_height + 1
        stable = stable + 1 if new_height == prev_height else 0
        prev_height = new_height


def check_session_limit(run_count_today: int,
                        max_runs_per_day: int = MAX_RUNS_PER_DAY) -> bool:
    """
    Vérifie si la limite journalière de runs est atteinte.

    Args:
        run_count_today: nombre de runs déjà effectués aujourd'hui (depuis config)
        max_runs_per_day: limite configurable par session

    Returns:
        True = on peut lancer, False = limite atteinte
    """
    if run_count_today >= max_runs_per_day:
        emit("WARN", "SESSION_LIMIT_REACHED",
             today_runs=run_count_today, max=max_runs_per_day)
        return False
    return True


def check_cooldown(last_run_ts: Optional[str],
                   cooldown_s: int = COOLDOWN_BETWEEN_RUNS_S) -> bool:
    """
    Vérifie le cooldown minimum entre deux runs.

    Args:
        last_run_ts: timestamp ISO du dernier run (depuis config session)
        cooldown_s:  cooldown minimum en secondes

    Returns:
        True = cooldown OK, False = trop tôt
    """
    if not last_run_ts:
        return True
    try:
        last = datetime.fromisoformat(last_run_ts)
        elapsed = (datetime.now() - last).total_seconds()
        if elapsed < cooldown_s:
            remaining = cooldown_s - elapsed
            emit("WARN", "COOLDOWN_ACTIVE",
                 remaining_minutes=round(remaining / 60, 1))
            return False
        return True
    except (ValueError, TypeError):
        return True


def update_session_run_stats(session_config: dict) -> dict:
    """
    Met à jour last_run_ts, run_count_today et last_run_date dans la config.
    Appeler après un run réussi, avant save_config().

    Returns:
        Le dict modifié en place
    """
    now   = datetime.now()
    today = date.today().isoformat()

    if session_config.get("last_run_date") == today:
        session_config["run_count_today"] = session_config.get("run_count_today", 0) + 1
    else:
        session_config["run_count_today"] = 1

    session_config["last_run_ts"]   = now.isoformat(timespec="seconds")
    session_config["last_run_date"] = today
    return session_config
