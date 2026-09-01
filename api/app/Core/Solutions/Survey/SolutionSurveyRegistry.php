<?php

declare(strict_types=1);

namespace App\Core\Solutions\Survey;

use App\Core\Solutions\Survey\Contracts\SolutionSurvey;
use App\Core\Solutions\Survey\Exceptions\SolutionSurveyNotFoundException;

/**
 * Registre des questionnaires de solutions — allowlist serveur.
 *
 * Miroir de {@see \App\Core\Solutions\SolutionCatalogue} : les surveys sont
 * enregistrés par les providers des modules (inversion de dépendance), ce
 * registre ne référence jamais `App\Modules\*` directement (garde d'isolation
 * #5584). Un code inconnu est REFUSÉ (fail-closed).
 */
final class SolutionSurveyRegistry
{
    /** @var array<string, callable(): SolutionSurvey> */
    private array $factories = [];

    public function register(string $code, callable $factory): void
    {
        $this->factories[$code] = $factory;
    }

    public function has(string $code): bool
    {
        return isset($this->factories[$code]);
    }

    /** @return list<string> */
    public function codes(): array
    {
        $codes = array_keys($this->factories);
        sort($codes);

        return $codes;
    }

    public function resolve(string $code): SolutionSurvey
    {
        if (! isset($this->factories[$code])) {
            throw new SolutionSurveyNotFoundException($code);
        }

        return ($this->factories[$code])();
    }
}
