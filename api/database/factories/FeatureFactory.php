<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $featureTypes = ['list', 'form', 'detail', 'dashboard', 'generic'];
        $httpMethods = [
            ['GET'],
            ['GET', 'POST'],
            ['GET', 'POST', 'PUT', 'DELETE'],
            ['POST'],
            ['PUT', 'PATCH'],
        ];

        return [
            'company_id' => null, // Sera dÃ©fini par le test ou le seeder
            'key' => $this->faker->unique()->slug(2).'_management',
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(10),
            'endpoint' => '/api/v1/'.$this->faker->slug(2),
            'http_methods' => $this->faker->randomElement($httpMethods),
            'parameters' => $this->generateParameters(),
            'response_schema' => $this->generateResponseSchema(),
            'permissions' => $this->generatePermissions(),
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => $this->faker->optional(0.3)->randomElement(['1.5.0', '2.0.0', null]),
            'api_version' => $this->faker->randomElement(['1.0.0', '1.1.0', '1.2.0']),
            'status' => $this->faker->randomElement(['active', 'deprecated']),
            'metadata' => [
                'ui_type' => $this->faker->randomElement($featureTypes),
                'form_schema' => $this->generateFormSchema(),
                'list_schema' => $this->generateListSchema(),
            ],
        ];
    }

    /**
     * GÃ©nÃ¨re des paramÃ¨tres d'exemple pour l'API
     */
    private function generateParameters(): array
    {
        return [
            'list' => [
                'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
                'per_page' => ['type' => 'integer', 'required' => false, 'default' => 15],
                'search' => ['type' => 'string', 'required' => false],
            ],
            'create' => [
                'name' => ['type' => 'string', 'required' => true, 'max_length' => 100],
                'description' => ['type' => 'string', 'required' => false],
                'status' => ['type' => 'enum', 'required' => false, 'values' => ['active', 'inactive']],
            ],
            'update' => [
                'name' => ['type' => 'string', 'required' => false, 'max_length' => 100],
                'description' => ['type' => 'string', 'required' => false],
                'status' => ['type' => 'enum', 'required' => false, 'values' => ['active', 'inactive']],
            ],
        ];
    }

    /**
     * GÃ©nÃ¨re un schÃ©ma de rÃ©ponse d'exemple
     */
    private function generateResponseSchema(): array
    {
        return [
            'item' => [
                'id' => 'integer',
                'name' => 'string',
                'description' => 'string',
                'status' => 'string',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ],
            'list' => [
                'data' => 'array',
                'meta' => [
                    'current_page' => 'integer',
                    'per_page' => 'integer',
                    'total' => 'integer',
                    'last_page' => 'integer',
                ],
            ],
        ];
    }

    /**
     * GÃ©nÃ¨re des permissions d'exemple
     */
    private function generatePermissions(): array
    {
        $basePermission = $this->faker->slug(2);

        return [
            $basePermission.'.view',
            $basePermission.'.create',
            $basePermission.'.update',
            $basePermission.'.delete',
        ];
    }

    /**
     * GÃ©nÃ¨re un schÃ©ma de formulaire pour l'interface mobile
     */
    private function generateFormSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Nom',
                    'required' => true,
                    'validation' => ['min_length' => 2, 'max_length' => 100],
                ],
                [
                    'name' => 'description',
                    'type' => 'textarea',
                    'label' => 'Description',
                    'required' => false,
                    'validation' => ['max_length' => 500],
                ],
                [
                    'name' => 'status',
                    'type' => 'select',
                    'label' => 'Statut',
                    'required' => true,
                    'options' => [
                        ['value' => 'active', 'label' => 'Actif'],
                        ['value' => 'inactive', 'label' => 'Inactif'],
                    ],
                ],
            ],
        ];
    }

    /**
     * GÃ©nÃ¨re un schÃ©ma de liste pour l'interface mobile
     */
    private function generateListSchema(): array
    {
        return [
            'columns' => [
                ['field' => 'name', 'label' => 'Nom', 'sortable' => true],
                ['field' => 'status', 'label' => 'Statut', 'sortable' => true],
                ['field' => 'created_at', 'label' => 'CrÃ©Ã© le', 'sortable' => true, 'type' => 'date'],
            ],
            'actions' => [
                ['type' => 'view', 'label' => 'Voir'],
                ['type' => 'edit', 'label' => 'Modifier'],
                ['type' => 'delete', 'label' => 'Supprimer'],
            ],
        ];
    }

    /**
     * Ã‰tat actif
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Ã‰tat dÃ©prÃ©ciÃ©
     */
    public function deprecated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deprecated',
        ]);
    }

    /**
     * Ã‰tat supprimÃ©
     */
    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'removed',
        ]);
    }

    /**
     * FonctionnalitÃ© de type liste
     */
    public function listType(): static
    {
        return $this->state(fn (array $attributes) => [
            'http_methods' => ['GET'],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'ui_type' => 'list',
            ]),
        ]);
    }

    /**
     * FonctionnalitÃ© de type formulaire
     */
    public function formType(): static
    {
        return $this->state(fn (array $attributes) => [
            'http_methods' => ['GET', 'POST', 'PUT'],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'ui_type' => 'form',
            ]),
        ]);
    }

    /**
     * FonctionnalitÃ© CRUD complÃ¨te
     */
    public function fullCrud(): static
    {
        return $this->state(fn (array $attributes) => [
            'http_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'ui_type' => 'list',
            ]),
        ]);
    }
}
