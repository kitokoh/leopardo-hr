#!/usr/bin/env python3
"""Tests de simulation du bridge d'impression cuisine (#8606).

Aucun matériel requis : un serveur HTTP local rejoue le contrat du bridge
REST-OS-3 (`jobs` / `claim` / `ack` / `fail`) et le transport `file` capture le
flux ESC/POS réellement envoyé.

Lancer :  python3 edge/print-bridge/test_print_bridge.py
"""

from __future__ import annotations

import base64
import json
import sys
import tempfile
import threading
import unittest
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from leopardo_print_bridge import (  # noqa: E402
    BridgeClient,
    BridgeConfig,
    FileTransport,
    PrintBridge,
    PrintJournal,
    PrinterError,
    decode_printer_status,
)

TOKEN = "test-bridge-token"
COMPANY = "company-1"
BRANCH = "branch-1"
PAYLOAD = b"\x1b@KITCHEN TICKET\n\x1dV\x00"


class FakeBridgeServer:
    """Serveur de simulation du contrat bridge (état en mémoire)."""

    def __init__(self) -> None:
        self.jobs: list[dict] = []
        self.claimed: dict[str, str] = {}
        self.acked: list[str] = []
        self.failed: dict[str, str] = {}
        self.requests: list[tuple[str, str]] = []
        self._server: HTTPServer | None = None
        self._thread: threading.Thread | None = None

    # -- cycle de vie --------------------------------------------------------
    def start(self) -> str:
        outer = self

        class Handler(BaseHTTPRequestHandler):
            def log_message(self, *args):  # silence
                pass

            def _json(self, status: int, body: dict) -> None:
                raw = json.dumps(body).encode()
                self.send_response(status)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(raw)))
                self.end_headers()
                self.wfile.write(raw)

            def _authorized(self) -> bool:
                return (
                    self.headers.get("Authorization") == f"Bearer {TOKEN}"
                    and self.headers.get("X-Company-Id") == COMPANY
                )

            def do_GET(self) -> None:  # noqa: N802
                outer.requests.append(("GET", self.path))

                if not self._authorized():
                    self._json(401, {"error": "print_bridge_token_invalid"})
                    return

                if self.path.startswith("/restaurant/printing/bridge/jobs"):
                    self._json(200, {"data": outer.jobs})
                    return

                self._json(404, {"error": "not_found"})

            def do_POST(self) -> None:  # noqa: N802
                outer.requests.append(("POST", self.path))
                length = int(self.headers.get("Content-Length") or 0)
                body = json.loads(self.rfile.read(length) or b"{}")

                if not self._authorized():
                    self._json(401, {"error": "print_bridge_token_invalid"})
                    return

                parts = self.path.strip("/").split("/")
                # restaurant/printing/bridge/jobs/{id}/{action}
                if len(parts) == 6 and parts[3] == "jobs":
                    job_id, action = parts[4], parts[5]

                    if action == "claim":
                        if job_id in outer.claimed:
                            self._json(409, {"error": "job_not_claimable"})
                            return
                        outer.claimed[job_id] = body.get("printer_ref", "")
                        self._json(200, {
                            "job": {"id": job_id, "status": "claimed"},
                            "payload_base64": base64.b64encode(PAYLOAD).decode(),
                            "payload_sha256": "deadbeef",
                            "encoding": "escpos",
                        })
                        return

                    if action == "ack":
                        outer.acked.append(job_id)
                        self._json(200, {"data": {"id": job_id, "status": "printed"}})
                        return

                    if action == "fail":
                        outer.failed[job_id] = body.get("error", "")
                        self._json(200, {"data": {"id": job_id, "status": "pending"}})
                        return

                self._json(404, {"error": "not_found"})

        self._server = HTTPServer(("127.0.0.1", 0), Handler)
        self._thread = threading.Thread(target=self._server.serve_forever, daemon=True)
        self._thread.start()

        host, port = self._server.server_address
        return f"http://{host}:{port}"

    def stop(self) -> None:
        if self._server is not None:
            self._server.shutdown()
            self._server.server_close()


class FlakyTransport(FileTransport):
    """Transport qui échoue N fois avant de réussir (test des tentatives)."""

    def __init__(self, directory: Path, failures: int) -> None:
        super().__init__(directory)
        self.remaining_failures = failures
        self.attempts = 0

    def send(self, payload: bytes) -> None:
        self.attempts += 1
        if self.remaining_failures > 0:
            self.remaining_failures -= 1
            raise PrinterError("paper_out")
        super().send(payload)


class PrintBridgeTest(unittest.TestCase):
    def setUp(self) -> None:
        self.server = FakeBridgeServer()
        self.base_url = self.server.start()
        self.tmp = tempfile.TemporaryDirectory()
        self.dir = Path(self.tmp.name)

    def tearDown(self) -> None:
        self.server.stop()
        self.tmp.cleanup()

    def _bridge(self, transport=None, max_attempts: int = 3) -> PrintBridge:
        config = BridgeConfig(
            base_url=self.base_url,
            token=TOKEN,
            company_id=COMPANY,
            branch_id=BRANCH,
            printer_ref="kitchen",
            paper_width=80,
            max_attempts=max_attempts,
            retry_backoff=0.0,
            state_file=self.dir / "state.json",
            once=True,
        )
        client = BridgeClient(self.base_url, TOKEN, COMPANY)
        return PrintBridge(
            config,
            client,
            transport or FileTransport(self.dir / "printed"),
            PrintJournal(config.state_file),
        )

    # -- tests ---------------------------------------------------------------
    def test_prints_and_acks_a_claimed_job(self) -> None:
        self.server.jobs = [{"id": "job-1", "status": "pending"}]

        handled = self._bridge().run_once()

        self.assertEqual(handled, 1)
        self.assertEqual(self.server.acked, ["job-1"])
        self.assertEqual(self.server.failed, {})
        tickets = list((self.dir / "printed").glob("*.escpos"))
        self.assertEqual(len(tickets), 1)
        self.assertEqual(tickets[0].read_bytes(), PAYLOAD)

    def test_does_not_ack_when_printer_fails(self) -> None:
        self.server.jobs = [{"id": "job-2", "status": "pending"}]
        transport = FlakyTransport(self.dir / "printed", failures=99)

        handled = self._bridge(transport, max_attempts=2).run_once()

        self.assertEqual(handled, 0)
        self.assertEqual(self.server.acked, [])
        self.assertIn("job-2", self.server.failed)
        self.assertIn("paper_out", self.server.failed["job-2"])
        self.assertEqual(transport.attempts, 2)

    def test_retries_then_succeeds(self) -> None:
        self.server.jobs = [{"id": "job-3", "status": "pending"}]
        transport = FlakyTransport(self.dir / "printed", failures=1)

        handled = self._bridge(transport, max_attempts=3).run_once()

        self.assertEqual(handled, 1)
        self.assertEqual(transport.attempts, 2)
        self.assertEqual(self.server.acked, ["job-3"])

    def test_already_claimed_job_is_skipped(self) -> None:
        self.server.jobs = [{"id": "job-4", "status": "claimed"}]
        self.server.claimed["job-4"] = "other-terminal"

        handled = self._bridge().run_once()

        self.assertEqual(handled, 0)
        self.assertEqual(self.server.acked, [])

    def test_crash_recovery_acks_without_reprinting(self) -> None:
        """Un travail imprimé mais non acquitté est acquitté, pas réimprimé."""
        self.server.jobs = [{"id": "job-5", "status": "claimed"}]
        journal = PrintJournal(self.dir / "state.json")
        journal.mark_printed("job-5")

        config = BridgeConfig(
            base_url=self.base_url,
            token=TOKEN,
            company_id=COMPANY,
            branch_id=BRANCH,
            state_file=self.dir / "state.json",
            retry_backoff=0.0,
            once=True,
        )
        bridge = PrintBridge(
            config,
            BridgeClient(self.base_url, TOKEN, COMPANY),
            FileTransport(self.dir / "printed"),
            PrintJournal(config.state_file),
        )

        handled = bridge.run_once()

        self.assertEqual(handled, 1)
        self.assertEqual(self.server.acked, ["job-5"])
        self.assertEqual(list((self.dir / "printed").glob("*.escpos")), [])

    def test_invalid_payload_is_reported_not_printed(self) -> None:
        self.server.jobs = [{"id": "job-6", "status": "pending"}]

        # Payload base64 invalide : le serveur renvoie un flux corrompu.
        original = base64.b64encode

        def broken(data: bytes) -> bytes:
            return b"!!!not-base64!!!"

        base64.b64encode = broken  # type: ignore[assignment]
        try:
            handled = self._bridge().run_once()
        finally:
            base64.b64encode = original  # type: ignore[assignment]

        self.assertEqual(handled, 0)
        self.assertEqual(self.server.acked, [])
        self.assertIn("invalid_payload", self.server.failed.get("job-6", ""))

    def test_unauthorized_token_is_rejected(self) -> None:
        client = BridgeClient(self.base_url, "wrong-token", COMPANY)

        with self.assertRaises(Exception) as ctx:
            client.jobs(BRANCH)

        self.assertIn("401", str(ctx.exception))

    def test_printer_status_decoding(self) -> None:
        self.assertTrue(decode_printer_status(b"\x00")["ok"])
        self.assertIn("paper_out", decode_printer_status(b"\x10")["flags"])
        self.assertIn("cover_open", decode_printer_status(b"\x02")["flags"])
        self.assertFalse(decode_printer_status(b"")["ok"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
