from __future__ import annotations

import hmac
import json
import secrets
import sqlite3
import threading
import time
import urllib.error
import urllib.request
import uuid
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parents[1]
DATA_DIR = ROOT / "desktop-bridge" / "data"
DATA_DIR.mkdir(parents=True, exist_ok=True)
# Attendance data contains PII: never inherit a world-readable umask.
DATA_DIR.chmod(0o700)
DB_PATH = DATA_DIR / "kiosk.db"
CONFIG_PATH = ROOT / "config.json"


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def load_config() -> dict:
    if CONFIG_PATH.exists():
        return json.loads(CONFIG_PATH.read_text(encoding="utf-8"))

    example = ROOT / "config.example.json"
    if example.exists():
        return json.loads(example.read_text(encoding="utf-8"))

    return {}


CONFIG = load_config()

# Issue #3586 — le bridge écoute sans aucune authentification : n'importe quel
# poste du LAN pouvait lire config.json (token kiosk) et kiosk.db (PII), ou
# forger des pointages via POST /local/punch cross-origin. Un token de session
# local est généré à chaque boot, injecté dans les pages HTML servies par le
# bridge (window.__LOCAL_BRIDGE_TOKEN) et exigé sur tous les endpoints /local/*
# via le header X-Local-Bridge-Token.
LOCAL_BRIDGE_TOKEN = secrets.token_urlsafe(32)
LOCAL_TOKEN_HEADER = "X-Local-Bridge-Token"
MAX_JSON_BODY_BYTES = 64 * 1024
PUNCH_RATE_LIMIT = 60
PUNCH_RATE_WINDOW_SECONDS = 60.0
_PUNCH_RATE_LOCK = threading.Lock()
_PUNCH_RATE_BUCKETS: dict[str, list[float]] = {}


def allow_local_punch(client_ip: str, now: float | None = None) -> bool:
    """Allow at most 60 local punches per IP in a rolling 60-second window."""
    current = time.monotonic() if now is None else now
    cutoff = current - PUNCH_RATE_WINDOW_SECONDS
    with _PUNCH_RATE_LOCK:
        timestamps = [t for t in _PUNCH_RATE_BUCKETS.get(client_ip, []) if t > cutoff]
        if len(timestamps) >= PUNCH_RATE_LIMIT:
            _PUNCH_RATE_BUCKETS[client_ip] = timestamps
            return False
        timestamps.append(current)
        _PUNCH_RATE_BUCKETS[client_ip] = timestamps
        return True


class PayloadTooLargeError(ValueError):
    pass

# Allowlist statique stricte : seuls les assets de l'UI kiosk sont servis.
# config.json (token), config.example.json, *.db (PII), *.py, package*.json,
# desktop-bridge/** ne doivent JAMAIS être exposés.
ALLOWED_STATIC_FILES = frozenset({
    "index.html",
    "admin.html",
    "app.js",
    "admin.js",
    "i18n.js",
})

# Enums miroir du contrat serveur (KioskController::sync, #3588) : valider à
# l'insertion évite qu'un événement « poison » bloque la file offline.
VALID_ACTIONS = frozenset({"check_in", "check_out"})
VALID_BIOMETRIC_TYPES = frozenset({"fingerprint", "face", "mixed"})
# #5121 — méthodes de pointage (nouveau contrat, inclut la carte)
VALID_PUNCH_METHODS = frozenset({"fingerprint", "face", "card"})

# Politique de retry de la sync (#3588) : 5xx/réseau = transitoire (backoff
# exponentiel borné), 4xx = permanent (dead-letter). Au-delà du cap, un
# événement irrécupérable est isolé en dead_letter au lieu de bloquer la file.
MAX_SYNC_ATTEMPTS = 10
RETRY_BASE_SECONDS = 15
RETRY_MAX_SECONDS = 900


class LocalStore:
    def __init__(self, path: Path) -> None:
        self.path = path
        if isinstance(path, Path) and path.exists():
            path.chmod(0o600)
        self.conn = sqlite3.connect(path, check_same_thread=False)
        if isinstance(path, Path):
            path.chmod(0o600)
        self.conn.row_factory = sqlite3.Row
        self._init_schema()

    def _init_schema(self) -> None:
        self.conn.executescript(
            """
            create table if not exists roster_cache (
                employee_id integer primary key,
                name text not null,
                email text,
                matricule text,
                zkteco_id text,
                face_enabled integer not null default 0,
                fingerprint_enabled integer not null default 0,
                raw_json text not null
            );

            create table if not exists punch_queue (
                id integer primary key autoincrement,
                external_event_id text not null unique,
                identifier text not null,
                action text not null,
                biometric_type text,
                occurred_at text not null,
                sync_status text not null default 'queued',
                error_message text,
                remote_response text,
                created_at text not null,
                synced_at text
            );

            create table if not exists sync_state (
                key text primary key,
                value text
            );
            """
        )
        # Migration douce (#3588) : les bases existantes gagnent les colonnes
        # de retry/dead-letter sans perdre la file en cours.
        columns = {
            row["name"] for row in self.conn.execute("pragma table_info(punch_queue)").fetchall()
        }
        if "retry_count" not in columns:
            self.conn.execute("alter table punch_queue add column retry_count integer not null default 0")
        if "next_retry_at" not in columns:
            self.conn.execute("alter table punch_queue add column next_retry_at text")
        # #5121 — méthode de pointage (fingerprint|face|card) et badge_number (carte)
        if "method" not in columns:
            self.conn.execute("alter table punch_queue add column method text")
        if "badge_number" not in columns:
            self.conn.execute("alter table punch_queue add column badge_number text")
        self.conn.commit()

    def upsert_roster(self, employees: list[dict]) -> None:
        with self.conn:
            for employee in employees:
                self.conn.execute(
                    """
                    insert into roster_cache (
                        employee_id, name, email, matricule, zkteco_id,
                        face_enabled, fingerprint_enabled, raw_json
                    ) values (?, ?, ?, ?, ?, ?, ?, ?)
                    on conflict(employee_id) do update set
                        name = excluded.name,
                        email = excluded.email,
                        matricule = excluded.matricule,
                        zkteco_id = excluded.zkteco_id,
                        face_enabled = excluded.face_enabled,
                        fingerprint_enabled = excluded.fingerprint_enabled,
                        raw_json = excluded.raw_json
                    """,
                    (
                        employee.get("employee_id"),
                        employee.get("name"),
                        employee.get("email"),
                        employee.get("matricule"),
                        employee.get("zkteco_id"),
                        1 if employee.get("face_enabled") else 0,
                        1 if employee.get("fingerprint_enabled") else 0,
                        json.dumps(employee, ensure_ascii=False),
                    ),
                )

    def queue_punch(
        self,
        identifier: str,
        action: str,
        biometric_type: str,
        method: str | None = None,
        badge_number: str | None = None,
    ) -> dict:
        """Enregistre un pointage dans la file offline.

        #5121 — `method` (fingerprint|face|card) est le nouveau champ de méthode
        de pointage transmis à l'API. `badge_number` est requis pour method=card.
        """
        payload = {
            "external_event_id": str(uuid.uuid4()),
            "identifier": identifier,
            "action": action,
            "biometric_type": biometric_type,
            "method": method or biometric_type,  # fallback rétro-compat
            "badge_number": badge_number,
            "occurred_at": utc_now_iso(),
            "created_at": utc_now_iso(),
        }
        with self.conn:
            self.conn.execute(
                """
                insert into punch_queue (
                    external_event_id, identifier, action, biometric_type,
                    method, badge_number, occurred_at, created_at
                ) values (?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    payload["external_event_id"],
                    payload["identifier"],
                    payload["action"],
                    payload["biometric_type"],
                    payload["method"],
                    payload["badge_number"],
                    payload["occurred_at"],
                    payload["created_at"],
                ),
            )
        return payload

    def queued_events(self, limit: int = 200) -> list[dict]:
        # Ne sélectionne que les événements éligibles : le backoff (#3588)
        # reporte les événements en échec transitoire via next_retry_at.
        rows = self.conn.execute(
            """
            select * from punch_queue
            where sync_status = 'queued'
              and (next_retry_at is null or next_retry_at <= ?)
            order by id asc
            limit ?
            """,
            (utc_now_iso(), limit),
        ).fetchall()
        return [dict(row) for row in rows]

    def all_events(self, limit: int = 200) -> list[dict]:
        rows = self.conn.execute(
            "select * from punch_queue order by id desc limit ?",
            (limit,),
        ).fetchall()
        return [dict(row) for row in rows]

    def mark_synced(self, external_event_ids: list[str], remote_response: str) -> None:
        with self.conn:
            for event_id in external_event_ids:
                self.conn.execute(
                    """
                    update punch_queue
                    set sync_status = 'synced',
                        remote_response = ?,
                        synced_at = ?
                    where external_event_id = ?
                    """,
                    (remote_response, utc_now_iso(), event_id),
                )

    def mark_error(self, external_event_ids: list[str], error_message: str) -> None:
        with self.conn:
            for event_id in external_event_ids:
                self.conn.execute(
                    """
                    update punch_queue
                    set error_message = ?
                    where external_event_id = ?
                    """,
                    (error_message, event_id),
                )

    def mark_retry(self, external_event_ids: list[str], error_message: str) -> list[str]:
        """Echec transitoire (#3588) : backoff exponentiel, puis dead-letter au cap.

        Retourne la liste des événements basculés en dead_letter (cap atteint).
        """
        dead: list[str] = []
        with self.conn:
            for event_id in external_event_ids:
                row = self.conn.execute(
                    "select retry_count from punch_queue where external_event_id = ?",
                    (event_id,),
                ).fetchone()
                attempts = int(row["retry_count"]) + 1 if row else 1
                if attempts >= MAX_SYNC_ATTEMPTS:
                    self.conn.execute(
                        """
                        update punch_queue
                        set sync_status = 'dead_letter',
                            retry_count = ?,
                            error_message = ?,
                            next_retry_at = null
                        where external_event_id = ?
                        """,
                        (attempts, f"MAX_RETRY_EXCEEDED: {error_message}", event_id),
                    )
                    dead.append(event_id)
                    continue
                delay = min(RETRY_BASE_SECONDS * (2 ** (attempts - 1)), RETRY_MAX_SECONDS)
                next_retry = datetime.fromtimestamp(time.time() + delay, tz=timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")
                self.conn.execute(
                    """
                    update punch_queue
                    set retry_count = ?,
                        error_message = ?,
                        next_retry_at = ?
                    where external_event_id = ?
                    """,
                    (attempts, error_message, next_retry, event_id),
                )
        return dead

    def mark_dead_letter(self, external_event_ids: list[str], reason: str) -> None:
        """Rejet permanent (#3587/#3588) : isolé, jamais marqué synced, réparable."""
        with self.conn:
            for event_id in external_event_ids:
                self.conn.execute(
                    """
                    update punch_queue
                    set sync_status = 'dead_letter',
                        error_message = ?,
                        next_retry_at = null
                    where external_event_id = ?
                    """,
                    (reason, event_id),
                )

    def requeue_event(self, external_event_id: str) -> bool:
        """Réparation ops (#3588) : un dead_letter/erreur repart en file."""
        with self.conn:
            cursor = self.conn.execute(
                """
                update punch_queue
                set sync_status = 'queued',
                    retry_count = 0,
                    next_retry_at = null,
                    error_message = null
                where external_event_id = ? and sync_status != 'synced'
                """,
                (external_event_id,),
            )
        return cursor.rowcount > 0

    def dead_letter_count(self) -> int:
        row = self.conn.execute(
            "select count(*) as count from punch_queue where sync_status = 'dead_letter'"
        ).fetchone()
        return int(row["count"])

    def set_state(self, key: str, value: str) -> None:
        with self.conn:
            self.conn.execute(
                """
                insert into sync_state(key, value) values (?, ?)
                on conflict(key) do update set value = excluded.value
                """,
                (key, value),
            )

    def get_state(self, key: str, default: str | None = None) -> str | None:
        row = self.conn.execute("select value from sync_state where key = ?", (key,)).fetchone()
        return row["value"] if row else default

    def roster(self) -> list[dict]:
        rows = self.conn.execute("select raw_json from roster_cache order by name asc").fetchall()
        return [json.loads(row["raw_json"]) for row in rows]

    def queue_count(self) -> int:
        row = self.conn.execute("select count(*) as count from punch_queue where sync_status = 'queued'").fetchone()
        return int(row["count"])


STORE = LocalStore(DB_PATH)


class SyncEngine:
    def __init__(self, config: dict, store: LocalStore) -> None:
        self.config = config
        self.store = store
        # Normalisation miroir de app.js (issue #3590) : une config sans
        # suffixe /api/v1 fonctionne pour l'UI mais 404 toute la sync bridge.
        base = config.get("apiBaseUrl", "").rstrip("/")
        if base and not base.endswith("/api/v1"):
            base = f"{base}/api/v1"
        self.api_base_url = base
        self.device_code = config.get("deviceCode", "")
        self.kiosk_token = config.get("kioskToken", "")

    def _request(self, method: str, path: str, payload: dict | None = None) -> dict:
        body = json.dumps(payload or {}).encode("utf-8")
        request = urllib.request.Request(
            f"{self.api_base_url}{path}",
            method=method,
            data=body if method != "GET" else None,
            headers={
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-Kiosk-Token": self.kiosk_token,
            },
        )
        with urllib.request.urlopen(request, timeout=15) as response:
            return json.loads(response.read().decode("utf-8"))

    def download_roster(self) -> dict:
        payload = self._request("GET", f"/kiosks/{self.device_code}/roster")
        employees = payload.get("data", {}).get("employees", [])
        self.store.upsert_roster(employees)
        self.store.set_state("last_sync_error", "")
        self.store.set_state("last_roster_sync_at", utc_now_iso())
        return {"employees_count": len(employees)}

    def upload_events(self) -> dict:
        events = self.store.queued_events()
        if not events:
            return {"processed_count": 0, "message": "Aucun evenement a synchroniser"}
        return self._upload_batch(events, allow_poison_retry=True)

    def _upload_batch(self, events: list[dict], allow_poison_retry: bool) -> dict:
        api_payload = {
            "events": [
                {
                    "identifier": event["identifier"],
                    "action": event["action"],
                    "occurred_at": event["occurred_at"],
                    "external_event_id": event["external_event_id"],
                    "biometric_type": event["biometric_type"],
                    # #5121 — méthode de pointage + badge pour flux carte
                    **({"method": event["method"]} if event.get("method") else {}),
                    **({"badge_number": event["badge_number"]} if event.get("badge_number") else {}),
                }
                for event in events
            ]
        }

        try:
            payload = self._request("POST", f"/kiosks/{self.device_code}/sync", api_payload)
        except urllib.error.HTTPError as error:
            return self._handle_sync_http_error(events, error, allow_poison_retry)
        except Exception as error:
            # 5xx applicatif non HTTP ou erreur réseau : transitoire → backoff.
            event_ids = [event["external_event_id"] for event in events]
            self.store.mark_retry(event_ids, str(error))
            self.store.set_state("last_sync_error", str(error))
            raise

        data = payload.get("data", {})
        processed = data.get("processed_count", 0)
        skipped = data.get("skipped")

        if isinstance(skipped, list):
            # Contrat #3587 : le serveur détaille les événements refusés
            # (identifiant inconnu, biométrie non approuvée...). Ils sont
            # isolés en dead_letter avec la raison — jamais marqués synced.
            skipped_by_id = {
                str(item.get("external_event_id")): str(item.get("reason", "SKIPPED"))
                for item in skipped
                if isinstance(item, dict) and item.get("external_event_id")
            }
            synced_ids = [
                event["external_event_id"]
                for event in events
                if event["external_event_id"] not in skipped_by_id
            ]
            if synced_ids:
                self.store.mark_synced(synced_ids, json.dumps(data, ensure_ascii=False))
            for event in events:
                reason = skipped_by_id.get(event["external_event_id"])
                if reason:
                    self.store.mark_dead_letter([event["external_event_id"]], reason)
        elif processed < len(events):
            # Serveur legacy sans détail : NE PAS marquer synced en aveugle
            # (#3587 — perte silencieuse historique). Les événements restent
            # en file avec un warning exploitable ; le cap de retries borne
            # la rétention (les pointages valides sont idempotents côté API).
            event_ids = [event["external_event_id"] for event in events]
            warning = (
                f"SYNC_PARTIAL_WITHOUT_DETAIL: processed_count={processed} "
                f"sur {len(events)} evenements envoyes"
            )
            self.store.mark_retry(event_ids, warning)
            self.store.set_state("last_sync_error", warning)
            return {"processed_count": processed, "warning": warning}
        else:
            event_ids = [event["external_event_id"] for event in events]
            self.store.mark_synced(event_ids, json.dumps(data, ensure_ascii=False))

        self.store.set_state("last_sync_at", utc_now_iso())
        self.store.set_state("last_sync_error", "")
        result = {"processed_count": processed}
        if isinstance(skipped, list):
            result["skipped_count"] = len(skipped)
        return result

    def _handle_sync_http_error(self, events: list[dict], error: urllib.error.HTTPError, allow_poison_retry: bool) -> dict:
        body = ""
        try:
            body = error.read().decode("utf-8")
        except Exception:
            pass

        if 400 <= error.code < 500:
            # 4xx = rejet permanent (#3588) : isoler le poison au lieu de
            # bloquer la file entière en retry infini.
            poison_ids = self._poison_event_ids(events, body)
            if poison_ids:
                remaining = [e for e in events if e["external_event_id"] not in poison_ids]
                self.store.mark_dead_letter(poison_ids, f"HTTP_{error.code}: {body[:300]}")
                if remaining and allow_poison_retry:
                    return self._upload_batch(remaining, allow_poison_retry=False)
                return {"processed_count": 0, "dead_lettered": len(poison_ids)}
            if len(events) == 1:
                self.store.mark_dead_letter(
                    [events[0]["external_event_id"]], f"HTTP_{error.code}: {body[:300]}"
                )
                return {"processed_count": 0, "dead_lettered": 1}
            # 4xx multi-événements non analysable : transitoire côté file,
            # le cap de retries finira par isoler le batch.
            event_ids = [event["external_event_id"] for event in events]
            self.store.mark_retry(event_ids, f"HTTP_{error.code}: {body[:300]}")
            self.store.set_state("last_sync_error", f"HTTP_{error.code}")
            raise error

        # 5xx : transitoire → backoff.
        event_ids = [event["external_event_id"] for event in events]
        self.store.mark_retry(event_ids, f"HTTP_{error.code}: {body[:300]}")
        self.store.set_state("last_sync_error", f"HTTP_{error.code}")
        raise error

    @staticmethod
    def _poison_event_ids(events: list[dict], body: str) -> list[str]:
        """Extrait les événements fautifs des erreurs de validation Laravel.

        Le contrat 422 Laravel indexe les erreurs par `events.<i>.<field>` ;
        on mappe l'index sur l'external_event_id du batch envoyé.
        """
        try:
            payload = json.loads(body) if body else {}
        except json.JSONDecodeError:
            return []
        errors = payload.get("errors")
        if not isinstance(errors, dict):
            return []
        poison_indexes: set[int] = set()
        for key in errors:
            parts = str(key).split(".")
            if len(parts) >= 2 and parts[0] == "events" and parts[1].isdigit():
                poison_indexes.add(int(parts[1]))
        return [events[i]["external_event_id"] for i in sorted(poison_indexes) if i < len(events)]

    def sync_all(self) -> dict:
        roster = self.download_roster()
        uploaded = self.upload_events()
        return {
            "roster": roster,
            "events": uploaded,
        }

    def online_status(self) -> tuple[bool, str]:
        # Issue #3590 : la sonde de connectivité ne doit pas télécharger le
        # roster complet (pollé toutes les 15 s par l'UI) — /health suffit.
        try:
            self._request("GET", "/health")
            return True, ""
        except Exception as error:
            return False, str(error)


SYNC_ENGINE = SyncEngine(CONFIG, STORE)


class BridgeHandler(BaseHTTPRequestHandler):
    def _json(self, status: int, payload: dict) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_json(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        if length > MAX_JSON_BODY_BYTES:
            raise PayloadTooLargeError("REQUEST_BODY_TOO_LARGE")
        if length <= 0:
            return {}
        raw = self.rfile.read(length)
        return json.loads(raw.decode("utf-8"))

    def _is_authorized_local(self) -> bool:
        """Token de session local exigé sur /local/* (#3586).

        Le token est injecté dans les pages servies par le bridge ; comparaison
        en temps constant pour éviter toute oracle temporelle.
        """
        provided = self.headers.get(LOCAL_TOKEN_HEADER, "")
        return bool(provided) and hmac.compare_digest(provided, LOCAL_BRIDGE_TOKEN)

    def _is_same_origin(self) -> bool:
        """Anti-CSRF (#3586) : un Origin cross-site ne peut pas forger de punch.

        Les requêtes same-origin du kiosk n'envoient pas toujours Origin (GET) ;
        quand il est présent (fetch POST), son host doit égaler celui du bridge.
        """
        origin = self.headers.get("Origin")
        if not origin:
            return True
        origin_host = urlparse(origin).netloc
        return bool(origin_host) and origin_host == self.headers.get("Host", "")

    def _guard_local(self) -> bool:
        """Garde commune /local/* : répond et retourne False si refusé."""
        if not self._is_authorized_local():
            return self._json(401, {"error": "LOCAL_TOKEN_REQUIRED"}) or False
        if not self._is_same_origin():
            return self._json(403, {"error": "ORIGIN_FORBIDDEN"}) or False
        return True

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path.startswith("/local/") and not self._guard_local():
            return

        if parsed.path == "/local/status":
            online, error_message = SYNC_ENGINE.online_status()
            # #5120 — punch_methods depuis la config (null = toutes méthodes)
            punch_methods = CONFIG.get("punch_methods")
            payload = {
                "data": {
                    "company_name": CONFIG.get("companyName", "Leopardo RH Client"),
                    "location_label": CONFIG.get("locationLabel", "Entree principale"),
                    "device_code": CONFIG.get("deviceCode", ""),
                    "queue_count": STORE.queue_count(),
                    "dead_letter_count": STORE.dead_letter_count(),
                    "online": online,
                    "last_error": error_message or STORE.get_state("last_sync_error", ""),
                    "last_sync_at": STORE.get_state("last_sync_at", ""),
                    "punch_methods": punch_methods if isinstance(punch_methods, list) else None,
                }
            }
            return self._json(200, payload)

        if parsed.path == "/local/events":
            return self._json(200, {"data": STORE.all_events()})

        if parsed.path == "/local/roster":
            return self._json(200, {"data": STORE.roster()})

        return self._serve_static(parsed.path)

    def do_POST(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path.startswith("/local/"):
            if not self._guard_local():
                return
            # Anti-CSRF (#3586) : un POST cross-site simple (fetch no-cors) ne
            # peut pas poser application/json → rejet systématique.
            content_type = (self.headers.get("Content-Type") or "").split(";")[0].strip().lower()
            if content_type != "application/json":
                return self._json(415, {"error": "CONTENT_TYPE_JSON_REQUIRED"})
        try:
            if parsed.path == "/local/punch":
                client_ip = self.client_address[0] if self.client_address else "unknown"
                if not allow_local_punch(client_ip):
                    return self._json(429, {"error": "LOCAL_PUNCH_RATE_LIMITED"})
                payload = self._read_json()
                identifier = str(payload.get("identifier", "")).strip()
                action = str(payload.get("action", "check_in")).strip() or "check_in"
                biometric_type = str(payload.get("biometric_type", "fingerprint")).strip() or "fingerprint"
                # #5121 — méthode de pointage (nouveau champ, rétro-compat biometric_type)
                method_raw = str(payload.get("method", biometric_type)).strip()
                method = method_raw if method_raw in VALID_PUNCH_METHODS else biometric_type
                badge_number_raw = payload.get("badge_number")
                badge_number: str | None = str(badge_number_raw).strip() if badge_number_raw else None

                # Pour method=card, l'identifiant peut être le badge_number lui-même.
                effective_identifier = identifier or (badge_number or "")
                if not effective_identifier:
                    return self._json(422, {"error": "IDENTIFIER_REQUIRED"})
                if len(effective_identifier) > 150:
                    return self._json(422, {"error": "IDENTIFIER_TOO_LONG"})
                # Validation à l'insertion (#3588) : un événement hors contrat
                # serveur ne doit jamais entrer dans la file offline.
                if action not in VALID_ACTIONS:
                    return self._json(422, {"error": "INVALID_ACTION"})
                if biometric_type not in VALID_BIOMETRIC_TYPES and method not in VALID_PUNCH_METHODS:
                    return self._json(422, {"error": "INVALID_BIOMETRIC_TYPE"})

                event = STORE.queue_punch(
                    effective_identifier, action, biometric_type,
                    method=method, badge_number=badge_number,
                )
                sync_status = "queued"

                if CONFIG.get("autoSync", True):
                    try:
                        SYNC_ENGINE.upload_events()
                        sync_status = "synced"
                    except Exception:
                        sync_status = "queued"

                return self._json(201, {"data": {**event, "sync_status": sync_status}})

            if parsed.path == "/local/events/requeue":
                payload = self._read_json()
                event_id = str(payload.get("external_event_id", "")).strip()
                if not event_id:
                    return self._json(422, {"error": "EXTERNAL_EVENT_ID_REQUIRED"})
                if not STORE.requeue_event(event_id):
                    return self._json(404, {"error": "EVENT_NOT_REQUEUEABLE"})
                return self._json(200, {"data": {"external_event_id": event_id, "sync_status": "queued"}})

            if parsed.path == "/local/sync/roster":
                return self._json(200, {"data": SYNC_ENGINE.download_roster()})

            if parsed.path == "/local/sync/events":
                return self._json(200, {"data": SYNC_ENGINE.upload_events()})

            if parsed.path == "/local/sync/all":
                return self._json(200, {"data": SYNC_ENGINE.sync_all()})

        except PayloadTooLargeError:
            return self._json(413, {"error": "REQUEST_BODY_TOO_LARGE"})
        except urllib.error.URLError as error:
            return self._json(502, {"error": f"REMOTE_UNREACHABLE: {error}"})
        except Exception as error:
            return self._json(500, {"error": str(error)})

        return self._json(404, {"error": "NOT_FOUND"})

    def _serve_static(self, path: str) -> None:
        relative = path.lstrip("/") or "index.html"

        # Allowlist stricte (#3586) : config.json (token kiosk), *.db (PII),
        # *.py et tout le reste du projet ne sont JAMAIS servis.
        if relative not in ALLOWED_STATIC_FILES:
            return self._json(404, {"error": "NOT_FOUND"})

        target = (ROOT / relative).resolve()
        try:
            target.relative_to(ROOT)
        except ValueError:
            return self._json(404, {"error": "NOT_FOUND"})
        if not target.exists() or not target.is_file():
            return self._json(404, {"error": "NOT_FOUND"})

        content_type = "text/html; charset=utf-8"
        if target.suffix == ".js":
            content_type = "application/javascript; charset=utf-8"
        elif target.suffix == ".json":
            content_type = "application/json; charset=utf-8"

        body = target.read_bytes()

        # Issue #2750 — injecter la config cloud dans les pages HTML servies :
        # `app.js` lit `window.__KIOSK_API_BASE / __KIOSK_DEVICE_CODE /
        # __KIOSK_TOKEN`. Sans injection, le device code est vide et les
        # fonctions cloud (employee-info, announcements, leave-balance,
        # qr-punch) appellent `/api/v1/kiosks//…` → 404 (déploiement
        # documenté http://127.0.0.1:8037/index.html).
        # Issue #3586 — on injecte aussi `window.__LOCAL_BRIDGE_TOKEN` :
        # les appels `/local/*` exigent désormais le header
        # `X-Local-Bridge-Token` (auth de session locale).
        if target.suffix == ".html":
            injected = (
                "<script>\n"
                "window.__KIOSK_API_BASE = "
                + json.dumps(CONFIG.get("apiBaseUrl", ""))
                + ";\n"
                "window.__KIOSK_DEVICE_CODE = "
                + json.dumps(CONFIG.get("deviceCode", ""))
                + ";\n"
                "window.__KIOSK_TOKEN = "
                + json.dumps(CONFIG.get("kioskToken", ""))
                + ";\n"
                "window.__LOCAL_BRIDGE_TOKEN = "
                + json.dumps(LOCAL_BRIDGE_TOKEN)
                + ";\n"
                "</script>"
            ).encode("utf-8")
            body = body.replace(b"</head>", injected + b"</head>", 1)

        self.send_response(200)
        self.send_header("Content-Type", content_type)
        # Le HTML transporte le token de session : jamais de cache (#3586).
        if target.suffix == ".html":
            self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format: str, *args) -> None:
        return


def auto_sync_loop() -> None:
    interval = int(CONFIG.get("syncIntervalSeconds", 60))
    while True:
        if CONFIG.get("autoSync", True):
            try:
                SYNC_ENGINE.sync_all()
            except Exception:
                pass
        time.sleep(max(15, interval))


def main() -> None:
    threading.Thread(target=auto_sync_loop, daemon=True).start()
    host = CONFIG.get("listenHost", "127.0.0.1")
    port = int(CONFIG.get("listenPort", 8037))
    if host not in ("127.0.0.1", "::1", "localhost"):
        # #3586 : l'auth locale protege desormais les endpoints, mais exposer
        # la borne sur le LAN reste un choix a assumer explicitement.
        print(f"AVERTISSEMENT: bridge expose sur {host} — auth locale active, verifier la config reseau.")
    server = ThreadingHTTPServer((host, port), BridgeHandler)
    print(f"Bridge ZKTeco local demarre sur http://{host}:{port}")
    server.serve_forever()


if __name__ == "__main__":
    main()
