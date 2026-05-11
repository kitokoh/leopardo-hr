from __future__ import annotations

import json
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


class LocalStore:
    def __init__(self, path: Path) -> None:
        self.path = path
        self.conn = sqlite3.connect(path, check_same_thread=False)
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

    def queue_punch(self, identifier: str, action: str, biometric_type: str) -> dict:
        payload = {
            "external_event_id": str(uuid.uuid4()),
            "identifier": identifier,
            "action": action,
            "biometric_type": biometric_type,
            "occurred_at": utc_now_iso(),
            "created_at": utc_now_iso(),
        }
        with self.conn:
            self.conn.execute(
                """
                insert into punch_queue (
                    external_event_id, identifier, action, biometric_type,
                    occurred_at, created_at
                ) values (?, ?, ?, ?, ?, ?)
                """,
                (
                    payload["external_event_id"],
                    payload["identifier"],
                    payload["action"],
                    payload["biometric_type"],
                    payload["occurred_at"],
                    payload["created_at"],
                ),
            )
        return payload

    def queued_events(self, limit: int = 200) -> list[dict]:
        rows = self.conn.execute(
            """
            select * from punch_queue
            where sync_status = 'queued'
            order by id asc
            limit ?
            """,
            (limit,),
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
        self.api_base_url = config.get("apiBaseUrl", "").rstrip("/")
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

        api_payload = {
            "events": [
                {
                    "identifier": event["identifier"],
                    "action": event["action"],
                    "occurred_at": event["occurred_at"],
                    "external_event_id": event["external_event_id"],
                    "biometric_type": event["biometric_type"],
                }
                for event in events
            ]
        }

        try:
            payload = self._request("POST", f"/kiosks/{self.device_code}/sync", api_payload)
            processed = payload.get("data", {}).get("processed_count", 0)
            event_ids = [event["external_event_id"] for event in events]
            self.store.mark_synced(event_ids, json.dumps(payload.get("data", {}), ensure_ascii=False))
            self.store.set_state("last_sync_at", utc_now_iso())
            self.store.set_state("last_sync_error", "")
            return {"processed_count": processed}
        except Exception as error:
            event_ids = [event["external_event_id"] for event in events]
            self.store.mark_error(event_ids, str(error))
            self.store.set_state("last_sync_error", str(error))
            raise

    def sync_all(self) -> dict:
        roster = self.download_roster()
        uploaded = self.upload_events()
        return {
            "roster": roster,
            "events": uploaded,
        }

    def online_status(self) -> tuple[bool, str]:
        try:
            self._request("GET", f"/kiosks/{self.device_code}/roster")
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
        if length <= 0:
            return {}
        raw = self.rfile.read(length)
        return json.loads(raw.decode("utf-8"))

    def do_GET(self) -> None:
        parsed = urlparse(self.path)
        if parsed.path == "/local/status":
            online, error_message = SYNC_ENGINE.online_status()
            payload = {
                "data": {
                    "company_name": CONFIG.get("companyName", "Leopardo RH Client"),
                    "location_label": CONFIG.get("locationLabel", "Entree principale"),
                    "device_code": CONFIG.get("deviceCode", ""),
                    "queue_count": STORE.queue_count(),
                    "online": online,
                    "last_error": error_message or STORE.get_state("last_sync_error", ""),
                    "last_sync_at": STORE.get_state("last_sync_at", ""),
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
        try:
            if parsed.path == "/local/punch":
                payload = self._read_json()
                identifier = str(payload.get("identifier", "")).strip()
                action = str(payload.get("action", "check_in")).strip() or "check_in"
                biometric_type = str(payload.get("biometric_type", "fingerprint")).strip() or "fingerprint"

                if not identifier:
                    return self._json(422, {"error": "IDENTIFIER_REQUIRED"})

                event = STORE.queue_punch(identifier, action, biometric_type)
                sync_status = "queued"

                if CONFIG.get("autoSync", True):
                    try:
                        SYNC_ENGINE.upload_events()
                        sync_status = "synced"
                    except Exception:
                        sync_status = "queued"

                return self._json(201, {"data": {**event, "sync_status": sync_status}})

            if parsed.path == "/local/sync/roster":
                return self._json(200, {"data": SYNC_ENGINE.download_roster()})

            if parsed.path == "/local/sync/events":
                return self._json(200, {"data": SYNC_ENGINE.upload_events()})

            if parsed.path == "/local/sync/all":
                return self._json(200, {"data": SYNC_ENGINE.sync_all()})

        except urllib.error.URLError as error:
            return self._json(502, {"error": f"REMOTE_UNREACHABLE: {error}"})
        except Exception as error:
            return self._json(500, {"error": str(error)})

        return self._json(404, {"error": "NOT_FOUND"})

    def _serve_static(self, path: str) -> None:
        relative = path.lstrip("/") or "index.html"
        target = (ROOT / relative).resolve()
        if not str(target).startswith(str(ROOT)) or not target.exists() or not target.is_file():
            return self._json(404, {"error": "NOT_FOUND"})

        content_type = "text/html; charset=utf-8"
        if target.suffix == ".js":
            content_type = "application/javascript; charset=utf-8"
        elif target.suffix == ".json":
            content_type = "application/json; charset=utf-8"

        body = target.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", content_type)
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
    server = ThreadingHTTPServer((host, port), BridgeHandler)
    print(f"Bridge ZKTeco local demarre sur http://{host}:{port}")
    server.serve_forever()


if __name__ == "__main__":
    main()
