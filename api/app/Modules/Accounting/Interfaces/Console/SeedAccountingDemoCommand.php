<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Console;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\SeedAccountingDemoData;
use Illuminate\Console\Command;

/**
 * Commande de démonstration du module Comptabilité — issue #5274.
 *
 * « Démo exploitable en 1 clic » : `php artisan accounting:demo-seed <slug|id>`
 * crée une vitrine réaliste (contacts, documents, paiements, paramétrage) pour
 * une entreprise, SANS données réelles. Idempotente ; `--force` recrée
 * uniquement les données marquées demo.
 *
 * Usage :
 *   php artisan accounting:demo-seed techcorp-algerie
 *   php artisan accounting:demo-seed <uuid> --force
 */
final class SeedAccountingDemoCommand extends Command
{
    protected $signature = 'accounting:demo-seed
                            {company? : ID (uuid) ou slug de l\'entreprise cible}
                            {--force : Supprimer et recréer les données demo (jamais les données réelles)}';

    protected $description = 'Crée des données de démonstration réalistes pour le module Comptabilité (issue #5274)';

    public function handle(SeedAccountingDemoData $seeder): int
    {
        $input = $this->argument('company');

        if (! is_string($input) || trim($input) === '') {
            $this->error("Argument obligatoire : ID (uuid) ou slug de l'entreprise (ex. techcorp-algerie).");

            return self::FAILURE;
        }

        $company = $this->resolveCompany(trim($input));

        if (! $company instanceof Company) {
            $this->error("Entreprise introuvable : {$input}");

            return self::FAILURE;
        }

        $result = $seeder->seed($company, (bool) $this->option('force'));

        $this->printResult($company, $result);

        return self::SUCCESS;
    }

    private function resolveCompany(string $input): ?Company
    {
        /** @var Company|null $byId */
        $byId = Company::query()->where('id', $input)->first();

        if ($byId instanceof Company) {
            return $byId;
        }

        /** @var Company|null $bySlug */
        $bySlug = Company::query()->where('slug', $input)->first();

        return $bySlug;
    }

    /**
     * @param  array{seeded: bool, status: string, company_id: string, contacts: int, documents: int, payments: int, skipped_documents: int}  $result
     */
    private function printResult(Company $company, array $result): void
    {
        $this->newLine();
        $this->info(sprintf('Entreprise : %s (%s)', $company->name, $company->slug));

        if ($result['seeded']) {
            $this->info('Données de démonstration comptabilité créées :');
            $this->table(
                ['Contacts', 'Documents', 'Paiements'],
                [[$result['contacts'], $result['documents'], $result['payments']]],
            );
            $this->warn('Toutes les lignes demo portent metadata.demo_seed=true — jamais confondues avec des données réelles.');

            if ($result['skipped_documents'] > 0) {
                $this->warn(sprintf(
                    '%d document(s) démo préservé(s) : ils portent un paiement non-demo — aucune donnée réelle supprimée.',
                    $result['skipped_documents'],
                ));
            }
        } else {
            $this->warn(sprintf('Aucune nouvelle donnée (%s) — rejouer avec --force pour recréer la vitrine.', $result['status']));
        }
    }
}
