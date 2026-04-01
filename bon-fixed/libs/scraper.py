"""
scraper.py — Logique métier Facebook (publication groupes, marketplace, commentaires)
Composition avec PlaywrightEngine — aucun héritage, cross-platform, sans Selenium.
Intégré avec BONDatabase, SelectorHealthManager et AntiBlockManager.

CORRECTIONS v4 :
  - AntiBlockManager intégré dans le flux de publication (ignoré en v3)
  - Déduplication texte consécutif via _pick_post(exclude_text=...)
  - Déduplication image via anti_block.can_use_image()
  - Vérification limite horaire avant et pendant la boucle
  - run_marketplace applique les mêmes limites de fréquence
  - _add_comment_after_post : fallback sur DEFAULT_COMMENTS si liste vide
  - Toutes les corrections v3 conservées
"""
import random
import time
import pathlib
from typing import Optional, List

from libs.playwright_engine import PlaywrightEngine
from libs.selector_registry import SelectorRegistry, SelectorNotFound
from libs.timing_humanizer import (
    human_delay, human_delay_between_groups,
    post_publication_wait, random_scroll, human_scroll_to_bottom
)
from libs.error_handlers import (
    retry, check_page_state, check_group_accessible,
    SessionExpiredError, FacebookBlockedError,
    RateLimitError, CaptchaDetectedError
)
from libs.log_emitter import emit
from libs.config_manager import resolve_media_path
from libs.database import get_database, BONDatabase
from automation.selector_health import get_health_manager, SelectorHealthManager
from automation.anti_block import get_anti_block_manager, AntiBlockManager

DEFAULT_COMMENTS = [
    "Super post !",
    "Merci pour le partage !",
    "Très intéressant !",
    "Top !",
    "J'aime beaucoup ce contenu.",
    "Excellent partage !",
    "Merci !",
]


class Scraper:
    """
    Publie des posts dans des groupes Facebook et gère les interactions.
    Utilise composition avec PlaywrightEngine (pas d'héritage).
    """

    def __init__(
        self,
        engine: PlaywrightEngine,
        selectors: SelectorRegistry,
        session_config: dict,
        session_name: str,
    ):
        self.engine         = engine
        self.selectors      = selectors
        self.config         = session_config
        self.session_name   = session_name
        self._context       = None
        self._page          = None
        self.db             = get_database()
        self.health_manager = get_health_manager()
        self.anti_block     = get_anti_block_manager()
        self.db.ensure_account_exists(self.session_name)

    # ──────────────────────────────────────────
    # Cycle de vie
    # ──────────────────────────────────────────

    def open(self) -> None:
        """Ouvre le contexte navigateur avec la session du compte."""
        account_status = self.db.get_account_status(self.session_name)
        if account_status == "temporarily_blocked":
            block_info = self.db.get_account_block_info(self.session_name)
            if block_info and not block_info.get("can_resume", False):
                raise FacebookBlockedError(
                    f"Compte {self.session_name} bloqué jusqu'à {block_info.get('until')}"
                )
        elif account_status == "session_expired":
            emit("WARN", "SESSION_EXPIRED_DETECTED", compte=self.session_name)

        storage_state = self.config.get("storage_state", "")
        self._context, self._page = self.engine.new_context(
            storage_state=storage_state
        )
        self.db.update_account_status(self.session_name, "healthy")
        emit("INFO", "SESSION_START", compte=self.session_name)

    def close(self) -> None:
        """Ferme le contexte navigateur proprement."""
        try:
            if self._context:
                self._context.close()
                self._context = None
                self._page    = None
            emit("INFO", "SESSION_END", compte=self.session_name)
        except Exception as e:
            emit("WARN", "SESSION_CLOSE_ERROR", error=str(e))

    def __enter__(self):
        self.open()
        return self

    def __exit__(self, *_):
        self.close()

    # ──────────────────────────────────────────
    # Publication groupes
    # ──────────────────────────────────────────

    def post_in_groups(self) -> dict:
        return self._run_groups_loop(multi_image=False)

    def post_in_groups_multi(self) -> dict:
        return self._run_groups_loop(multi_image=True)

    def _run_groups_loop(self, multi_image: bool = False) -> dict:
        """Boucle commune pour post_in_groups et post_in_groups_multi."""
        groups     = self.config.get("groups", [])
        posts      = self.config.get("posts", [])
        max_groups = int(self.config.get("max_groups_per_run", 10))
        delay_cfg  = self.config.get("delay_between_groups", [60, 120])

        if not groups:
            emit("WARN", "NO_GROUPS_CONFIGURED", compte=self.session_name)
            return {"success": 0, "skipped": 0, "errors": 0}
        if not posts:
            emit("WARN", "NO_POSTS_CONFIGURED", compte=self.session_name)
            return {"success": 0, "skipped": 0, "errors": 0}

        groups_to_process = groups[:max_groups]
        if len(groups) > max_groups:
            emit("INFO", "GROUPS_LIMITED", total=len(groups), processing=max_groups)

        # Vérification limite horaire anti-blocage avant de démarrer
        if not self.anti_block.can_post_now():
            emit("WARN", "ANTI_BLOCK_HOURLY_LIMIT",
                 compte=self.session_name,
                 reason="Limite horaire de groupes atteinte")
            return {"success": 0, "skipped": len(groups_to_process), "errors": 0}

        stats = {"success": 0, "skipped": 0, "errors": 0}
        last_post_text = None  # pour déduplication texte consécutif

        for idx, group_url in enumerate(groups_to_process, 1):
            # Déduplication texte : éviter le même texte 2 fois de suite
            post      = self._pick_post(posts, exclude_text=last_post_text)
            post_text = post.get("text", "")

            if multi_image:
                images = self._resolve_images_list(
                    post.get("images", []) or ([post.get("image")] if post.get("image") else [])
                )
            else:
                single = post.get("image") or (post.get("images", [""])[0] if post.get("images") else "")
                images = self._resolve_images_list([single]) if single else []

            # Déduplication image via AntiBlockManager (max 2 usages par image)
            images_filtered = [img for img in images if self.anti_block.can_use_image(img)]
            if len(images_filtered) < len(images):
                emit("WARN", "ANTI_BLOCK_IMAGE_OVERUSED",
                     compte=self.session_name, group=idx,
                     dropped=len(images) - len(images_filtered))
            images = images_filtered

            emit("INFO", "GROUP_START",
                 compte=self.session_name, group=idx,
                 total=len(groups_to_process), url=group_url, images=len(images))

            try:
                # Vérifier cooldown DB
                can_post, reason = self.db.can_account_post(self.session_name)
                if not can_post:
                    emit("WARN", "ACCOUNT_COOLDOWN",
                         compte=self.session_name, reason=reason)
                    stats["skipped"] += 1
                    continue

                # Re-vérifier limite horaire en cours de boucle
                if not self.anti_block.can_post_now():
                    emit("WARN", "ANTI_BLOCK_HOURLY_LIMIT_MID_RUN",
                         compte=self.session_name, remaining_groups=len(groups_to_process) - idx + 1)
                    stats["skipped"] += len(groups_to_process) - idx + 1
                    break

                success = self._post_in_group(group_url, post_text, images)

                if success:
                    stats["success"] += 1
                    last_post_text = post_text
                    # Notifier l'anti-block manager
                    self.anti_block.record_post(text=post_text, images=images)
                    self.db.record_publication(
                        account_name=self.session_name,
                        group_url=group_url,
                        status="success",
                        post_content=post_text,
                    )
                    if self.config.get("add_comments", False):
                        self._add_comment_after_post()
                else:
                    stats["skipped"] += 1
                    self.db.record_publication(
                        account_name=self.session_name,
                        group_url=group_url,
                        status="skipped",
                        post_content=post_text,
                    )

            except (SessionExpiredError, FacebookBlockedError, RateLimitError) as e:
                emit("ERROR", "CRITICAL_STOPPING",
                     compte=self.session_name, error=str(e))
                stats["errors"] += 1
                if isinstance(e, FacebookBlockedError):
                    self.db.record_account_block(
                        self.session_name, "facebook_block", str(e)
                    )
                elif isinstance(e, SessionExpiredError):
                    self.db.update_account_status(
                        self.session_name, "session_expired"
                    )
                break

            except Exception as e:
                emit("ERROR", "GROUP_ERROR", groupe=group_url[:80], error=str(e))
                screenshot_path = self.engine.screenshot_on_error(
                    self._page, f"group_err_{idx}"
                )
                stats["errors"] += 1
                self.db.record_error(
                    account_name=self.session_name,
                    group_url=group_url,
                    error_type=type(e).__name__,
                    error_message=str(e),
                    screenshot_path=screenshot_path,
                )

            if idx < len(groups_to_process):
                human_delay_between_groups(
                    min_s=float(delay_cfg[0]),
                    max_s=float(delay_cfg[1])
                )

        emit("INFO", "SESSION_STATS", compte=self.session_name, **stats)
        return stats

    # ──────────────────────────────────────────
    # Cœur de la publication
    # ──────────────────────────────────────────

    @retry(max_attempts=3, delay=4, backoff=2)
    def _post_in_group(self, group_url: str, post_text: str,
                       images: List[str]) -> bool:
        """Publie un post dans un groupe Facebook. Retryable 3×."""
        page = self._page

        if not self.engine.navigate(page, group_url):
            return False
        human_delay(2.5, 0.8)

        check_page_state(page)
        if not check_group_accessible(page):
            emit("WARN", "GROUP_SKIPPED_INACCESSIBLE", url=group_url[:80])
            return False

        # Ouvrir zone de saisie
        try:
            btn = self.selectors.find(page, "display_input", timeout=10000)
            btn.click()
            self.health_manager.record_success("display_input", "display_input")
        except SelectorNotFound as exc:
            self.health_manager.record_failure("display_input", str(exc), exc.tried)
            emit("WARN", "DISPLAY_INPUT_NOT_FOUND", url=group_url[:80])
            return False
        human_delay(1.5, 0.5)

        check_page_state(page)

        # Saisie texte
        try:
            field = self.selectors.find(page, "input", timeout=8000)
            field.click()
            field.press_sequentially(post_text, delay=random.randint(40, 160))
            self.health_manager.record_success("input", "input")
            human_delay(1.0, 0.3)
        except SelectorNotFound as exc:
            self.health_manager.record_failure("input", str(exc), exc.tried)
            emit("WARN", "INPUT_NOT_FOUND", url=group_url[:80])
            return False

        # Upload images ou thème
        if images:
            uploaded = self._upload_images_sequential(page, images)
            if not uploaded:
                self._apply_theme(page)
        else:
            if len(post_text) < 120:
                self._apply_theme(page)

        # Soumettre
        try:
            submit = self.selectors.find(page, "submit", timeout=10000)
            submit.click()
            self.health_manager.record_success("submit", "submit")
            post_publication_wait()
            emit("SUCCESS", "POST_PUBLISHED",
                 compte=self.session_name,
                 groupe=group_url[:80],
                 preview=post_text[:60])
            return True
        except SelectorNotFound as exc:
            self.health_manager.record_failure("submit", str(exc), exc.tried)
            emit("ERROR", "SUBMIT_NOT_FOUND", url=group_url[:80])
            self.engine.screenshot_on_error(page, "submit_missing")
            return False

    # ──────────────────────────────────────────
    # Upload images
    # ──────────────────────────────────────────

    def _upload_images_sequential(self, page, images: List[str]) -> bool:
        """Upload des images une par une. Max 30 (limite Facebook)."""
        images = images[:30]
        uploaded_count = 0

        try:
            show_btn = self.selectors.find(page, "show_image_input", timeout=6000)
            show_btn.click()
            human_delay(1.2, 0.4)
        except SelectorNotFound:
            emit("WARN", "SHOW_IMAGE_BTN_NOT_FOUND")
            return False

        for img_path in images:
            if not pathlib.Path(img_path).exists():
                emit("WARN", "IMAGE_SKIP_NOT_FOUND", path=img_path)
                continue
            try:
                add_sel = self.selectors.get_candidates("add_image")
                if add_sel:
                    page.set_input_files(add_sel[0], img_path)
                    human_delay(1.5, 0.5)
                    uploaded_count += 1
                    emit("DEBUG", "IMAGE_UPLOADED", path=img_path[-50:])
            except Exception as e:
                emit("WARN", "IMAGE_UPLOAD_FAIL", path=img_path[-50:], error=str(e))

        emit("INFO", "IMAGES_UPLOAD_DONE",
             requested=len(images), uploaded=uploaded_count)
        return uploaded_count > 0

    # ──────────────────────────────────────────
    # Thème fallback
    # ──────────────────────────────────────────

    def _apply_theme(self, page) -> None:
        """Applique un thème coloré si aucune image disponible."""
        try:
            theme_btn = self.selectors.find(page, "display_themes", timeout=4000)
            theme_btn.click()
            human_delay(0.8, 0.2)
            theme_idx = random.randint(1, 5)
            candidates = self.selectors.get_candidates("theme")
            if candidates:
                sel = candidates[0].replace("index", str(theme_idx))
                page.click(sel, timeout=3000)
                emit("DEBUG", "THEME_APPLIED", index=theme_idx)
        except Exception as e:
            emit("WARN", "THEME_SKIP", reason=str(e))

    # ──────────────────────────────────────────
    # Commentaires
    # ──────────────────────────────────────────

    def _add_comment_after_post(self) -> None:
        page = self._page
        # Utiliser les commentaires custom s'ils existent, sinon fallback sur defaults
        comments = self.config.get("comments") or DEFAULT_COMMENTS
        if not comments:
            return

        comment = random.choice(comments)
        human_delay(3, 1)

        try:
            comment_input = self.selectors.find(page, "comment_input", timeout=8000)
            comment_input.click()
            human_delay(0.5, 0.2)
            comment_input.press_sequentially(comment, delay=random.randint(40, 130))
            human_delay(0.5, 0.2)
            comment_input.press("Enter")
            human_delay(2, 0.5)
            emit("SUCCESS", "COMMENT_ADDED",
                 compte=self.session_name, preview=comment[:40])
        except SelectorNotFound:
            emit("WARN", "COMMENT_INPUT_NOT_FOUND")
        except Exception as e:
            emit("WARN", "COMMENT_FAILED", error=str(e))

    # ──────────────────────────────────────────
    # Sauvegarde des groupes
    # ──────────────────────────────────────────

    def save_groups(self, keyword: str) -> List[str]:
        """Recherche et retourne les URLs de groupes pour un mot-clé."""
        page       = self._page
        search_url = f"https://www.facebook.com/groups/search/groups/?q={keyword}"
        emit("INFO", "SAVE_GROUPS_START", keyword=keyword)

        if not self.engine.navigate(page, search_url):
            return []

        human_delay(3.0, 0.8)
        check_page_state(page)
        human_scroll_to_bottom(page, stable_count=3)

        links = []
        try:
            elements = self.selectors.find_all(page, "group_link")
            for el in elements:
                href = el.get_attribute("href")
                if href and "facebook.com/groups/" in href:
                    clean = href.split("?")[0].rstrip("/") + "/"
                    links.append(clean)
        except Exception as e:
            emit("WARN", "GROUP_LINK_EXTRACT_ERROR", error=str(e))

        links = list(dict.fromkeys(links))
        emit("SUCCESS", "GROUPS_SAVED", keyword=keyword, count=len(links))
        self.config["groups"] = links
        return links

    # ──────────────────────────────────────────
    # Marketplace
    # ──────────────────────────────────────────

    @retry(max_attempts=2, delay=5)
    def post_in_marketplace(self) -> bool:
        """Publie une annonce dans Facebook Marketplace."""
        mkt = self.config.get("marketplace", {})
        if not mkt:
            emit("WARN", "MARKETPLACE_NOT_CONFIGURED", compte=self.session_name)
            return False

        page = self._page
        emit("INFO", "MARKETPLACE_START", compte=self.session_name)

        if not self.engine.navigate(page, "https://www.facebook.com/marketplace/create/item"):
            return False
        human_delay(3.0, 1.0)
        check_page_state(page)

        try:
            title = random.choice(mkt.get("titles", ["Produit"]))
            title_field = self.selectors.find(page, "marketplace_title", timeout=8000)
            title_field.click()
            title_field.press_sequentially(title, delay=random.randint(50, 150))
            human_delay(0.8, 0.2)

            price = str(mkt.get("price", "1"))
            price_field = self.selectors.find(page, "marketplace_price", timeout=6000)
            price_field.click()
            price_field.press_sequentially(price, delay=random.randint(80, 180))
            human_delay(0.5, 0.2)

            desc = random.choice(mkt.get("descriptions", ["Description"]))
            desc_field = self.selectors.find(page, "marketplace_description", timeout=6000)
            desc_field.click()
            desc_field.press_sequentially(desc, delay=random.randint(40, 130))
            human_delay(1.0, 0.3)

            mkt_images = self._resolve_images_list(mkt.get("images", []))
            if mkt_images:
                try:
                    add_photos = self.selectors.find(
                        page, "marketplace_add_photos", timeout=5000
                    )
                    add_photos.click()
                    human_delay(1, 0.3)
                    add_sel = self.selectors.get_candidates("add_image")
                    if add_sel:
                        page.set_input_files(add_sel[0], mkt_images[:10])
                    human_delay(3, 1)
                except SelectorNotFound:
                    emit("WARN", "MARKETPLACE_PHOTOS_BTN_NOT_FOUND")

            submit = self.selectors.find(page, "marketplace_submit", timeout=8000)
            submit.click()
            human_delay(3, 1)

            emit("SUCCESS", "MARKETPLACE_PUBLISHED",
                 compte=self.session_name, titre=title)
            return True

        except SelectorNotFound as e:
            emit("ERROR", "MARKETPLACE_SELECTOR_MISSING",
                 compte=self.session_name, error=str(e))
            self.engine.screenshot_on_error(page, "marketplace_error")
            return False

    # ──────────────────────────────────────────
    # Helpers
    # ──────────────────────────────────────────

    @staticmethod
    def _pick_post(posts: list, exclude_text: Optional[str] = None) -> dict:
        """
        Sélection aléatoire pondérée d'un post (champ 'weight').
        Évite de répéter le même texte deux fois de suite (exclude_text).
        """
        if not posts:
            return {}
        candidates = posts
        if exclude_text and len(posts) > 1:
            candidates = [p for p in posts if p.get("text", "") != exclude_text]
            if not candidates:
                candidates = posts  # tous identiques → pas de choix
        weights = [max(1, p.get("weight", 1)) for p in candidates]
        return random.choices(candidates, weights=weights, k=1)[0]

    def _resolve_images_list(self, images: List[str]) -> List[str]:
        """Résout et filtre une liste de chemins d'images."""
        resolved = []
        for img in images:
            if not img:
                continue
            p = resolve_media_path(img, self.session_name)
            if p.exists():
                resolved.append(str(p))
            else:
                emit("WARN", "IMAGE_NOT_FOUND", path=str(p))
        return resolved
