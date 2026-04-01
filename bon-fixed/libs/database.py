"""
database.py — Base de données SQLite pour suivi professionnel

Tables:
- accounts      : Comptes Facebook avec score de santé
- groups        : Groupes avec scoring et historique
- publications  : Historique des publications
- errors        : Journal des erreurs
- selector_stats: Statistiques des sélecteurs
- account_blocks: Historique des blocages

CORRECTIONS v3:
  - Suppression de la colonne storage_state (cookies ne doivent PAS être en DB)
  - Correction blocked_at → started_at dans account_blocks
  - Toutes les méthodes publiques acceptent session_name (str) en plus de account_id
  - record_publication / record_error acceptent group_url (str) et créent le groupe si besoin
  - can_account_post accepte str ou int
  - Singleton thread-safe via threading.Lock
"""

import sqlite3
import json
import threading
import pathlib
from datetime import datetime, timedelta
from typing import Optional, List, Dict, Any, Union

try:
    from libs.log_emitter import emit
except ImportError:
    from log_emitter import emit


class BONDatabase:
    """
    Base de données SQLite pour le suivi professionnel des activités.
    Thread-safe via un verrou global.
    """

    def __init__(self, db_path: str = None):
        if db_path is None:
            try:
                from libs.config_manager import LOGS_DIR
                db_path = str(LOGS_DIR / "bon.db")
            except ImportError:
                db_path = "logs/bon.db"

        self.db_path = pathlib.Path(db_path)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()
        self._init_db()

    # ──────────────────────────────────────────
    # Connexion
    # ──────────────────────────────────────────

    def _connect(self) -> sqlite3.Connection:
        conn = sqlite3.connect(str(self.db_path), check_same_thread=False)
        conn.row_factory = sqlite3.Row
        conn.execute("PRAGMA journal_mode=WAL")
        conn.execute("PRAGMA foreign_keys=ON")
        return conn

    def _exec(self, sql: str, params: tuple = ()) -> sqlite3.Cursor:
        """Exécute une requête sans retour de résultat."""
        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(sql, params)
                conn.commit()
                return cur
            finally:
                conn.close()

    def _query(self, sql: str, params: tuple = ()) -> List[Dict]:
        """Exécute une requête SELECT et retourne une liste de dicts."""
        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(sql, params)
                return [dict(row) for row in cur.fetchall()]
            finally:
                conn.close()

    def _query_one(self, sql: str, params: tuple = ()) -> Optional[Dict]:
        """Exécute une requête SELECT et retourne un seul dict ou None."""
        rows = self._query(sql, params)
        return rows[0] if rows else None

    def _query_scalar(self, sql: str, params: tuple = ()):
        """Retourne la première colonne de la première ligne."""
        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(sql, params)
                row = cur.fetchone()
                return row[0] if row else None
            finally:
                conn.close()

    # ──────────────────────────────────────────
    # Initialisation du schéma
    # ──────────────────────────────────────────

    def _init_db(self):
        """Initialise le schéma. Idempotent (CREATE IF NOT EXISTS)."""
        ddl_statements = [
            # Comptes — NOTE : pas de colonne storage_state (cookies = fichiers disque)
            """CREATE TABLE IF NOT EXISTS accounts (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                name                TEXT UNIQUE NOT NULL,
                email               TEXT,
                profile_url         TEXT,
                health_score        INTEGER DEFAULT 100,
                status              TEXT DEFAULT 'healthy',
                status_reason       TEXT,
                total_posts         INTEGER DEFAULT 0,
                successful_posts    INTEGER DEFAULT 0,
                failed_posts        INTEGER DEFAULT 0,
                blocked_count       INTEGER DEFAULT 0,
                last_post_date      TEXT,
                last_activity_date  TEXT,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at          TEXT DEFAULT CURRENT_TIMESTAMP,
                max_groups_per_day  INTEGER DEFAULT 20,
                cooldown_until      TEXT,
                warmup_completed    INTEGER DEFAULT 0
            )""",

            # Groupes
            """CREATE TABLE IF NOT EXISTS groups (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                url             TEXT UNIQUE NOT NULL,
                name            TEXT,
                category        TEXT,
                language        TEXT DEFAULT 'fr',
                quality_score   INTEGER DEFAULT 50,
                members_count   INTEGER,
                activity_level  TEXT,
                total_posts     INTEGER DEFAULT 0,
                successful_posts INTEGER DEFAULT 0,
                failed_posts    INTEGER DEFAULT 0,
                rejected_posts  INTEGER DEFAULT 0,
                last_post_date  TEXT,
                first_seen_date TEXT DEFAULT CURRENT_TIMESTAMP,
                metadata        TEXT
            )""",

            # Publications
            """CREATE TABLE IF NOT EXISTS publications (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id      INTEGER NOT NULL,
                group_id        INTEGER NOT NULL,
                campaign_name   TEXT,
                variant_id      TEXT,
                text_content    TEXT,
                images          TEXT,
                status          TEXT NOT NULL,
                error_message   TEXT,
                screenshot_path TEXT,
                created_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                published_at    TEXT,
                FOREIGN KEY (account_id) REFERENCES accounts(id),
                FOREIGN KEY (group_id)   REFERENCES groups(id)
            )""",

            # Erreurs
            """CREATE TABLE IF NOT EXISTS errors (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id          INTEGER,
                group_id            INTEGER,
                error_type          TEXT NOT NULL,
                error_message       TEXT,
                step                TEXT,
                selector_key        TEXT,
                screenshot_path     TEXT,
                html_snapshot_path  TEXT,
                created_at          TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (account_id) REFERENCES accounts(id),
                FOREIGN KEY (group_id)   REFERENCES groups(id)
            )""",

            # Statistiques des sélecteurs
            """CREATE TABLE IF NOT EXISTS selector_stats (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                selector_key        TEXT UNIQUE NOT NULL,
                working_selector    TEXT,
                total_attempts      INTEGER DEFAULT 0,
                successful_attempts INTEGER DEFAULT 0,
                failed_attempts     INTEGER DEFAULT 0,
                success_rate        REAL DEFAULT 100.0,
                last_success_date   TEXT,
                last_failure_date   TEXT,
                last_failure_reason TEXT,
                updated_at          TEXT DEFAULT CURRENT_TIMESTAMP
            )""",

            # Historique des blocages — colonne started_at (pas blocked_at)
            """CREATE TABLE IF NOT EXISTS account_blocks (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id      INTEGER NOT NULL,
                block_type      TEXT NOT NULL,
                reason          TEXT,
                duration_hours  INTEGER,
                started_at      TEXT DEFAULT CURRENT_TIMESTAMP,
                ended_at        TEXT,
                resolved        INTEGER DEFAULT 0,
                FOREIGN KEY (account_id) REFERENCES accounts(id)
            )""",

            # Index
            "CREATE INDEX IF NOT EXISTS idx_pub_account  ON publications(account_id)",
            "CREATE INDEX IF NOT EXISTS idx_pub_group    ON publications(group_id)",
            "CREATE INDEX IF NOT EXISTS idx_pub_status   ON publications(status)",
            "CREATE INDEX IF NOT EXISTS idx_pub_date     ON publications(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_err_account  ON errors(account_id)",
            "CREATE INDEX IF NOT EXISTS idx_err_date     ON errors(created_at)",
        ]

        with self._lock:
            conn = self._connect()
            try:
                for stmt in ddl_statements:
                    conn.execute(stmt)
                conn.commit()
                emit("INFO", "DATABASE_INITIALIZED", path=str(self.db_path))
            finally:
                conn.close()

    # ──────────────────────────────────────────
    # Helpers internes
    # ──────────────────────────────────────────

    def _resolve_account_id(self, account: Union[int, str]) -> Optional[int]:
        """Retourne l'ID d'un compte depuis un int ou un name (str)."""
        if isinstance(account, int):
            return account
        row = self._query_one("SELECT id FROM accounts WHERE name = ?", (account,))
        return row["id"] if row else None

    def _resolve_group_id(self, group: Union[int, str]) -> Optional[int]:
        """Retourne l'ID d'un groupe depuis un int ou une URL (str)."""
        if isinstance(group, int):
            return group
        row = self._query_one("SELECT id FROM groups WHERE url = ?", (group,))
        if row:
            return row["id"]
        # Créer le groupe à la volée si URL inconnue
        return self.add_group(group)

    # ──────────────────────────────────────────
    # ACCOUNTS
    # ──────────────────────────────────────────

    def create_account(self, name: str, email: str = None,
                       profile_url: str = None) -> int:
        """Crée un nouveau compte. Retourne l'ID."""
        now = datetime.now().isoformat()
        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(
                    """INSERT INTO accounts (name, email, profile_url, last_activity_date)
                       VALUES (?, ?, ?, ?)""",
                    (name, email, profile_url, now)
                )
                conn.commit()
                return cur.lastrowid
            finally:
                conn.close()

    def ensure_account_exists(self, name: str, email: str = None,
                               profile_url: str = None) -> int:
        """Crée le compte s'il n'existe pas. Retourne toujours l'ID."""
        row = self._query_one("SELECT id FROM accounts WHERE name = ?", (name,))
        if row:
            return row["id"]
        return self.create_account(name, email, profile_url)

    def get_account(self, name: str) -> Optional[Dict]:
        """Récupère un compte par son nom."""
        return self._query_one("SELECT * FROM accounts WHERE name = ?", (name,))

    def get_account_by_id(self, account_id: int) -> Optional[Dict]:
        """Récupère un compte par son ID."""
        return self._query_one("SELECT * FROM accounts WHERE id = ?", (account_id,))

    def get_all_accounts(self) -> List[Dict]:
        """Récupère tous les comptes."""
        return self._query("SELECT * FROM accounts ORDER BY updated_at DESC")

    def get_account_status(self, name: str) -> Optional[str]:
        """Retourne le statut d'un compte (None si inconnu)."""
        row = self._query_one("SELECT status FROM accounts WHERE name = ?", (name,))
        return row["status"] if row else None

    def update_account_status(self, account: Union[int, str],
                               status: str, reason: str = None):
        """Met à jour le statut d'un compte (int ou name)."""
        account_id = self._resolve_account_id(account)
        if account_id is None:
            if isinstance(account, str):
                account_id = self.ensure_account_exists(account)
            else:
                emit("WARN", "DB_ACCOUNT_NOT_FOUND", account=account)
                return
        self._set_account_status(account_id, status, reason)

    def _set_account_status(self, account_id: int, status: str, reason: str = None):
        """Implémentation interne — attend un account_id."""
        now = datetime.now()
        cooldown_until = None

        if status == "temporarily_blocked":
            cooldown_until = (now + timedelta(hours=24)).isoformat()
        elif status == "warning":
            cooldown_until = (now + timedelta(hours=3)).isoformat()

        self._exec(
            """UPDATE accounts
               SET status = ?, status_reason = ?, cooldown_until = ?, updated_at = ?
               WHERE id = ?""",
            (status, reason, cooldown_until, now.isoformat(), account_id)
        )

    def can_account_post(self, account: Union[int, str]) -> tuple:
        """
        Vérifie si un compte peut poster.

        Args:
            account: account_id (int) ou session_name (str)

        Returns:
            (bool, str) — (peut poster, raison)
        """
        if isinstance(account, str):
            row = self.get_account(account)
        else:
            row = self.get_account_by_id(account)

        if not row:
            return False, "Compte inexistant en base"

        # Cooldown actif ?
        cooldown = row.get("cooldown_until")
        if cooldown:
            try:
                until = datetime.fromisoformat(cooldown)
                if datetime.now() < until:
                    remaining_h = int((until - datetime.now()).total_seconds() // 3600)
                    return False, f"Cooldown actif — encore {remaining_h}h"
            except ValueError:
                pass

        # Statut bloquant ?
        status = row.get("status", "healthy")
        if status == "temporarily_blocked":
            return False, "Compte temporairement bloqué par Facebook"
        if status == "session_expired":
            return False, "Session expirée — reconnectez-vous"

        # Limite quotidienne
        account_id = row["id"]
        today = datetime.now().date().isoformat()
        posts_today = self._query_scalar(
            "SELECT COUNT(*) FROM publications WHERE account_id = ? AND DATE(created_at) = ?",
            (account_id, today)
        ) or 0
        max_per_day = row.get("max_groups_per_day", 20)

        if posts_today >= max_per_day:
            return False, f"Limite quotidienne atteinte ({posts_today}/{max_per_day})"

        return True, "OK"

    def record_account_block(self, account: Union[int, str],
                              block_type: str, reason: str):
        """Enregistre un blocage de compte."""
        account_id = self._resolve_account_id(account)
        if account_id is None and isinstance(account, str):
            account_id = self.ensure_account_exists(account)

        self._set_account_status(account_id, "temporarily_blocked", reason)

        # Insérer dans account_blocks — colonne correcte : started_at
        self._exec(
            """INSERT INTO account_blocks (account_id, block_type, reason, started_at)
               VALUES (?, ?, ?, ?)""",
            (account_id, block_type, reason, datetime.now().isoformat())
        )

    def get_account_block_info(self, name: str) -> Optional[Dict]:
        """Retourne les infos de blocage d'un compte."""
        row = self.get_account(name)
        if not row or row["status"] != "temporarily_blocked":
            return None

        cooldown = row.get("cooldown_until")
        can_resume = True
        if cooldown:
            try:
                can_resume = datetime.now() >= datetime.fromisoformat(cooldown)
            except ValueError:
                can_resume = True

        return {
            "status":     row["status"],
            "reason":     row.get("status_reason"),
            "until":      cooldown,
            "can_resume": can_resume,
        }

    # ──────────────────────────────────────────
    # GROUPS
    # ──────────────────────────────────────────

    def add_group(self, url: str, name: str = None, category: str = None,
                  language: str = "fr", members_count: int = None) -> int:
        """Ajoute ou met à jour un groupe. Retourne l'ID."""
        existing = self._query_one("SELECT id FROM groups WHERE url = ?", (url,))
        if existing:
            self._exec(
                """UPDATE groups
                   SET name = COALESCE(?, name),
                       category = COALESCE(?, category),
                       language = COALESCE(?, language),
                       members_count = COALESCE(?, members_count)
                   WHERE url = ?""",
                (name, category, language, members_count, url)
            )
            return existing["id"]

        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(
                    "INSERT INTO groups (url, name, category, language, members_count) VALUES (?,?,?,?,?)",
                    (url, name, category, language, members_count)
                )
                conn.commit()
                return cur.lastrowid
            finally:
                conn.close()

    def get_group_by_url(self, url: str) -> Optional[Dict]:
        return self._query_one("SELECT * FROM groups WHERE url = ?", (url,))

    def get_best_groups(self, limit: int = 10, category: str = None) -> List[Dict]:
        if category:
            return self._query(
                "SELECT * FROM groups WHERE category = ? ORDER BY quality_score DESC LIMIT ?",
                (category, limit)
            )
        return self._query(
            "SELECT * FROM groups ORDER BY quality_score DESC LIMIT ?", (limit,)
        )

    def get_all_groups(self) -> List[Dict]:
        return self._query("SELECT * FROM groups ORDER BY quality_score DESC")

    # ──────────────────────────────────────────
    # PUBLICATIONS
    # ──────────────────────────────────────────

    def record_publication(
        self,
        account_name: str,
        group_url: str,
        status: str = "success",
        post_content: str = None,
        campaign_name: str = None,
        images: List[str] = None,
        error_message: str = None,
        screenshot_path: str = None,
    ) -> int:
        """
        Enregistre une publication.

        Args:
            account_name: nom de la session (str) — crée le compte si besoin
            group_url:    URL du groupe (str) — crée le groupe si besoin
            status:       "success" | "skipped" | "failed"
            post_content: texte du post (tronqué à 500 chars)
            campaign_name, images, error_message, screenshot_path: optionnels

        Returns:
            ID de la publication
        """
        account_id = self.ensure_account_exists(account_name)
        group_id   = self._resolve_group_id(group_url)
        images_json = json.dumps(images) if images else None
        now = datetime.now().isoformat()

        with self._lock:
            conn = self._connect()
            try:
                cur = conn.execute(
                    """INSERT INTO publications
                       (account_id, group_id, campaign_name, text_content,
                        images, status, error_message, screenshot_path, published_at)
                       VALUES (?,?,?,?,?,?,?,?,?)""",
                    (account_id, group_id, campaign_name,
                     (post_content or "")[:500],
                     images_json, status, error_message, screenshot_path,
                     now if status == "success" else None)
                )
                pub_id = cur.lastrowid

                # Mettre à jour les compteurs groupe
                if status == "success":
                    conn.execute(
                        """UPDATE groups
                           SET successful_posts = successful_posts + 1,
                               total_posts = total_posts + 1,
                               last_post_date = ?
                           WHERE id = ?""",
                        (now, group_id)
                    )
                    conn.execute(
                        """UPDATE accounts
                           SET successful_posts = successful_posts + 1,
                               total_posts = total_posts + 1,
                               last_post_date = ?,
                               last_activity_date = ?,
                               updated_at = ?
                           WHERE id = ?""",
                        (now, now, now, account_id)
                    )
                else:
                    conn.execute(
                        "UPDATE groups SET failed_posts = failed_posts + 1, total_posts = total_posts + 1 WHERE id = ?",
                        (group_id,)
                    )
                    conn.execute(
                        """UPDATE accounts
                           SET failed_posts = failed_posts + 1,
                               total_posts = total_posts + 1,
                               last_activity_date = ?, updated_at = ?
                           WHERE id = ?""",
                        (now, now, account_id)
                    )

                conn.commit()
                return pub_id
            finally:
                conn.close()

    def get_publications(self, account_name: str = None,
                         group_url: str = None, limit: int = 50) -> List[Dict]:
        """Récupère l'historique des publications."""
        where, params = ["1=1"], []

        if account_name:
            acc = self.get_account(account_name)
            if acc:
                where.append("account_id = ?")
                params.append(acc["id"])

        if group_url:
            grp = self.get_group_by_url(group_url)
            if grp:
                where.append("group_id = ?")
                params.append(grp["id"])

        params.append(limit)
        return self._query(
            f"SELECT * FROM publications WHERE {' AND '.join(where)} ORDER BY created_at DESC LIMIT ?",
            tuple(params)
        )

    # ──────────────────────────────────────────
    # ERRORS
    # ──────────────────────────────────────────

    def record_error(
        self,
        account_name: str = None,
        group_url: str = None,
        error_type: str = None,
        error_message: str = None,
        step: str = None,
        selector_key: str = None,
        screenshot_path: str = None,
        html_snapshot_path: str = None,
    ):
        """
        Enregistre une erreur.

        Args:
            account_name: nom de la session (str) — résolu en ID
            group_url:    URL du groupe (str) — résolu en ID
        """
        account_id = None
        group_id   = None

        if account_name:
            account_id = self._resolve_account_id(account_name)

        if group_url:
            group_id = self._resolve_group_id(group_url)

        self._exec(
            """INSERT INTO errors
               (account_id, group_id, error_type, error_message, step,
                selector_key, screenshot_path, html_snapshot_path)
               VALUES (?,?,?,?,?,?,?,?)""",
            (account_id, group_id, error_type, error_message,
             step, selector_key, screenshot_path, html_snapshot_path)
        )

    def get_recent_errors(self, limit: int = 20) -> List[Dict]:
        return self._query(
            "SELECT * FROM errors ORDER BY created_at DESC LIMIT ?", (limit,)
        )

    # ──────────────────────────────────────────
    # SELECTOR STATS
    # ──────────────────────────────────────────

    def record_selector_attempt(self, selector_key: str, success: bool,
                                 used_selector: str = None,
                                 failure_reason: str = None):
        """Enregistre une tentative de sélecteur."""
        now = datetime.now().isoformat()
        existing = self._query_one(
            "SELECT id, total_attempts, successful_attempts FROM selector_stats WHERE selector_key = ?",
            (selector_key,)
        )

        if existing:
            if success:
                self._exec(
                    """UPDATE selector_stats
                       SET successful_attempts = successful_attempts + 1,
                           total_attempts = total_attempts + 1,
                           last_success_date = ?,
                           working_selector = COALESCE(?, working_selector),
                           success_rate = (successful_attempts + 1) * 100.0 / (total_attempts + 1),
                           updated_at = ?
                       WHERE selector_key = ?""",
                    (now, used_selector, now, selector_key)
                )
            else:
                self._exec(
                    """UPDATE selector_stats
                       SET failed_attempts = failed_attempts + 1,
                           total_attempts = total_attempts + 1,
                           last_failure_date = ?,
                           last_failure_reason = COALESCE(?, last_failure_reason),
                           success_rate = successful_attempts * 100.0 / (total_attempts + 1),
                           updated_at = ?
                       WHERE selector_key = ?""",
                    (now, failure_reason, now, selector_key)
                )
        else:
            self._exec(
                """INSERT INTO selector_stats
                   (selector_key, working_selector, total_attempts,
                    successful_attempts, failed_attempts, success_rate,
                    last_success_date, last_failure_date, last_failure_reason)
                   VALUES (?,?,1,?,?,?,?,?,?)""",
                (selector_key,
                 used_selector if success else None,
                 1 if success else 0,
                 0 if success else 1,
                 100.0 if success else 0.0,
                 now if success else None,
                 now if not success else None,
                 failure_reason if not success else None)
            )

    def get_selector_stats(self) -> List[Dict]:
        return self._query("SELECT * FROM selector_stats ORDER BY success_rate ASC")

    # ──────────────────────────────────────────
    # DASHBOARD / REPORTING
    # ──────────────────────────────────────────

    def get_dashboard_stats(self) -> Dict:
        """Retourne les statistiques pour le dashboard."""
        today = datetime.now().date().isoformat()
        stats = {}

        stats["total_accounts"]   = self._query_scalar("SELECT COUNT(*) FROM accounts") or 0
        stats["healthy_accounts"] = self._query_scalar("SELECT COUNT(*) FROM accounts WHERE status = 'healthy'") or 0
        stats["blocked_accounts"] = self._query_scalar("SELECT COUNT(*) FROM accounts WHERE status = 'temporarily_blocked'") or 0
        stats["total_groups"]     = self._query_scalar("SELECT COUNT(*) FROM groups") or 0
        stats["avg_group_score"]  = round(self._query_scalar("SELECT AVG(quality_score) FROM groups") or 0, 1)

        stats["posts_today"]           = self._query_scalar("SELECT COUNT(*) FROM publications WHERE DATE(created_at) = ?", (today,)) or 0
        stats["successful_posts_today"] = self._query_scalar("SELECT COUNT(*) FROM publications WHERE DATE(created_at) = ? AND status = 'success'", (today,)) or 0
        stats["failed_posts_today"]     = self._query_scalar("SELECT COUNT(*) FROM publications WHERE DATE(created_at) = ? AND status = 'failed'", (today,)) or 0
        stats["errors_today"]           = self._query_scalar("SELECT COUNT(*) FROM errors WHERE DATE(created_at) = DATE('now')") or 0

        if stats["posts_today"] > 0:
            stats["success_rate_today"] = round(stats["successful_posts_today"] * 100 / stats["posts_today"], 1)
        else:
            stats["success_rate_today"] = 0

        return stats


# ──────────────────────────────────────────
# Singleton thread-safe
# ──────────────────────────────────────────

_db_instance: Optional[BONDatabase] = None
_db_lock = threading.Lock()


def get_database() -> BONDatabase:
    """Retourne l'instance singleton thread-safe."""
    global _db_instance
    if _db_instance is None:
        with _db_lock:
            if _db_instance is None:
                _db_instance = BONDatabase()
    return _db_instance
