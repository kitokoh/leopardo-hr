"""Tests sécurité/résilience du bridge local ZKTeco (issues #3586, #3587, #3588).

#3586 — le bridge servait TOUT fichier sous la racine (config.json = token
kiosk réel, kiosk.db = PII) et acceptait des POST /local/punch sans auth ni
vérification Origin/Content-Type → forge de pointages cross-origin.
#3588 — un événement « poison » (action/biometric_type hors enum) entrait en
file et faisait échouer chaque sync (422 batch) indéfiniment.
Ces tests verrouillent : allowlist statique, token de session local, guards
Origin/Content-Type, validation enums à l'insertion, endpoint requeue.
"""

from __future__ import annotations

import io
import json
import threading
import unittest
import urllib.error
import urllib.request
from http.server import ThreadingHTTPServer
from pathlib import Path

import sys

BRIDGE_DIR = Path(__file__).resolve().parents[1] / "desktop-bridge"
sys.path.insert(0, str(BRIDGE_DIR))

import bridge  # noqa: E402


class BridgeSecurityTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.original_config = dict(bridge.CONFIG)
        bridge.CONFIG.update(
            {
                "apiBaseUrl": "https://example.test/api/v1",
                "deviceCode": "KIOSK-SEC-001",
                "kioskToken": "token-sec-123",
                # Pas de sync réseau pendant les tests d'insertion.
                "autoSync": False,
            }
        )
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), bridge.BridgeHandler)
        cls.port = cls.server.server_address[1]
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()
        cls.base = f"http://127.0.0.1:{cls.port}"
        cls.token = bridge.LOCAL_BRIDGE_TOKEN

    @classmethod
    def tearDownClass(cls) -> None:
        cls.server.shutdown()
        cls.server.server_close()
        bridge.CONFIG.clear()
        bridge.CONFIG.update(cls.original_config)

    # ── helpers ──────────────────────────────────────
    def _request(self, path, method="GET", payload=None, headers=None):
        body = json.dumps(payload).encode("utf-8") if payload is not None else None
        req = urllib.request.Request(
            f"{self.base}{path}",
            method=method,
            data=body,
            headers=headers or {},
        )
        try:
            with urllib.request.urlopen(req, timeout=30) as response:
                return response.status, response.read().decode("utf-8"), dict(response.headers)
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode("utf-8"), dict(error.headers)

    def _auth_headers(self, token=None):
        return {"X-Local-Bridge-Token": token if token is not None else self.token}

    def _json_headers(self, token=None):
        headers = self._auth_headers(token)
        headers["Content-Type"] = "application/json"
        return headers

    # ── #3586 : allowlist statique ───────────────────
    def test_config_json_is_never_served(self) -> None:
        status, _, _ = self._request("/config.json")
        self.assertEqual(status, 404)

    def test_kiosk_db_is_never_served(self) -> None:
        status, _, _ = self._request("/desktop-bridge/data/kiosk.db")
        self.assertEqual(status, 404)

    def test_bridge_source_is_never_served(self) -> None:
        status, _, _ = self._request("/desktop-bridge/bridge.py")
        self.assertEqual(status, 404)
        status, _, _ = self._request("/package.json")
        self.assertEqual(status, 404)

    def test_path_traversal_is_blocked(self) -> None:
        # urllib normalise ../ côté client : on teste le préfixe-sibling et
        # un chemin encodé qui survivrait à un startswith naïf.
        status, _, _ = self._request("/../config.json")
        self.assertEqual(status, 404)
        status, _, _ = self._request("/%2e%2e/config.json")
        self.assertEqual(status, 404)

    def test_ui_assets_are_served(self) -> None:
        for asset in ("/", "/index.html", "/app.js", "/admin.html", "/admin.js", "/i18n.js"):
            status, _, _ = self._request(asset)
            self.assertEqual(status, 200, asset)

    # ── #3586 : token de session local ───────────────
    def test_local_status_requires_token(self) -> None:
        status, body, _ = self._request("/local/status")
        self.assertEqual(status, 401)
        self.assertIn("LOCAL_TOKEN_REQUIRED", body)

    def test_local_status_rejects_wrong_token(self) -> None:
        status, _, _ = self._request("/local/status", headers=self._auth_headers("wrong-token"))
        self.assertEqual(status, 401)

    def test_local_status_accepts_session_token(self) -> None:
        status, body, _ = self._request("/local/status", headers=self._auth_headers())
        self.assertEqual(status, 200)
        payload = json.loads(body)
        self.assertIn("queue_count", payload["data"])
        self.assertIn("dead_letter_count", payload["data"])

    def test_local_events_and_roster_require_token(self) -> None:
        for path in ("/local/events", "/local/roster"):
            status, _, _ = self._request(path)
            self.assertEqual(status, 401, path)

    def test_html_injects_local_bridge_token(self) -> None:
        status, html, headers = self._request("/index.html")
        self.assertEqual(status, 200)
        self.assertIn("window.__LOCAL_BRIDGE_TOKEN", html)
        # Le HTML transporte le token : ne jamais le laisser en cache.
        self.assertIn("no-store", headers.get("Cache-Control", ""))

    # ── #3586 : guards POST ──────────────────────────
    def test_post_without_json_content_type_is_rejected(self) -> None:
        req = urllib.request.Request(
            f"{self.base}/local/punch",
            method="POST",
            data=b"identifier=FP-001",
            headers={
                "X-Local-Bridge-Token": self.token,
                "Content-Type": "application/x-www-form-urlencoded",
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=30) as response:
                status = response.status
        except urllib.error.HTTPError as error:
            status = error.code
        self.assertEqual(status, 415)

    def test_post_with_cross_origin_is_rejected(self) -> None:
        status, body, _ = self._request(
            "/local/punch",
            method="POST",
            payload={"identifier": "FP-001"},
            headers={
                **self._json_headers(),
                "Origin": "https://evil.example.com",
            },
        )
        self.assertEqual(status, 403)
        self.assertIn("ORIGIN_FORBIDDEN", body)

    # ── #3588 : validation à l'insertion ─────────────
    def test_punch_rejects_invalid_action(self) -> None:
        status, body, _ = self._request(
            "/local/punch",
            method="POST",
            payload={"identifier": "FP-001", "action": "break_in"},
            headers=self._json_headers(),
        )
        self.assertEqual(status, 422)
        self.assertIn("INVALID_ACTION", body)

    def test_punch_rejects_invalid_biometric_type(self) -> None:
        status, body, _ = self._request(
            "/local/punch",
            method="POST",
            payload={"identifier": "FP-001", "biometric_type": "retina"},
            headers=self._json_headers(),
        )
        self.assertEqual(status, 422)
        self.assertIn("INVALID_BIOMETRIC_TYPE", body)

    def test_punch_valid_event_is_queued(self) -> None:
        status, body, _ = self._request(
            "/local/punch",
            method="POST",
            payload={"identifier": "FP-SEC-1", "action": "check_in", "biometric_type": "face"},
            headers=self._json_headers(),
        )
        self.assertEqual(status, 201)
        payload = json.loads(body)
        self.assertEqual(payload["data"]["sync_status"], "queued")

    # ── #3588 : requeue ops ──────────────────────────
    def test_requeue_requires_token_and_known_event(self) -> None:
        status, _, _ = self._request(
            "/local/events/requeue",
            method="POST",
            payload={"external_event_id": "evt-x"},
            headers={"Content-Type": "application/json"},
        )
        self.assertEqual(status, 401)

        status, _, _ = self._request(
            "/local/events/requeue",
            method="POST",
            payload={"external_event_id": "evt-does-not-exist"},
            headers=self._json_headers(),
        )
        self.assertEqual(status, 404)


class SyncSkippedContractTest(unittest.TestCase):
    """#3587 — le bridge ne marque plus synced en aveugle : il exploite la
    liste `skipped` du serveur et conserve/flagge les non-traités."""

    def _engine(self) -> bridge.SyncEngine:
        return bridge.SyncEngine(
            {"apiBaseUrl": "https://example.test/api/v1", "deviceCode": "K1", "kioskToken": "t"},
            bridge.LocalStore(":memory:"),
        )

    def test_skipped_events_go_to_dead_letter_not_synced(self) -> None:
        engine = self._engine()
        engine.store.queue_punch("KNOWN", "check_in", "fingerprint")
        engine.store.queue_punch("UNKNOWN", "check_in", "fingerprint")
        events = engine.store.queued_events()
        ok_id, ko_id = events[0]["external_event_id"], events[1]["external_event_id"]

        class FakeResponse:
            def get(self, key, default=None):
                return {
                    "data": {
                        "processed_count": 1,
                        "skipped": [
                            {"external_event_id": ko_id, "identifier": "UNKNOWN", "reason": "EMPLOYEE_NOT_FOUND"}
                        ],
                    }
                }.get(key, default)

        engine._request = lambda *args, **kwargs: FakeResponse()  # type: ignore[assignment]
        result = engine.upload_events()

        self.assertEqual(result["processed_count"], 1)
        self.assertEqual(result["skipped_count"], 1)
        remaining = engine.store.queued_events()
        self.assertEqual(remaining, [])
        all_events = {e["external_event_id"]: e for e in engine.store.all_events()}
        self.assertEqual(all_events[ok_id]["sync_status"], "synced")
        self.assertEqual(all_events[ko_id]["sync_status"], "dead_letter")
        self.assertEqual(all_events[ko_id]["error_message"], "EMPLOYEE_NOT_FOUND")
        self.assertEqual(engine.store.dead_letter_count(), 1)

    def test_legacy_partial_sync_never_marks_all_synced(self) -> None:
        engine = self._engine()
        engine.store.queue_punch("A", "check_in", "fingerprint")
        engine.store.queue_punch("B", "check_in", "fingerprint")

        class FakeResponse:
            def get(self, key, default=None):
                # Serveur legacy : pas de clé `skipped`, processed_count < envoyés.
                return {"data": {"processed_count": 1}}.get(key, default)

        engine._request = lambda *args, **kwargs: FakeResponse()  # type: ignore[assignment]
        result = engine.upload_events()

        self.assertIn("warning", result)
        statuses = {e["external_event_id"]: e["sync_status"] for e in engine.store.all_events()}
        # Aucun marquage synced global (#3587) : les événements restent en file.
        self.assertTrue(all(status == "queued" for status in statuses.values()))
        self.assertIn("SYNC_PARTIAL_WITHOUT_DETAIL", engine.store.get_state("last_sync_error", ""))

    def test_poison_event_is_dead_lettered_and_batch_retried(self) -> None:
        engine = self._engine()
        engine.store.queue_punch("GOOD", "check_in", "fingerprint")
        engine.store.queue_punch("POISON", "check_in", "fingerprint")
        events = engine.store.queued_events()
        good_id, poison_id = events[0]["external_event_id"], events[1]["external_event_id"]

        calls = {"n": 0}

        def fake_request(method, path, payload=None):
            calls["n"] += 1
            sent = payload["events"]
            if len(sent) == 2:
                body = json.dumps({"message": "invalid", "errors": {"events.1.action": ["invalid"]}}).encode()
                raise urllib.error.HTTPError(
                    "https://example.test/api/v1/kiosks/K1/sync", 422, "Unprocessable", {}, io.BytesIO(body)
                )
            return {"data": {"processed_count": 1, "skipped": []}}

        engine._request = fake_request  # type: ignore[assignment]
        result = engine.upload_events()

        self.assertEqual(calls["n"], 2)
        self.assertEqual(result["processed_count"], 1)
        statuses = {e["external_event_id"]: e["sync_status"] for e in engine.store.all_events()}
        self.assertEqual(statuses[good_id], "synced")
        self.assertEqual(statuses[poison_id], "dead_letter")

    def test_requeue_restores_dead_letter(self) -> None:
        engine = self._engine()
        event = engine.store.queue_punch("X", "check_in", "fingerprint")
        engine.store.mark_dead_letter([event["external_event_id"]], "EMPLOYEE_NOT_FOUND")
        self.assertEqual(engine.store.dead_letter_count(), 1)

        self.assertTrue(engine.store.requeue_event(event["external_event_id"]))
        self.assertEqual(engine.store.dead_letter_count(), 0)
        queued = engine.store.queued_events()
        self.assertEqual(len(queued), 1)
        self.assertEqual(queued[0]["retry_count"], 0)


if __name__ == "__main__":
    unittest.main()
