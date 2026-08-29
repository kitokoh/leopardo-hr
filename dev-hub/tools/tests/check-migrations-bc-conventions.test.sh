#!/usr/bin/env bash
# check-migrations-bc-conventions.test.sh — auto-test du garde MAT-005.
#
# Crée un arbre temporaire de migrations (public + tenant) et vérifie que la
# garde accepte les fichiers conformes et rejette chaque violation avec le
# bon message. Ne modifie aucun fichier du dépôt.
#
# Usage : bash dev-hub/tools/tests/check-migrations-bc-conventions.test.sh
#
# NB : la sortie du garde est capturée dans une variable (jamais de pipe vers
# grep -q) : un `grep -q` ferme le pipe dès la première correspondance et le
# garde meurt en SIGPIPE → `set -o pipefail` rend la condition fausse, test
# faussement rouge (observé en local).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-migrations-bc-conventions.sh"

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

mkdir -p "${TMP}/api/database/migrations/public" "${TMP}/api/database/migrations/tenant"

TENANT_DIR="${TMP}/api/database/migrations/tenant"
PUBLIC_DIR="${TMP}/api/database/migrations/public"

run_guard() {
  # Retourne la sortie du garde (stdout+stderr) sans propager son code de
  # sortie : c'est le CONTENU que l'on vérifie par `case`.
  set +e
  local out
  out="$(bash "${GUARD}" "${TMP}" 2>&1)"
  local rc=$?
  set -e
  printf '%s' "${out}"
}

cat > "${TENANT_DIR}/2099_01_01_000901_9999_create_demo_widgets_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('demo_widgets')) {
            Schema::create('demo_widgets', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name');
                $table->unique(['company_id', 'name'], 'demo_widgets_company_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_widgets');
    }
};
PHP

cat > "${PUBLIC_DIR}/2099_01_01_000902_9999_create_demo_platform_config_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('demo_platform_config')) {
            Schema::create('demo_platform_config', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_platform_config');
    }
};
PHP

# ── Cas conformes ─────────────────────────────────────────────────────────────
if ! bash "${GUARD}" "${TMP}" >/dev/null 2>&1; then
  echo "FAIL: la garde doit accepter des migrations conformes."
  exit 1
fi

# ── Violation 1 : nommage sans référence d'issue ─────────────────────────────
cat > "${TENANT_DIR}/2099_01_01_000903_create_bad_widgets_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('demo_widgets')) {
            Schema::create('demo_widgets', function (Blueprint $table) {
                $table->id();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_widgets');
    }
};
PHP

case "$(run_guard)" in
  *"nommage #5431"*) rm "${TENANT_DIR}/2099_01_01_000903_create_bad_widgets_table.php" ;;
  *) echo "FAIL: nommage sans issue doit être rejeté (règle #5431)."; exit 1 ;;
esac

# ── Violation 2 : down() manquant ─────────────────────────────────────────────
cat > "${TENANT_DIR}/2099_01_01_000904_9999_create_bad_widgets_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('demo_widgets')) {
            Schema::create('demo_widgets', function (Blueprint $table) {
                $table->id();
            });
        }
    }
};
PHP

case "$(run_guard)" in
  *"down() manquant"*) rm "${TENANT_DIR}/2099_01_01_000904_9999_create_bad_widgets_table.php" ;;
  *) echo "FAIL: migration sans down() doit être rejetée."; exit 1 ;;
esac

# ── Violation 3 : Schema::create sans schemaTableExists (tenant) ─────────────
cat > "${TENANT_DIR}/2099_01_01_000905_9999_create_bad_widgets_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_widgets');
    }
};
PHP

case "$(run_guard)" in
  *"schemaTableExists()"*) rm "${TENANT_DIR}/2099_01_01_000905_9999_create_bad_widgets_table.php" ;;
  *) echo "FAIL: Schema::create tenant sans schemaTableExists() doit être rejeté."; exit 1 ;;
esac

# ── Violation 4 : FK vers companies dans une migration tenant ────────────────
cat > "${TENANT_DIR}/2099_01_01_000906_9999_create_bad_widgets_table.php" << 'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('demo_widgets')) {
            Schema::create('demo_widgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->references('id')->on('companies');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_widgets');
    }
};
PHP

case "$(run_guard)" in
  *"FK vers companies"*) rm "${TENANT_DIR}/2099_01_01_000906_9999_create_bad_widgets_table.php" ;;
  *) echo "FAIL: FK tenant→companies doit être rejetée (constitution §II)."; exit 1 ;;
esac

# ── Violation 5 : DDL SQL autonome ───────────────────────────────────────────
printf -- '-- reference only\nCREATE TABLE bad_ddl (id int);\n' > "${TMP}/api/database/bad_ddl.sql"
case "$(run_guard)" in
  *"DDL SQL autonome"*) rm "${TMP}/api/database/bad_ddl.sql" ;;
  *) echo "FAIL: DDL SQL autonome doit être rejeté (chaîne unique Laravel)."; exit 1 ;;
esac

echo "OK: check-migrations-bc-conventions — 5 scénarios de violation + conformité passent."
exit 0
