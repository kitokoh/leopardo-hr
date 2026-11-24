<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #9896 (ADR-9879, etape 2) — sonde de statut RLS.
 *
 * ## Le probleme que cette commande resout
 *
 * L'ADR-9879 exige un role applicatif `NOSUPERUSER NOBYPASSRLS` avant que RLS
 * ne protege quoi que ce soit : PostgreSQL fait TOUJOURS bypasser RLS aux
 * superusers, MEME avec `FORCE ROW LEVEL SECURITY`. Les politiques de #9879 et
 * #9889 existent donc, mais elles sont **inertes** tant que la bascule n'est
 * pas faite.
 *
 * Jusqu'ici, ce fait n'etait verifiable qu'en lisant l'ADR et en lancant `\du`
 * a la main. Une politique « posee mais inoperante » est le pire des etats :
 * elle donne l'impression d'une protection qui n'existe pas. Cette commande
 * rend l'etat **mesurable et opposable** :
 *
 *   - le role courant bypass-t-il RLS (SUPERUSER ou BYPASSRLS) ?
 *   - quelles tables portent ENABLE + FORCE + une politique ?
 *   - le tenant est-il publie dans la session (`app.current_company_id`) ?
 *
 * ## Codes de sortie
 *
 *   0 — RLS est effectif : role non-bypass, toutes les tables couvertes.
 *   1 — RLS est annonce mais BYPASSE (role superuser/BYPASSRLS) : c'est
 *       l'etat actuel du depot, et c'est precisement ce qu'il faut voir.
 *   2 — RLS est effectif mais une table attendue n'est pas couverte.
 *   3 — environnement non-PostgreSQL : la sonde ne s'applique pas.
 *
 * Le code 1 n'est PAS une erreur de la commande : c'est le verdict. Un
 * deploiement qui veut faire echouer un pipeline sur cet etat teste `$?`.
 */
final class TenantRlsStatusCommand extends Command
{
    protected $signature = 'tenant:rls-status
                            {--json : Sortie machine (CI, scripts)}
                            {--strict : Sortir en erreur si RLS est bypasse (garde de deploiement)}';

    protected $description = 'Verifie si PostgreSQL RLS protege reellement (role non-superuser + politiques posees)';

    /**
     * Tables que l'ADR-9879 engage a couvrir, par etape.
     *
     * @var array<string, string>
     */
    private const EXPECTED_TABLES = [
        'bulk_payment_claims' => 'etape 1 (#9879)',
        'pay_slips' => 'etape 3 (#9889)',
        'payroll_runs' => 'etape 3 (#9889)',
        'employees' => 'etape 4 (#9897)',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('tenant:rls-status : RLS est une fonctionnalite PostgreSQL (driver courant : '.DB::getDriverName().').');

            return 3;
        }

        $role = $this->roleStatus();
        $tables = $this->tableStatus();
        $tenant = $this->tenantSetting();

        $bypassed = $role['is_superuser'] || $role['bypasses_rls'];
        $uncovered = array_values(array_filter($tables, static fn (array $t): bool => ! $t['covered']));

        $exit = 0;
        if ($bypassed) {
            $exit = 1;
        } elseif ($uncovered !== []) {
            $exit = 2;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'role' => $role,
                'rls_bypassed' => $bypassed,
                'tables' => $tables,
                'uncovered' => array_map(static fn (array $t): string => $t['table'], $uncovered),
                'tenant_setting' => $tenant,
                'verdict' => $this->verdict($bypassed, $uncovered),
                'exit_code' => $exit,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->option('strict') && $bypassed ? 1 : $exit;
        }

        $this->render($role, $bypassed, $tables, $uncovered, $tenant);

        if ($this->option('strict') && $bypassed) {
            return 1;
        }

        return $exit;
    }

    /**
     * @return array{name: string, is_superuser: bool, bypasses_rls: bool}
     */
    private function roleStatus(): array
    {
        /** @var object{rolname: string, rolsuper: bool, rolbypassrls: bool}|null $row */
        $row = DB::selectOne(
            'SELECT rolname, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return [
            'name' => (string) ($row->rolname ?? '?'),
            'is_superuser' => (bool) ($row->rolsuper ?? false),
            'bypasses_rls' => (bool) ($row->rolbypassrls ?? false),
        ];
    }

    /**
     * @return list<array{table: string, step: string, exists: bool, enabled: bool, forced: bool, policy: bool, covered: bool}>
     */
    private function tableStatus(): array
    {
        $out = [];

        foreach (self::EXPECTED_TABLES as $table => $step) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                $out[] = [
                    'table' => $table,
                    'step' => $step,
                    'exists' => false,
                    'enabled' => false,
                    'forced' => false,
                    'policy' => false,
                    'covered' => false,
                ];

                continue;
            }

            $qualified = $schema.'.'.$table;

            /** @var object{relrowsecurity: bool, relforcerowsecurity: bool}|null $cls */
            $cls = DB::selectOne(
                'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = to_regclass(?)',
                [$qualified]
            );

            $policy = DB::selectOne(
                'SELECT 1 AS ok FROM pg_policy WHERE polrelid = to_regclass(?) AND polname = ?',
                [$qualified, $table.'_tenant_isolation']
            );

            $enabled = (bool) ($cls->relrowsecurity ?? false);
            $forced = (bool) ($cls->relforcerowsecurity ?? false);
            $hasPolicy = $policy !== null;

            $out[] = [
                'table' => $table,
                'step' => $step,
                'exists' => true,
                'enabled' => $enabled,
                'forced' => $forced,
                'policy' => $hasPolicy,
                'covered' => $enabled && $forced && $hasPolicy,
            ];
        }

        return $out;
    }

    /**
     * Le tenant est-il publie dans la session courante ?
     *
     * `current_setting(..., true)` renvoie NULL si la variable n'existe pas.
     * Une chaine vide est l'etat « efface » de `TenantManager::clearTenant()`.
     */
    private function tenantSetting(): ?string
    {
        try {
            /** @var object{c: string|null}|null $row */
            $row = DB::selectOne("SELECT current_setting('app.current_company_id', true) AS c");
        } catch (Throwable) {
            return null;
        }

        $value = $row->c ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @param  list<array{table: string, step: string, exists: bool, enabled: bool, forced: bool, policy: bool, covered: bool}>  $tables
     * @param  list<array{table: string, step: string, exists: bool, enabled: bool, forced: bool, policy: bool, covered: bool}>  $uncovered
     */
    private function render(array $role, bool $bypassed, array $tables, array $uncovered, ?string $tenant): void
    {
        $this->newLine();
        $this->line('  <options=bold>RLS — etat reel de la protection tenant</>');
        $this->newLine();

        $this->line('  Role applicatif : <options=bold>'.$role['name'].'</>');
        $this->line('    SUPERUSER      : '.($role['is_superuser'] ? '<fg=red>oui</>' : '<fg=green>non</>'));
        $this->line('    BYPASSRLS      : '.($role['bypasses_rls'] ? '<fg=red>oui</>' : '<fg=green>non</>'));
        $this->newLine();

        $rows = [];
        foreach ($tables as $t) {
            $rows[] = [
                $t['table'],
                $t['step'],
                $t['exists'] ? 'oui' : '—',
                $t['enabled'] ? 'oui' : 'non',
                $t['forced'] ? 'oui' : 'non',
                $t['policy'] ? 'oui' : 'non',
                $t['covered'] ? '<fg=green>couverte</>' : '<fg=yellow>non couverte</>',
            ];
        }

        $this->table(['Table', 'Etape', 'Existe', 'ENABLE', 'FORCE', 'Policy', 'Etat'], $rows);
        $this->newLine();

        $this->line('  Tenant publie dans la session : '.($tenant === null ? '<fg=yellow>aucun</> (fail-closed)' : '<fg=green>'.$tenant.'</>'));
        $this->newLine();

        if ($bypassed) {
            $this->warn('  ⚠️  RLS est BYPASSE par le role courant.');
            $this->line('     PostgreSQL fait toujours bypasser RLS aux SUPERUSER/BYPASSRLS,');
            $this->line('     MEME avec FORCE ROW LEVEL SECURITY. Les politiques existent mais');
            $this->line('     ne protegent rien. Bascule : database/scripts/rls/create-app-role.sql');
            $this->line('     puis DB_USERNAME=leopardo_app (ADR-9879, etape 2).');
            $this->newLine();

            return;
        }

        if ($uncovered !== []) {
            $this->warn('  ⚠️  Role conforme, mais '.count($uncovered).' table(s) non couverte(s) : '
                .implode(', ', array_map(static fn (array $t): string => $t['table'], $uncovered)));

            return;
        }

        $this->info('  ✅ RLS est effectif : role non-bypass, toutes les tables attendues couvertes.');
    }

    /**
     * @param  list<array{table: string, step: string, exists: bool, enabled: bool, forced: bool, policy: bool, covered: bool}>  $uncovered
     */
    private function verdict(bool $bypassed, array $uncovered): string
    {
        if ($bypassed) {
            return 'bypassed';
        }

        return $uncovered === [] ? 'effective' : 'partial';
    }
}
