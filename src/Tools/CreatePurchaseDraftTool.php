<?php

declare(strict_types=1);

namespace Demo\Tools;

use Demo\Inventory\InventoryRepository;
use Demo\Inventory\RestockCalculator;
use Demo\Inventory\RestockPolicyRepository;
use InvalidArgumentException;
use RuntimeException;

final class CreatePurchaseDraftTool implements Tool
{
    public function __construct(
        private InventoryRepository $products,
        private RestockPolicyRepository $policies,
        private RestockCalculator $calculator,
        private string $directory,
    ) {
    }

    public function name(): string
    {
        return 'create_purchase_draft';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => $this->name(),
            'description' => 'Create a local purchase-order draft for human review. This does not place an order and requires write permission.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'sku' => ['type' => 'string', 'description' => 'Product SKU.'],
                    'reason' => ['type' => 'string', 'description' => 'Short explanation based on verified inventory and policy data.'],
                ],
                'required' => ['sku', 'reason'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function execute(array $arguments): array
    {
        $sku = strtoupper(trim((string) ($arguments['sku'] ?? '')));
        $reason = trim((string) ($arguments['reason'] ?? ''));
        if ($sku === '' || $reason === '') {
            throw new InvalidArgumentException('SKU and a reason are required.');
        }

        $product = $this->products->findBySku($sku);
        if ($product === null) {
            throw new InvalidArgumentException('Cannot create a purchase draft for an unknown SKU.');
        }

        $policy = $this->policies->forSku($sku);
        $calculation = $this->calculator->calculate(
            $this->nonNegativeInteger($product, 'current_stock'),
            $this->nonNegativeNumber($product, 'average_daily_sales'),
            $this->nonNegativeInteger($policy, 'lead_time_days'),
            $this->nonNegativeInteger($policy, 'safety_stock'),
        );
        if (!$calculation['restock_needed']) {
            throw new InvalidArgumentException('Current inventory does not require a purchase draft.');
        }

        $quantity = $calculation['recommended_quantity'];

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create the purchase draft directory.');
        }

        $safeSku = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $sku));
        $path = $this->directory . '/' . date('Ymd-His') . '-' . trim($safeSku, '-') . '-' . bin2hex(random_bytes(3)) . '.json';
        $payload = [
            'status' => 'draft',
            'sku' => $sku,
            'quantity' => $quantity,
            'reason' => $reason,
            'calculation' => $calculation,
            'created_at' => date(DATE_ATOM),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException('Could not save the purchase draft.');
        }

        return [
            'created' => true,
            'status' => 'draft',
            'sku' => $sku,
            'quantity' => $quantity,
            'path' => $path,
            'message' => 'Draft created for human review; no order was placed.',
        ];
    }

    public function requiresWritePermission(): bool
    {
        return true;
    }

    /** @param array<string, mixed> $data */
    private function nonNegativeInteger(array $data, string $name): int
    {
        $value = $data[$name] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new RuntimeException($name . ' must be a non-negative integer.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nonNegativeNumber(array $data, string $name): float
    {
        $value = $data[$name] ?? null;
        if ((!is_int($value) && !is_float($value)) || $value < 0) {
            throw new RuntimeException($name . ' must be a non-negative number.');
        }
        return (float) $value;
    }
}
