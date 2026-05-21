<?php

namespace App\Contracts\Communication;

use App\Models\Employee;

interface MessageProviderInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Employee $employee, string $title, string $body, array $metadata = []): string;
}
