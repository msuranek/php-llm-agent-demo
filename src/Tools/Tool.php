<?php

declare(strict_types=1);

namespace Demo\Tools;

interface Tool
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function definition(): array;

    /** @param array<string, mixed> $arguments */
    public function execute(array $arguments): mixed;

    public function requiresWritePermission(): bool;
}
