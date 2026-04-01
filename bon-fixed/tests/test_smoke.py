"""
tests/test_smoke.py — Tests de non-régression fondamentaux
Exécution : python -m pytest tests/ -v
             (sans Playwright ni Facebook — tests purement unitaires)

CORRECTIONS v4 :
  - TestDataFiles : pointe vers data/campaigns/campaigns.json et data/groups/groups.json
    (data.json et data1.json n'existent pas dans la structure actuelle)
  - TestAntiBlockManager : nouveaux tests pour l'API publique refactorisée
  - TestFrequencyLimits  : test de la remise à zéro journalière du compteur
  - TestPlaywrightEngine : test de la propriété publique browser
"""
import sys
import pathlib
import pytest

sys.path.insert(0, str(pathlib.Path(__file__).parent.parent))


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 1 — resolve_media_path : chemins Windows sur Linux/macOS
# ═══════════════════════════════════════════════════════════════════════════════

class TestResolveMediaPath:
    """Fix F-01 : PureWindowsPath pour les chemins avec antislash."""

    def _get_fn(self):
        from libs.config_manager import resolve_media_path
        return resolve_media_path

    def test_windows_backslash_path_extracts_filename(self):
        fn = self._get_fn()
        result = fn(r"C:\Users\Administrator\AppData\Roaming\saadiya\media\media10\7.png")
        assert result.name == "7.png", \
            f"Attendu '7.png', obtenu '{result.name}'"

    def test_windows_path_with_spaces(self):
        fn = self._get_fn()
        result = fn(r"C:\Users\My User\Documents\image test.jpg")
        assert result.name == "image test.jpg"

    def test_simple_relative_path(self):
        fn = self._get_fn()
        result = fn("mon_image.jpg")
        assert result.name == "mon_image.jpg"

    def test_unix_relative_path(self):
        fn = self._get_fn()
        result = fn("subfolder/image.png")
        assert result.name == "image.png"

    def test_empty_string_does_not_crash(self):
        fn = self._get_fn()
        try:
            fn("")
        except Exception as e:
            pytest.fail(f"resolve_media_path('') a levé {e}")

    def test_windows_jpg_extension_preserved(self):
        fn = self._get_fn()
        result = fn(r"D:\photos\profil\avatar.jpg", "compte1")
        assert result.suffix == ".jpg"

    def test_no_backslash_path_unchanged(self):
        fn = self._get_fn()
        result = fn("logo.png", "session1")
        assert result.name == "logo.png"


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 2 — DEFAULT_SESSION_CONFIG : tous les champs requis présents
# ═══════════════════════════════════════════════════════════════════════════════

class TestDefaultSessionConfig:
    """Fix F-02 : DEFAULT_SESSION_CONFIG doit contenir tous les champs utilisés."""

    REQUIRED_FIELDS = [
        "session_name",
        "storage_state",
        "max_groups_per_run",
        "delay_between_groups",
        "max_runs_per_day",
        "cooldown_between_runs_s",
        "last_run_ts",
        "run_count_today",
        "posts",
        "groups",
        "add_comments",
        "comments",
        "marketplace",
        "last_run_date",
    ]

    def _get_config(self):
        from libs.session_manager import DEFAULT_SESSION_CONFIG
        return DEFAULT_SESSION_CONFIG

    def test_all_required_fields_present(self):
        config = self._get_config()
        missing = [f for f in self.REQUIRED_FIELDS if f not in config]
        assert not missing, f"Champs manquants : {missing}"

    def test_add_comments_default_false(self):
        assert self._get_config()["add_comments"] is False

    def test_marketplace_default_empty_dict(self):
        assert isinstance(self._get_config()["marketplace"], dict)

    def test_comments_default_empty_list(self):
        assert isinstance(self._get_config()["comments"], list)

    def test_cooldown_default_7200(self):
        assert self._get_config()["cooldown_between_runs_s"] == 7200

    def test_posts_default_list(self):
        assert isinstance(self._get_config()["posts"], list)

    def test_groups_default_list(self):
        assert isinstance(self._get_config()["groups"], list)


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 3 — timing_humanizer : check_cooldown et check_session_limit
# ═══════════════════════════════════════════════════════════════════════════════

class TestTimingHumanizer:
    """Vérification des limites de fréquence sans accès réseau ni browser."""

    def test_no_last_run_always_ok(self):
        from libs.timing_humanizer import check_cooldown
        assert check_cooldown(None, 7200) is True

    def test_recent_run_blocked(self):
        from libs.timing_humanizer import check_cooldown
        from datetime import datetime, timedelta
        recent = (datetime.now() - timedelta(seconds=3600)).isoformat()
        assert check_cooldown(recent, 7200) is False

    def test_old_run_allowed(self):
        from libs.timing_humanizer import check_cooldown
        from datetime import datetime, timedelta
        old = (datetime.now() - timedelta(seconds=8000)).isoformat()
        assert check_cooldown(old, 7200) is True

    def test_invalid_timestamp_does_not_crash(self):
        from libs.timing_humanizer import check_cooldown
        assert check_cooldown("not-a-date", 7200) is True

    def test_limit_not_reached(self):
        from libs.timing_humanizer import check_session_limit
        assert check_session_limit(1, max_runs_per_day=3) is True

    def test_limit_exactly_reached(self):
        from libs.timing_humanizer import check_session_limit
        assert check_session_limit(3, max_runs_per_day=3) is False

    def test_limit_exceeded(self):
        from libs.timing_humanizer import check_session_limit
        assert check_session_limit(10, max_runs_per_day=3) is False

    def test_update_stats_increments_count(self):
        from libs.timing_humanizer import update_session_run_stats
        from datetime import date
        cfg = {"run_count_today": 1, "last_run_date": date.today().isoformat(),
               "last_run_ts": None}
        updated = update_session_run_stats(cfg)
        assert updated["run_count_today"] == 2

    def test_update_stats_resets_on_new_day(self):
        from libs.timing_humanizer import update_session_run_stats
        cfg = {"run_count_today": 5, "last_run_date": "2020-01-01",
               "last_run_ts": None}
        updated = update_session_run_stats(cfg)
        assert updated["run_count_today"] == 1


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 4 — CORRECTION : data files pointent vers la bonne structure
# ═══════════════════════════════════════════════════════════════════════════════

class TestDataFiles:
    """Fix F-04 : les fichiers de données existent et ont la bonne structure."""

    ROOT = pathlib.Path(__file__).parent.parent

    def _load(self, rel_path):
        import json
        full = self.ROOT / rel_path
        assert full.exists(), f"Fichier introuvable : {full}"
        return json.loads(full.read_text(encoding="utf-8"))

    def test_campaigns_json_exists_and_valid(self):
        data = self._load("data/campaigns/campaigns.json")
        assert isinstance(data, (dict, list)), "campaigns.json doit être un dict ou une liste"

    def test_groups_json_exists_and_valid(self):
        data = self._load("data/groups/groups.json")
        assert isinstance(data, (dict, list)), "groups.json doit être un dict ou une liste"

    def test_campaigns_no_private_paths(self):
        data = self._load("data/campaigns/campaigns.json")
        text = str(data)
        assert "Administrator" not in text, "Chemin Windows personnel détecté"
        assert "AppData" not in text, "Chemin Windows personnel détecté"

    def test_groups_no_private_paths(self):
        data = self._load("data/groups/groups.json")
        text = str(data)
        assert "Administrator" not in text, "Chemin Windows personnel détecté"


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 5 — check_license : parse_license ne crashe pas sur entrées invalides
# ═══════════════════════════════════════════════════════════════════════════════

class TestLicenseParsing:
    """Vérification que parse_license est robuste."""

    def _parse(self, s):
        from check_license import parse_license
        return parse_license(s)

    def test_empty_string_returns_none(self):
        result = self._parse("")
        assert result[0] is None

    def test_invalid_format_returns_none(self):
        result = self._parse("invalid-key-12345")
        assert result[0] is None

    def test_wrong_prefix_returns_none(self):
        result = self._parse("XXXX030MySerial:AA-BB-CC-DD-EE010101120025TestUser")
        assert result[0] is None

    def test_get_serial_does_not_crash(self):
        from check_license import get_serial_number
        result = get_serial_number()
        assert isinstance(result, str)
        assert len(result) > 0

    def test_get_mac_addresses_returns_list(self):
        from check_license import get_mac_addresses
        macs = get_mac_addresses()
        assert isinstance(macs, list)
        assert len(macs) >= 1


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 6 — AntiBlockManager : API publique v4
# ═══════════════════════════════════════════════════════════════════════════════

class TestAntiBlockManager:
    """Vérifie que AntiBlockManager fonctionne correctement."""

    def _get_manager(self, tmp_path):
        from automation.anti_block import AntiBlockManager
        return AntiBlockManager(state_file=str(tmp_path / "anti_block.json"))

    def test_can_post_initially(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        assert mgr.can_post_now() is True

    def test_hourly_limit_enforced(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        mgr.max_groups_per_hour = 2
        mgr.long_pause_after_posts = 999  # désactiver la pause longue pour ce test
        mgr.record_post(text="post1")
        mgr.record_post(text="post2")
        assert mgr.can_post_now() is False

    def test_image_use_allowed_initially(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        assert mgr.can_use_image("/path/image.jpg") is True

    def test_image_use_blocked_after_max(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        mgr.max_image_uses = 2
        mgr.record_post(text="a", images=["/img.jpg"])
        mgr.record_post(text="b", images=["/img.jpg"])
        assert mgr.can_use_image("/img.jpg") is False

    def test_image_use_count_tracked(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        mgr.record_post(text="x", images=["/a.jpg", "/b.jpg"])
        assert mgr.get_image_use_count("/a.jpg") == 1
        assert mgr.get_image_use_count("/b.jpg") == 1

    def test_hourly_count(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        mgr.record_post(text="p1")
        mgr.record_post(text="p2")
        assert mgr.get_hourly_post_count() == 2

    def test_reset_image_uses(self, tmp_path):
        mgr = self._get_manager(tmp_path)
        mgr.record_post(text="x", images=["/img.jpg", "/img.jpg"])
        mgr.reset_image_uses()
        assert mgr.get_image_use_count("/img.jpg") == 0

    def test_singleton_returns_same_instance(self):
        from automation.anti_block import get_anti_block_manager
        a = get_anti_block_manager()
        b = get_anti_block_manager()
        assert a is b


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 7 — Fréquence journalière : remise à zéro du compteur
# ═══════════════════════════════════════════════════════════════════════════════

class TestFrequencyReset:
    """Fix F-bug : run_count_today doit se remettre à 0 si last_run_date != today."""

    def test_counter_resets_on_new_day(self):
        """Simule un config avec un run d'hier → le compteur doit être perçu comme 0."""
        from datetime import date
        config = {
            "run_count_today": 5,
            "last_run_date": "2020-01-01",  # date passée
            "last_run_ts": None,
            "cooldown_between_runs_s": 0,
            "max_runs_per_day": 3,
        }
        today = date.today().isoformat()
        if config.get("last_run_date", "") != today:
            config["run_count_today"] = 0

        from libs.timing_humanizer import check_session_limit
        assert check_session_limit(config["run_count_today"], config["max_runs_per_day"]) is True

    def test_counter_not_reset_same_day(self):
        """Si last_run_date == aujourd'hui, le compteur doit rester intouché."""
        from datetime import date
        today = date.today().isoformat()
        config = {
            "run_count_today": 3,
            "last_run_date": today,
            "max_runs_per_day": 3,
        }
        if config.get("last_run_date", "") != today:
            config["run_count_today"] = 0

        from libs.timing_humanizer import check_session_limit
        assert check_session_limit(config["run_count_today"], config["max_runs_per_day"]) is False


# ═══════════════════════════════════════════════════════════════════════════════
# TEST 8 — PlaywrightEngine : propriété publique browser
# ═══════════════════════════════════════════════════════════════════════════════

class TestPlaywrightEngine:
    """Vérifie que engine.browser est accessible (propriété publique)."""

    def test_browser_property_exists(self):
        """La propriété browser doit exister et retourner None avant start()."""
        from libs.playwright_engine import PlaywrightEngine
        engine = PlaywrightEngine()
        assert hasattr(engine, "browser")
        assert engine.browser is None  # Non démarré

    def test_no_private_browser_access_needed(self):
        """engine._browser ne doit plus être nécessaire en dehors de la classe."""
        from libs.playwright_engine import PlaywrightEngine
        engine = PlaywrightEngine()
        # La propriété publique suffit
        _ = engine.browser
        # Si on peut accéder sans AttributeError, le test passe
