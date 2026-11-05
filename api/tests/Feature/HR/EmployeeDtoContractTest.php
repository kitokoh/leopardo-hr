<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Modules\HR\Application\DTOs\CreateEmployeeDTO;
use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreEmployeeRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeRequest;
use Tests\TestCase;

/**
 * Garde structurelle anti-dérive (#8126 / UC-07).
 *
 * Les DTO employé sont construits par `new self(...$validated)` : chaque clé
 * validée par le FormRequest DOIT correspondre à un paramètre nommé du
 * constructeur, sinon PHP lève « Unknown named parameter $x » → HTTP 500.
 *
 * C'est exactement le bug UC-07 : `matricule` et `contract_type` étaient
 * validés par UpdateEmployeeRequest mais absents de UpdateEmployeeDTO.
 * Ce test échoue dès qu'un champ est ajouté d'un côté sans l'autre.
 */
class EmployeeDtoContractTest extends TestCase
{
    public function test_update_employee_request_rules_are_all_dto_constructor_params(): void
    {
        $this->assertRulesCoveredByDto(
            UpdateEmployeeRequest::class,
            UpdateEmployeeDTO::class,
        );
    }

    public function test_store_employee_request_rules_are_all_dto_constructor_params(): void
    {
        $this->assertRulesCoveredByDto(
            StoreEmployeeRequest::class,
            CreateEmployeeDTO::class,
        );
    }

    /**
     * @param  class-string<\Illuminate\Foundation\Http\FormRequest>  $requestClass
     * @param  class-string  $dtoClass
     */
    private function assertRulesCoveredByDto(string $requestClass, string $dtoClass): void
    {
        // On instancie la requête SANS passer par le conteneur : résoudre un
        // FormRequest via app() déclenche validateResolved() (et lève une
        // ValidationException). Ici on ne veut que la liste des règles.
        /** @var \Illuminate\Foundation\Http\FormRequest $request */
        $request = $requestClass::create('/', 'GET');
        $request->setUserResolver(static fn () => null);

        $ruleKeys = array_keys($request->rules());

        // On ne garde que les clés de premier niveau : les clés imbriquées
        // (`extra_data.department`, `resource_assignments.*.x`) sont portées
        // par leur parent (`extra_data`, `resource_assignments`).
        $topLevelKeys = array_values(array_filter(
            $ruleKeys,
            static fn (string $key): bool => ! str_contains($key, '.'),
        ));

        $constructor = new \ReflectionMethod($dtoClass, '__construct');
        $params = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $constructor->getParameters(),
        );

        $missing = array_values(array_diff($topLevelKeys, $params));

        $this->assertSame(
            [],
            $missing,
            sprintf(
                'Champs validés par %s mais absents du constructeur de %s '
                .'(provoquerait « Unknown named parameter » → 500) : %s',
                $requestClass,
                $dtoClass,
                implode(', ', $missing),
            ),
        );
    }
}
