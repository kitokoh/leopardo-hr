#!/usr/bin/env bash
# ============================================================================
# test_backup_store.py — tests de dev-hub/scripts/backup-store.sh (#7657)
# ============================================================================
#
# Le script choisit un fournisseur de stockage objet pour la sauvegarde
# quotidienne. Une régression ici ne casse pas un test unitaire : elle rend la
# sauvegarde de production silencieusement impossible (ou pire, l'envoie au
# mauvais endroit). Ces tests verrouillent :
#
#   1. la sélection du fournisseur (priorité S3, repli B2, override explicite) ;
#   2. l'échec BRUYANT quand aucun fournisseur n'est configuré (jamais de no-op) ;
#   3. le rejet d'un override inconnu ;
#   4. le parsing de `latest` (horodatage ISO + clé + taille) sur les deux
#      fournisseurs, avec un `aws`/`curl` simulé — aucun appel réseau.
#
# Usage : python3 dev-hub/tools/test_backup_store.py
# ============================================================================

from __future__ import annotations

import os
import subprocess
import sys
import tempfile
import textwrap
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
SCRIPT = REPO_ROOT / "dev-hub" / "scripts" / "backup-store.sh"

# Variables d'environnement qui influencent la sélection du fournisseur.
PROVIDER_VARS = (
    "BACKUP_STORE",
    "BACKUP_S3_BUCKET",
    "AWS_ACCESS_KEY_ID",
    "AWS_SECRET_ACCESS_KEY",
    "BACKUP_S3_ENDPOINT",
    "B2_BUCKET_NAME",
    "B2_KEY_ID",
    "B2_APP_KEY",
)

failures: list[str] = []


def run(args: list[str], env: dict[str, str], path_prefix: str | None = None) -> subprocess.CompletedProcess[str]:
    """Exécute le script avec un environnement PROPRE (aucune fuite du shell)."""
    base = {k: v for k, v in os.environ.items() if k not in PROVIDER_VARS}
    base.update(env)
    if path_prefix:
        base["PATH"] = f"{path_prefix}:{base.get('PATH', '')}"
    return subprocess.run(
        ["bash", str(SCRIPT), *args],
        capture_output=True,
        text=True,
        env=base,
        check=False,
    )


def check(name: str, condition: bool, detail: str = "") -> None:
    if condition:
        print(f"ok: {name}")
    else:
        failures.append(name)
        print(f"FAIL: {name}{(' — ' + detail) if detail else ''}", file=sys.stderr)


def test_provider_selection() -> None:
    # 1. Aucun fournisseur → exit non nul + message explicite (jamais de no-op).
    r = run(["provider"], {})
    check(
        "aucun fournisseur → échec bruyant",
        r.returncode != 0 and "Aucun fournisseur" in r.stderr,
        f"exit={r.returncode} stderr={r.stderr.strip()[:120]}",
    )

    # 2. B2 seul → b2.
    r = run(["provider"], {"B2_BUCKET_NAME": "b", "B2_KEY_ID": "k", "B2_APP_KEY": "s"})
    check("B2 seul → b2", r.returncode == 0 and r.stdout.strip() == "b2", r.stdout.strip())

    # 3. S3 seul → s3.
    r = run(
        ["provider"],
        {"BACKUP_S3_BUCKET": "b", "AWS_ACCESS_KEY_ID": "k", "AWS_SECRET_ACCESS_KEY": "s"},
    )
    check("S3 seul → s3", r.returncode == 0 and r.stdout.strip() == "s3", r.stdout.strip())

    # 4. Les deux → S3 gagne (comportement historique préservé).
    r = run(
        ["provider"],
        {
            "BACKUP_S3_BUCKET": "b",
            "AWS_ACCESS_KEY_ID": "k",
            "AWS_SECRET_ACCESS_KEY": "s",
            "B2_BUCKET_NAME": "b2",
            "B2_KEY_ID": "k2",
            "B2_APP_KEY": "s2",
        },
    )
    check("S3 + B2 → s3 (priorité historique)", r.stdout.strip() == "s3", r.stdout.strip())

    # 5. Override explicite → prime sur la détection.
    r = run(
        ["provider"],
        {
            "BACKUP_STORE": "b2",
            "BACKUP_S3_BUCKET": "b",
            "AWS_ACCESS_KEY_ID": "k",
            "AWS_SECRET_ACCESS_KEY": "s",
            "B2_BUCKET_NAME": "b2",
            "B2_KEY_ID": "k2",
            "B2_APP_KEY": "s2",
        },
    )
    check("BACKUP_STORE=b2 prime sur S3", r.stdout.strip() == "b2", r.stdout.strip())

    # 6. Override inconnu → échec explicite (pas de repli silencieux).
    r = run(["provider"], {"BACKUP_STORE": "gcs"})
    check(
        "BACKUP_STORE inconnu → échec",
        r.returncode != 0 and "inconnu" in r.stderr,
        r.stderr.strip()[:120],
    )

    # 7. B2 partiel (clé manquante) → pas considéré comme configuré.
    r = run(["provider"], {"B2_BUCKET_NAME": "b", "B2_KEY_ID": "k"})
    check(
        "B2 incomplet → échec (pas de faux positif)",
        r.returncode != 0 and "Aucun fournisseur" in r.stderr,
        r.stderr.strip()[:120],
    )


def test_s3_latest_parsing() -> None:
    """`latest` doit rendre « <ISO> <clé> <taille> » depuis la sortie d'aws s3 ls."""
    with tempfile.TemporaryDirectory() as tmp:
        fake_aws = Path(tmp) / "aws"
        fake_aws.write_text(
            textwrap.dedent(
                """\
                #!/usr/bin/env bash
                # Simule `aws s3 ls s3://bucket/prefix --recursive`.
                cat <<'OUT'
                2026-10-27 02:15:03   1000 postgres/daily/2026/10/27/leopardo-20261027-021503.dump.age
                2026-10-29 02:15:07   2048 postgres/daily/2026/10/29/leopardo-20261029-021507.dump.age
                2026-10-28 02:15:05   1500 postgres/daily/2026/10/28/leopardo-20261028-021505.dump.age
                OUT
                """
            )
        )
        fake_aws.chmod(0o755)

        r = run(
            ["latest", "postgres/daily/"],
            {"BACKUP_S3_BUCKET": "b", "AWS_ACCESS_KEY_ID": "k", "AWS_SECRET_ACCESS_KEY": "s"},
            path_prefix=tmp,
        )
        expected = "2026-10-29T02:15:07Z postgres/daily/2026/10/29/leopardo-20261029-021507.dump.age 2048"
        check("s3 latest → objet le plus récent", r.stdout.strip() == expected, r.stdout.strip())


def test_b2_latest_parsing() -> None:
    """`latest` doit convertir uploadTimestamp (ms epoch) en ISO 8601 UTC."""
    with tempfile.TemporaryDirectory() as tmp:
        fake_curl = Path(tmp) / "curl"
        # 2026-10-29T02:15:07Z = 1793240107 s → 1793240107000 ms
        fake_curl.write_text(
            textwrap.dedent(
                """\
                #!/usr/bin/env bash
                # 1er appel : authorize_account ; 2e : list_buckets ; 3e : list_file_names.
                case "$*" in
                  *b2_authorize_account*)
                    echo '{"apiUrl":"https://api.example","accountId":"acc","authorizationToken":"tok"}'
                    ;;
                  *b2_list_buckets*)
                    echo '{"buckets":[{"bucketId":"bid"}]}'
                    ;;
                  *b2_list_file_names*)
                    echo '{"files":[{"fileName":"postgres/daily/2026/10/28/a.dump.age","uploadTimestamp":1793153705000,"contentLength":1500},{"fileName":"postgres/daily/2026/10/29/b.dump.age","uploadTimestamp":1793240107000,"contentLength":2048}],"nextFileName":null}'
                    ;;
                  *)
                    echo '{}'
                    ;;
                esac
                """
            )
        )
        fake_curl.chmod(0o755)

        r = run(
            ["latest", "postgres/daily/"],
            {"B2_BUCKET_NAME": "b", "B2_KEY_ID": "k", "B2_APP_KEY": "s"},
            path_prefix=tmp,
        )
        expected = "2026-10-29T02:15:07Z postgres/daily/2026/10/29/b.dump.age 2048"
        check("b2 latest → objet le plus récent (ISO UTC)", r.stdout.strip() == expected, r.stdout.strip())


def test_put_requires_existing_file() -> None:
    r = run(
        ["put", "/nonexistent/dump.age", "postgres/daily/x.age"],
        {"B2_BUCKET_NAME": "b", "B2_KEY_ID": "k", "B2_APP_KEY": "s"},
    )
    check(
        "put sur fichier absent → échec",
        r.returncode != 0 and "introuvable" in r.stderr,
        r.stderr.strip()[:120],
    )


def main() -> int:
    if not SCRIPT.is_file():
        print(f"FAIL: script introuvable : {SCRIPT}", file=sys.stderr)
        return 1

    test_provider_selection()
    test_s3_latest_parsing()
    test_b2_latest_parsing()
    test_put_requires_existing_file()

    if failures:
        print(f"\nFAIL : {len(failures)} cas en échec — {', '.join(failures)}", file=sys.stderr)
        return 1

    print("\nPASS : test_backup_store.py")
    return 0


if __name__ == "__main__":
    sys.exit(main())
