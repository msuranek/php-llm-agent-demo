<?php

declare(strict_types=1);

namespace Demo\Inventory;

use InvalidArgumentException;

final class RestockCalculator
{
    /** @return array{current_stock: int, target_stock: int, recommended_quantity: int, restock_needed: bool} */
    public function calculate(
        int $currentStock,
        float $averageDailySales,
        int $leadTimeDays,
        int $safetyStock,
    ): array {
        if ($currentStock < 0 || $averageDailySales < 0 || $leadTimeDays < 0 || $safetyStock < 0) {
            throw new InvalidArgumentException('Restock calculation values must be non-negative.');
        }

        $targetStock = (int) ceil($averageDailySales * $leadTimeDays + $safetyStock);
        $recommendedQuantity = max(0, $targetStock - $currentStock);

        return [
            'current_stock' => $currentStock,
            'target_stock' => $targetStock,
            'recommended_quantity' => $recommendedQuantity,
            'restock_needed' => $recommendedQuantity > 0,
        ];
    }
}
