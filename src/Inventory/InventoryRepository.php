<?php

declare(strict_types=1);

namespace Demo\Inventory;

use RuntimeException;

final class InventoryRepository
{
    public function __construct(private string $file)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query): array
    {
        $needle = strtolower($query);
        $matches = [];

        foreach ($this->all() as $product) {
            $sku = strtolower((string) ($product['sku'] ?? ''));
            $name = strtolower((string) ($product['name'] ?? ''));
            if ($sku === $needle || str_contains($name, $needle)) {
                $matches[] = $product;
            }
        }

        return $matches;
    }

    /** @return array<string, mixed>|null */
    public function findBySku(string $sku): ?array
    {
        $needle = strtoupper($sku);
        foreach ($this->all() as $product) {
            if (strtoupper((string) ($product['sku'] ?? '')) === $needle) {
                return $product;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function all(): array
    {
        $contents = file_get_contents($this->file);
        if ($contents === false) {
            throw new RuntimeException('Could not read the inventory.');
        }

        /** @var mixed $inventory */
        $inventory = json_decode($contents, true);
        if (!is_array($inventory)) {
            throw new RuntimeException('Inventory is not valid JSON.');
        }

        return array_values(array_filter($inventory, 'is_array'));
    }
}
