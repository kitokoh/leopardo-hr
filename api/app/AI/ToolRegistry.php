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

        // audit(securite) #6532 : les permissions requises (ai_tool_registry)
        // sont désormais évaluées contre les permissions accordées au rôle
        // (config ai.role_permissions) — un outil dont les permissions
        // dépassent le rôle n'est jamais exposé au LLM, en plus du rôle minimal.
        $granted = $this->permissionsForRole($role);

        return array_filter($this->tools, function (array $tool) use ($userLevel, $roleHierarchy, $granted): bool {
            if (! ($tool['active'] ?? true)) {
                return false;
            }
            $requiredLevel = $roleHierarchy[$tool['required_role'] ?? 'employee'] ?? 1;
            if ($userLevel < $requiredLevel) {
                return false;
            }
            foreach ($tool['required_permissions'] ?? [] as $permission) {
                if (! in_array($permission, $granted, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @return list<string>
     */
    private function permissionsForRole(string $role): array
    {
        /** @var mixed $configured */
        $configured = config('ai.role_permissions', []);
        $entries = is_array($configured) && is_array($configured[$role] ?? null) ? $configured[$role] : [];

        $result = [];
        foreach ($entries as $permission) {
            if (is_scalar($permission)) {
                $result[] = (string) $permission;
            }
        }

        return $result;
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
