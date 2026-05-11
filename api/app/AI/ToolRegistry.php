<?php

namespace App\AI;

use Illuminate\Support\Facades\DB;

class ToolRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $tools = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getToolsForRole(string $role, string $companyId): array
    {
        $this->loadTools();

        $roleHierarchy = ['employee' => 1, 'manager' => 2, 'admin' => 3, 'super_admin' => 4];
        $userLevel = $roleHierarchy[$role] ?? 1;

        return array_filter($this->tools, function (array $tool) use ($userLevel, $roleHierarchy): bool {
            if (! ($tool['active'] ?? true)) {
                return false;
            }
            $requiredLevel = $roleHierarchy[$tool['required_role'] ?? 'employee'] ?? 1;

            return $userLevel >= $requiredLevel;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getToolsAsLLMFormat(string $role, string $companyId): array
    {
        $tools = $this->getToolsForRole($role, $companyId);
        $formatted = [];

        foreach ($tools as $tool) {
            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass],
                ],
            ];
        }

        return $formatted;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTool(string $name): ?array
    {
        $this->loadTools();

        return $this->tools[$name] ?? null;
    }

    private function loadTools(): void
    {
        if (count($this->tools) > 0) {
            return;
        }

        try {
            $rows = DB::table('ai_tool_registry')->where('active', true)->get();
            foreach ($rows as $row) {
                /** @var string $params */
                $params = $row->parameters ?? '{}';
                /** @var string $perms */
                $perms = $row->required_permissions ?? '[]';
                $this->tools[$row->name] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'description' => $row->description,
                    'parameters' => json_decode($params, true) ?: [],
                    'required_permissions' => json_decode($perms, true) ?: [],
                    'required_role' => $row->required_role,
                    'module' => $row->module,
                    'active' => (bool) $row->active,
                ];
            }
        } catch (\Throwable) {
            // Table may not exist yet during migrations
        }
    }
}
