<?php

declare(strict_types=1);

namespace App\AI;

class WriteToolPolicy
{
    /**
     * @return list<string>
     */
    public function writeTools(): array
    {
        /** @var list<string> $tools */
        $tools = config('ai.write_tools', []);

        return $tools;
    }

    public function requiresConfirmation(string $toolName): bool
    {
        return in_array($toolName, $this->writeTools(), true);
    }
}
