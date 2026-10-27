#!/usr/bin/env python3
"""Leopardo — bridge d'impression cuisine local (ESC/POS).

Issue #8606 (REST-OS-4). Le navigateur ne garantit pas l'impression silencieuse :
ce daemon tourne sur le poste cuisine (mini-PC / Raspberry Pi / service systemd),
récupère les travaux d'impression via l'API du bridge REST-OS-3 et les remet à une
imprimante thermique ESC/POS (58/80 mm) en USB, réseau (port 9100) ou fichier
(simulation / tests).

Cycle de vie (identique au contrat serveur, cf.
`RestaurantPrintBridgeController`) :

    GET  /restaurant/printing/bridge/jobs?branch_id=…   → travaux réclamables
    POST /restaurant/printing/bridge/jobs/{id}/claim    → flux ESC/POS (base64)
    POST /restaurant/printing/bridge/jobs/{id}/ack      → impression RÉELLE
    POST /restaurant/printing/bridge/jobs/{id}/fail     → échec + motif

Garanties :
  * **Aucune impression supposée** : l'`ack` n'est envoyé qu'après un envoi
    réussi au transport imprimante.
  * **Pas de double impression après crash** : avant l'`ack`, l'identifiant du
    travail est écrit dans un journal local (`--state-file`). Au redémarrage, un
    travail déjà imprimé est acquitté sans être réimprimé.
  * **Reprise sur erreur** : erreurs transport → nouvelles tentatives avec
    backoff exponentiel, puis `fail` (le serveur remet le travail en file).
  * **Statut imprimante** : interrogation temps réel ESC/POS (`DLE EOT`) quand
    le transport le permet ; le statut est journalisé et exposé dans le motif
    d'échec.

Le jeton du bridge n'est JAMAIS passé en argument de ligne de commande (visible
dans `ps`) : il est lu depuis l'environnement ou un fichier.
"""

from __future__ import annotations

import argparse
import base64
import binascii
import json
import logging
import os
import socket
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Protocol

LOG = logging.getLogger("leopardo.print-bridge")

# ── ESC/POS ──────────────────────────────────────────────────────────────────
ESC = b"\x1b"
GS = b"\x1d"
DLE = b"\x10"
EOT = b"\x04"

#: Réponses `DLE EOT n` (temps réel) — bitmask ESC/POS.
PRINTER_STATUS_BITS: dict[int, str] = {
    0: "printer_offline",
    1: "cover_open",
    2: "paper_feed_button",
    3: "paper_jam",
    4: "paper_out",
    5: "error",
    6: "reserved",
    7: "reserved",
}


def decode_printer_status(raw: bytes) -> dict[str, Any]:
    """Décode un octet de statut ESC/POS en drapeaux lisibles."""
    if not raw:
        return {"ok": False, "flags": ["no_response"], "raw": None}

    value = raw[0]
    flags = [name for bit, name in PRINTER_STATUS_BITS.items() if value & (1 << bit)]

    return {
        "ok": value == 0,
        "flags": flags,
        "raw": value,
    }


class PrinterError(RuntimeError):
    """Erreur d'impression (transport, papier, hors ligne)."""


class Transport(Protocol):
    """Contrat minimal d'un transport d'impression."""

    name: str

    def send(self, payload: bytes) -> None: ...

    def status(self) -> dict[str, Any]: ...


@dataclass
class FileTransport:
    """Transport de simulation : écrit le flux ESC/POS dans un fichier.

    Utilisé par les tests automatisés et pour valider une installation sans
    matériel. Chaque travail produit un fichier horodaté dans `directory`.
    """

    directory: Path
    name: str = "file"

    def __post_init__(self) -> None:
        self.directory = Path(self.directory)
        self.directory.mkdir(parents=True, exist_ok=True)

    def send(self, payload: bytes) -> None:
        stamp = time.strftime("%Y%m%dT%H%M%S", time.gmtime())
        target = self.directory / f"ticket-{stamp}-{os.getpid()}.escpos"
        target.write_bytes(payload)
        LOG.info("flux ESC/POS écrit (%d octets) → %s", len(payload), target)

    def status(self) -> dict[str, Any]:
        return {"ok": True, "flags": [], "raw": 0, "transport": self.name}


@dataclass
class TcpTransport:
    """Transport réseau : imprimante ESC/POS « raw » sur le port 9100."""

    host: str
    port: int = 9100
    timeout: float = 5.0
    name: str = "tcp"

    def send(self, payload: bytes) -> None:
        try:
            with socket.create_connection((self.host, self.port), timeout=self.timeout) as sock:
                sock.sendall(payload)
                sock.shutdown(socket.SHUT_WR)
        except OSError as exc:  # réseau, DNS, refus de connexion
            raise PrinterError(f"tcp_send_failed: {exc}") from exc

    def status(self) -> dict[str, Any]:
        try:
            with socket.create_connection((self.host, self.port), timeout=self.timeout) as sock:
                sock.sendall(DLE + EOT + b"\x01")
                sock.settimeout(self.timeout)
                raw = sock.recv(1)
        except OSError as exc:
            return {"ok": False, "flags": ["unreachable"], "raw": None, "error": str(exc)}

        decoded = decode_printer_status(raw)
        decoded["transport"] = self.name
        return decoded


@dataclass
class UsbTransport:
    """Transport USB : périphérique caractère `/dev/usb/lp*` (Linux)."""

    device: str = "/dev/usb/lp0"
    name: str = "usb"

    def send(self, payload: bytes) -> None:
        try:
            with open(self.device, "wb", buffering=0) as handle:
                handle.write(payload)
        except OSError as exc:
            raise PrinterError(f"usb_send_failed: {exc}") from exc

    def status(self) -> dict[str, Any]:
        try:
            with open(self.device, "rb+", buffering=0) as handle:
                handle.write(DLE + EOT + b"\x01")
                raw = handle.read(1)
        except OSError as exc:
            return {"ok": False, "flags": ["unreachable"], "raw": None, "error": str(exc)}

        decoded = decode_printer_status(raw)
        decoded["transport"] = self.name
        return decoded


def build_transport(kind: str, target: str) -> Transport:
    """Construit le transport demandé (`file`, `tcp`, `usb`)."""
    if kind == "file":
        return FileTransport(Path(target))
    if kind == "tcp":
        host, _, port = target.partition(":")
        return TcpTransport(host=host, port=int(port or 9100))
    if kind == "usb":
        return UsbTransport(device=target or "/dev/usb/lp0")
    raise ValueError(f"transport inconnu: {kind}")


# ── Client API ───────────────────────────────────────────────────────────────
class BridgeApiError(RuntimeError):
    """Réponse API inattendue (hors 2xx)."""

    def __init__(self, status: int, body: str) -> None:
        super().__init__(f"http_{status}: {body[:200]}")
        self.status = status
        self.body = body


@dataclass
class BridgeClient:
    """Client HTTP minimal du bridge d'impression (urllib, zéro dépendance)."""

    base_url: str
    token: str
    company_id: str
    timeout: float = 15.0

    def _request(self, method: str, path: str, body: dict[str, Any] | None = None) -> Any:
        url = self.base_url.rstrip("/") + path
        data = None
        headers = {
            "Authorization": f"Bearer {self.token}",
            "X-Company-Id": self.company_id,
            "Accept": "application/json",
        }

        if body is not None:
            data = json.dumps(body).encode("utf-8")
            headers["Content-Type"] = "application/json"

        request = urllib.request.Request(url, data=data, headers=headers, method=method)

        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                raw = response.read()
        except urllib.error.HTTPError as exc:
            raise BridgeApiError(exc.code, exc.read().decode("utf-8", "replace")) from exc
        except urllib.error.URLError as exc:
            raise BridgeApiError(0, f"network_error: {exc.reason}") from exc

        if not raw:
            return None

        return json.loads(raw.decode("utf-8"))

    def jobs(self, branch_id: str) -> list[dict[str, Any]]:
        query = urllib.parse.urlencode({"branch_id": branch_id})
        payload = self._request("GET", f"/restaurant/printing/bridge/jobs?{query}")

        if isinstance(payload, dict):
            data = payload.get("data", payload)
            if isinstance(data, list):
                return data
            if isinstance(data, dict) and isinstance(data.get("data"), list):
                return data["data"]

        return []

    def claim(self, job_id: str, printer_ref: str, paper_width: int) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/restaurant/printing/bridge/jobs/{job_id}/claim",
            {"printer_ref": printer_ref, "paper_width": paper_width},
        )

    def ack(self, job_id: str, printer_ref: str) -> Any:
        return self._request(
            "POST",
            f"/restaurant/printing/bridge/jobs/{job_id}/ack",
            {"printer_ref": printer_ref},
        )

    def fail(self, job_id: str, error: str) -> Any:
        return self._request(
            "POST",
            f"/restaurant/printing/bridge/jobs/{job_id}/fail",
            {"error": error[:255]},
        )


# ── Journal local (anti double impression) ───────────────────────────────────
@dataclass
class PrintJournal:
    """Journal local des travaux déjà imprimés (survit à un redémarrage)."""

    path: Path
    printed: set[str] = field(default_factory=set)

    def __post_init__(self) -> None:
        self.path = Path(self.path)
        if self.path.exists():
            try:
                data = json.loads(self.path.read_text("utf-8"))
                self.printed = set(data.get("printed", []))
            except (json.JSONDecodeError, OSError):
                LOG.warning("journal illisible, réinitialisation: %s", self.path)

    def mark_printed(self, job_id: str) -> None:
        self.printed.add(job_id)
        self._flush()

    def forget(self, job_id: str) -> None:
        if job_id in self.printed:
            self.printed.discard(job_id)
            self._flush()

    def _flush(self) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps({"printed": sorted(self.printed)}), "utf-8")
        tmp.replace(self.path)


# ── Daemon ───────────────────────────────────────────────────────────────────
@dataclass
class BridgeConfig:
    base_url: str
    token: str
    company_id: str
    branch_id: str
    printer_ref: str = "kitchen"
    paper_width: int = 80
    poll_interval: float = 3.0
    max_attempts: int = 3
    retry_backoff: float = 1.0
    state_file: Path = Path("print-bridge-state.json")
    once: bool = False


class PrintBridge:
    """Boucle de récupération / impression / acquittement."""

    def __init__(
        self,
        config: BridgeConfig,
        client: BridgeClient,
        transport: Transport,
        journal: PrintJournal,
    ) -> None:
        self.config = config
        self.client = client
        self.transport = transport
        self.journal = journal

    # -- une passe -----------------------------------------------------------
    def run_once(self) -> int:
        """Traite la file une fois. Retourne le nombre de travaux traités."""
        try:
            jobs = self.client.jobs(self.config.branch_id)
        except BridgeApiError as exc:
            LOG.error("récupération de la file impossible: %s", exc)
            return 0

        handled = 0

        for job in jobs:
            job_id = str(job.get("id", ""))
            if job_id == "":
                continue

            if self._process(job_id):
                handled += 1

        return handled

    def _process(self, job_id: str) -> bool:
        # Reprise après crash : le travail a été imprimé mais pas acquitté.
        if job_id in self.journal.printed:
            LOG.warning("travail %s déjà imprimé — acquittement sans réimpression", job_id)
            try:
                self.client.ack(job_id, self.config.printer_ref)
                self.journal.forget(job_id)
                return True
            except BridgeApiError as exc:
                LOG.error("acquittement de reprise impossible (%s): %s", job_id, exc)
                return False

        try:
            claimed = self.client.claim(job_id, self.config.printer_ref, self.config.paper_width)
        except BridgeApiError as exc:
            if exc.status == 409:
                LOG.info("travail %s déjà réclamé par un autre terminal", job_id)
                return False
            LOG.error("réclamation de %s impossible: %s", job_id, exc)
            return False

        payload = self._decode_payload(claimed)

        if payload is None:
            self._report_failure(job_id, "invalid_payload")
            return False

        if not self._print_with_retries(job_id, payload):
            return False

        # Marquer AVANT l'ack : un crash entre les deux ne provoque pas de
        # réimpression (le travail sera acquitté au redémarrage).
        self.journal.mark_printed(job_id)

        try:
            self.client.ack(job_id, self.config.printer_ref)
        except BridgeApiError as exc:
            LOG.error("impression faite mais ack refusé (%s): %s", job_id, exc)
            return False

        self.journal.forget(job_id)
        LOG.info("travail %s imprimé et acquitté", job_id)
        return True

    @staticmethod
    def _decode_payload(claimed: Any) -> bytes | None:
        if not isinstance(claimed, dict):
            return None

        encoded = claimed.get("payload_base64")
        if not isinstance(encoded, str) or encoded == "":
            return None

        try:
            return base64.b64decode(encoded, validate=True)
        except (binascii.Error, ValueError):
            return None

    def _print_with_retries(self, job_id: str, payload: bytes) -> bool:
        last_error = "unknown"

        for attempt in range(1, self.config.max_attempts + 1):
            try:
                self.transport.send(payload)
                return True
            except PrinterError as exc:
                last_error = str(exc)
                LOG.warning(
                    "impression %s échouée (tentative %d/%d): %s",
                    job_id,
                    attempt,
                    self.config.max_attempts,
                    exc,
                )
                if attempt < self.config.max_attempts:
                    time.sleep(self.config.retry_backoff * (2 ** (attempt - 1)))

        status = self.transport.status()
        self._report_failure(job_id, f"{last_error}; printer={status.get('flags')}")
        return False

    def _report_failure(self, job_id: str, reason: str) -> None:
        try:
            self.client.fail(job_id, reason)
        except BridgeApiError as exc:
            LOG.error("signalement d'échec refusé (%s): %s", job_id, exc)

    # -- boucle --------------------------------------------------------------
    def run_forever(self) -> None:
        LOG.info(
            "bridge démarré (branche=%s, imprimante=%s, transport=%s)",
            self.config.branch_id,
            self.config.printer_ref,
            self.transport.name,
        )

        while True:
            try:
                self.run_once()
            except Exception:  # noqa: BLE001 — le daemon ne doit jamais mourir
                LOG.exception("passe en erreur, poursuite après temporisation")

            if self.config.once:
                return

            time.sleep(self.config.poll_interval)


# ── CLI ──────────────────────────────────────────────────────────────────────
def _read_token(args: argparse.Namespace) -> str:
    if args.token_file:
        return Path(args.token_file).read_text("utf-8").strip()

    token = os.environ.get("RESTAURANT_PRINT_BRIDGE_TOKEN", "").strip()

    if token == "":
        raise SystemExit(
            "Jeton du bridge absent : définir RESTAURANT_PRINT_BRIDGE_TOKEN "
            "ou passer --token-file (jamais --token, visible dans ps)."
        )

    return token


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Leopardo — bridge d'impression cuisine ESC/POS")
    parser.add_argument("--api-url", default=os.environ.get("LEOPARDO_API_URL", "http://localhost:8000/api/v1"))
    parser.add_argument("--company-id", default=os.environ.get("LEOPARDO_COMPANY_ID", ""))
    parser.add_argument("--branch-id", default=os.environ.get("LEOPARDO_BRANCH_ID", ""))
    parser.add_argument("--printer-ref", default=os.environ.get("PRINTER_REF", "kitchen"))
    parser.add_argument("--paper-width", type=int, default=int(os.environ.get("PRINTER_PAPER_WIDTH", "80")))
    parser.add_argument("--transport", default=os.environ.get("PRINTER_TRANSPORT", "file"),
                        choices=["file", "tcp", "usb"])
    parser.add_argument("--target", default=os.environ.get("PRINTER_TARGET", "./printed"))
    parser.add_argument("--poll-interval", type=float, default=float(os.environ.get("POLL_INTERVAL", "3")))
    parser.add_argument("--max-attempts", type=int, default=int(os.environ.get("MAX_ATTEMPTS", "3")))
    parser.add_argument("--state-file", default=os.environ.get("STATE_FILE", "print-bridge-state.json"))
    parser.add_argument("--token-file", default=os.environ.get("RESTAURANT_PRINT_BRIDGE_TOKEN_FILE"))
    parser.add_argument("--once", action="store_true", help="une seule passe puis sortie")
    parser.add_argument("--check", action="store_true", help="vérifie l'imprimante et sort")
    parser.add_argument("--verbose", action="store_true")
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s — %(message)s",
    )

    transport = build_transport(args.transport, args.target)

    if args.check:
        status = transport.status()
        print(json.dumps(status, indent=2))
        return 0 if status.get("ok") else 1

    if not args.company_id or not args.branch_id:
        raise SystemExit("--company-id et --branch-id sont requis")

    config = BridgeConfig(
        base_url=args.api_url,
        token=_read_token(args),
        company_id=args.company_id,
        branch_id=args.branch_id,
        printer_ref=args.printer_ref,
        paper_width=args.paper_width,
        poll_interval=args.poll_interval,
        max_attempts=args.max_attempts,
        state_file=Path(args.state_file),
        once=args.once,
    )

    client = BridgeClient(
        base_url=config.base_url,
        token=config.token,
        company_id=config.company_id,
    )

    PrintBridge(config, client, transport, PrintJournal(config.state_file)).run_forever()
    return 0


if __name__ == "__main__":
    sys.exit(main())
