<?php

declare(strict_types=1);

namespace Demo\Tools;

use Demo\Inventory\RestockCalculator;
use InvalidArgumentException;

final class CalculateRestockTool implements Tool
{
    public function __construct(private RestockCalculator $calculator)
    {
    }

    public function name(): string
    {
        return 'calculate_restock';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->name(),
            'description' => 'Calculate a replenishment quantity from verified inventory data and policy values. Do not calculate this mentally.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'current_stock' => ['type' => 'integer', 'minimum' => 0],
                    'average_daily_sales' => ['type' => 'number', 'minimum' => 0],
                    'lead_time_days' => ['type' => 'integer', 'minimum' => 0],
                    'safety_stock' => ['type' => 'integer', 'minimum' => 0],
                ],
                'required' => ['current_stock', 'average_daily_sales', 'lead_time_days', 'safety_stock'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function execute(array $arguments): array
    {
        $currentStock = $this->nonNegativeInteger($arguments, 'current_stock');
        $averageDailySales = $this->nonNegativeNumber($arguments, 'average_daily_sales');
        $leadTimeDays = $this->nonNegativeInteger($arguments, 'lead_time_days');
        $safetyStock = $this->nonNegativeInteger($arguments, 'safety_stock');

        return $this->calculator->calculate($currentStock, $averageDailySales, $leadTimeDays, $safetyStock);
    }

    public function requiresWritePermission(): bool
    {
        return false;
    }

    /** @param array<string, mixed> $arguments */
    private function nonNegativeInteger(array $arguments, string $name): int
    {
        $value = $arguments[$name] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException($name . ' must be a non-negative integer.');
        }
        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function nonNegativeNumber(array $arguments, string $name): float
    {
        $value = $arguments[$name] ?? null;
        if ((!is_int($value) && !is_float($value)) || $value < 0) {
            throw new InvalidArgumentException($name . ' must be a non-negative number.');
        }
        return (float) $value;
    }
}
