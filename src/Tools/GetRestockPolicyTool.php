<?php

declare(strict_types=1);

namespace Demo\Tools;

use Demo\Inventory\RestockPolicyRepository;
use InvalidArgumentException;

final class GetRestockPolicyTool implements Tool
{
    public function __construct(private RestockPolicyRepository $policies)
    {
    }

    public function name(): string
    {
        return 'get_restock_policy';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->name(),
            'description' => 'Get the replenishment policy for a product SKU, including lead time and safety stock.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'sku' => [
                        'type' => 'string',
                        'description' => 'Product SKU returned by find_product.',
                    ],
                ],
                'required' => ['sku'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function execute(array $arguments): array
    {
        $sku = strtoupper(trim((string) ($arguments['sku'] ?? '')));
        if ($sku === '') {
            throw new InvalidArgumentException('SKU cannot be empty.');
        }

        return [
            'sku' => $sku,
            'policy' => $this->policies->forSku($sku),
            'formula' => 'recommended_quantity = max(0, ceil(average_daily_sales * lead_time_days + safety_stock - current_stock))',
        ];
    }

    public function requiresWritePermission(): bool
    {
        return false;
    }
}
