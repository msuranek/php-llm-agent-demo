<?php

declare(strict_types=1);

namespace Demo\Inventory;

use RuntimeException;

final class RestockPolicyRepository
{
    public function __construct(private string $file)
    {
    }

    /** @return array<string, mixed> */
    public function forSku(string $sku): array
    {
        $contents = file_get_contents($this->file);
        if ($contents === false) {
            throw new RuntimeException('Could not read restock policies.');
        }

        /** @var mixed $policies */
        $policies = json_decode($contents, true);
        if (!is_array($policies) || !is_array($policies['default'] ?? null)) {
            throw new RuntimeException('Restock policies are not valid JSON.');
        }

        $override = $policies['overrides'][strtoupper($sku)] ?? [];
        if (!is_array($override)) {
            throw new RuntimeException('The product restock policy is not valid.');
        }

        return array_merge($policies['default'], $override);
    }
}
