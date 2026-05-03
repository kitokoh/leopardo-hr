<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Console;

use Symfony\Component\Console\Command\Command;

/**
 * Patched stub for PHPStan analysis only.
 * Fixes PHP 8.4 incompatible declaration: configure() must declare `: void`
 * to be compatible with Symfony\Component\Console\Command::configure(): void
 *
 * @see https://github.com/laravel/framework/issues/DocsCommand
 */
class DocsCommand extends Command
{
    protected function configure(): void
    {
        // stub — overrides vendor class for static analysis only
    }
}
