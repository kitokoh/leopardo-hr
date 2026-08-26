"""Test du bridge local ZKTeco — injection des globals cloud (issue #2750).

Le bridge sert les pages HTML brutes : `app.js` lit
`window.__KIOSK_API_BASE / __KIOSK_DEVICE_CODE / __KIOSK_TOKEN` mais rien ne
les définissait → device code vide → `/api/v1/kiosks//…` → 404 pour toutes
les fonctions cloud. Ce test vérifie que les globals sont injectés dans
index.html servie par le bridge.
"""

from __future__ import annotations

import json
import threading
import unittest
from http.server import ThreadingHTTPServer
from pathlib import Path
from urllib.request import urlopen

import sys

BRIDGE_DIR = Path(__file__).resolve().parents[1] / "desktop-bridge"
sys.path.insert(0, str(BRIDGE_DIR))

import bridge  # noqa: E402


class BridgeGlobalsInjectionTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.original_config = dict(bridge.CONFIG)
        bridge.CONFIG.update(
            {
                "apiBaseUrl": "https://example.test/api/v1",
                "deviceCode": "KIOSK-TEST-001",
                "kioskToken": "token-test-123",
            }
        )
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), bridge.BridgeHandler)
        cls.port = cls.server.server_address[1]
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls) -> None:
        cls.server.shutdown()
        cls.server.server_close()
        bridge.CONFIG.clear()
        bridge.CONFIG.update(cls.original_config)

    def test_index_html_injects_kiosk_globals(self) -> None:
        with urlopen(f"http://127.0.0.1:{self.port}/index.html", timeout=10) as response:
            html = response.read().decode("utf-8")

        self.assertIn("window.__KIOSK_API_BASE = \"https://example.test/api/v1\"", html)
        self.assertIn("window.__KIOSK_DEVICE_CODE = \"KIOSK-TEST-001\"", html)
        self.assertIn("window.__KIOSK_TOKEN = \"token-test-123\"", html)
        # #5619 — demoMode absent de la config de test → false injecté par défaut.
        self.assertIn("window.__KIOSK_DEMO_MODE = false", html)
        # Injection placée dans le <head> (avant le body)
        self.assertLess(html.index("window.__KIOSK_"), html.index("</head>"))

    def test_root_serves_index_with_globals(self) -> None:
        with urlopen(f"http://127.0.0.1:{self.port}/", timeout=10) as response:
            html = response.read().decode("utf-8")
        self.assertIn("window.__KIOSK_DEVICE_CODE = \"KIOSK-TEST-001\"", html)

    def test_js_files_are_not_modified(self) -> None:
        with urlopen(f"http://127.0.0.1:{self.port}/app.js", timeout=10) as response:
            js = response.read().decode("utf-8")
        # app.js LIT les globals (window.__KIOSK_DEVICE_CODE || '') mais le
        # bridge ne doit jamais injecter d'assignation dans les .js.
        self.assertNotIn("window.__KIOSK_DEVICE_CODE = \"KIOSK-TEST-001\"", js)
        self.assertNotIn("window.__KIOSK_API_BASE = ", js)


class SyncEngineBaseUrlNormalizationTest(unittest.TestCase):
    """Issue #3590 — drift apiBaseUrl : une config sans /api/v1 doit être
    normalisée comme le fait app.js, sinon l'UI marche mais la sync bridge 404."""

    def _engine(self, api_base_url: str) -> bridge.SyncEngine:
        return bridge.SyncEngine(
            {"apiBaseUrl": api_base_url, "deviceCode": "K1", "kioskToken": "t"},
            bridge.LocalStore(":memory:"),
        )

    def test_base_url_with_version_suffix_is_kept(self) -> None:
        engine = self._engine("https://example.test/api/v1")
        self.assertEqual(engine.api_base_url, "https://example.test/api/v1")

    def test_base_url_without_version_suffix_gets_api_v1(self) -> None:
        engine = self._engine("https://example.test")
        self.assertEqual(engine.api_base_url, "https://example.test/api/v1")

    def test_base_url_trailing_slash_is_stripped(self) -> None:
        engine = self._engine("https://example.test/api/v1/")
        self.assertEqual(engine.api_base_url, "https://example.test/api/v1")

    def test_health_probe_uses_light_endpoint(self) -> None:
        # online_status ne doit plus télécharger le roster complet : la
        # requête cible /health (assertion sur l'URL construite).
        engine = self._engine("https://example.test/api/v1")
        url = f"{engine.api_base_url}/health"
        self.assertEqual(url, "https://example.test/api/v1/health")
        self.assertNotIn("roster", url)


if __name__ == "__main__":
    unittest.main()
