<?php

declare(strict_types=1);

use Demo\Agent\Agent;
use Demo\Inventory\InventoryRepository;
use Demo\Inventory\RestockCalculator;
use Demo\Inventory\RestockPolicyRepository;
use Demo\OpenAI\ResponsesGateway;
use Demo\Tools\CalculateRestockTool;
use Demo\Tools\CreatePurchaseDraftTool;
use Demo\Tools\FindProductTool;
use Demo\Tools\GetRestockPolicyTool;
use Demo\Tools\ToolRegistry;

require dirname(__DIR__) . '/src/bootstrap.php';

final class FakeResponsesGateway implements ResponsesGateway
{
    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses)
    {
    }

    public function create(array $payload): array
    {
        if ($this->responses === []) {
            throw new RuntimeException('No fake response left.');
        }
        return array_shift($this->responses);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$root = dirname(__DIR__);
$products = new InventoryRepository($root . '/data/inventory.json');
$policies = new RestockPolicyRepository($root . '/data/restock-policies.json');
$restockCalculator = new RestockCalculator();

$findProduct = new FindProductTool($products);
$productResult = $findProduct->execute(['query' => 'USB-HUB-01']);
assertSameValue(1, $productResult['count'], 'Product search should find an exact SKU.');
assertSameValue(8, $productResult['products'][0]['current_stock'], 'Product search should return current stock.');

$getPolicy = new GetRestockPolicyTool($policies);
$policyResult = $getPolicy->execute(['sku' => 'USB-HUB-01']);
assertSameValue(5, $policyResult['policy']['lead_time_days'], 'Default lead time should be returned.');
assertSameValue(10, $policyResult['policy']['safety_stock'], 'Default safety stock should be returned.');

$calculate = new CalculateRestockTool($restockCalculator);
$calculation = $calculate->execute([
    'current_stock' => 8,
    'average_daily_sales' => 3,
    'lead_time_days' => 5,
    'safety_stock' => 10,
]);
assertSameValue(17, $calculation['recommended_quantity'], 'Restock calculation should recommend 17 units.');
assertSameValue(true, $calculation['restock_needed'], 'Low inventory should require restocking.');

$sufficientStock = $calculate->execute([
    'current_stock' => 42,
    'average_daily_sales' => 2.5,
    'lead_time_days' => 5,
    'safety_stock' => 10,
]);
assertSameValue(0, $sufficientStock['recommended_quantity'], 'Sufficient stock should not produce a purchase quantity.');
assertSameValue(false, $sufficientStock['restock_needed'], 'Sufficient stock should not require restocking.');

$registry = new ToolRegistry([$calculate], false);
$fake = new FakeResponsesGateway([
    [
        'output' => [[
            'type' => 'function_call',
            'call_id' => 'call_test',
            'name' => 'calculate_restock',
            'arguments' => '{"current_stock":8,"average_daily_sales":3,"lead_time_days":5,"safety_stock":10}',
        ]],
    ],
    [
        'output' => [[
            'type' => 'message',
            'content' => [['type' => 'output_text', 'text' => 'Restock 17 units.']],
        ]],
    ],
]);
$result = (new Agent($fake, $registry, 'fake-model'))->run('Check whether USB-HUB-01 needs restocking.');
assertSameValue('Restock 17 units.', $result->answer, 'Agent should return final model text.');
assertSameValue('calculate_restock', $result->trace[0]['tool'], 'Agent should execute the requested tool.');
assertSameValue(17, $result->trace[0]['observation']['data']['recommended_quantity'], 'Agent should feed the tool result into the loop.');

$temporaryDirectory = sys_get_temp_dir() . '/llm-agent-demo-test-' . bin2hex(random_bytes(4));
$draftTool = new CreatePurchaseDraftTool($products, $policies, $restockCalculator, $temporaryDirectory);
$denied = new ToolRegistry([$draftTool], false);
$deniedResult = $denied->execute('create_purchase_draft', [
    'sku' => 'USB-HUB-01',
    'reason' => 'Stock is below the calculated target.',
]);
assertSameValue(false, $deniedResult['ok'], 'Purchase draft must be denied without write permission.');
assertSameValue(false, is_dir($temporaryDirectory), 'Denied write must not create a directory.');

$allowed = new ToolRegistry([$draftTool], true);
$createdResult = $allowed->execute('create_purchase_draft', [
    'sku' => 'USB-HUB-01',
    'reason' => 'Stock is below the calculated target.',
]);
assertSameValue(true, $createdResult['ok'], 'Purchase draft should be created with write permission.');
assertSameValue('draft', $createdResult['data']['status'], 'Created purchase document must remain a draft.');
assertSameValue(17, $createdResult['data']['quantity'], 'Purchase draft quantity must be calculated by the application.');
$createdPath = $createdResult['data']['path'];
assertSameValue(true, is_string($createdPath) && is_file($createdPath), 'Purchase draft file should exist.');
if (is_string($createdPath)) {
    $createdContents = file_get_contents($createdPath);
    assertSameValue(true, is_string($createdContents) && str_ends_with($createdContents, PHP_EOL), 'Purchase draft JSON should end with a newline.');
}

if (is_string($createdPath)) {
    unlink($createdPath);
}
rmdir($temporaryDirectory);

$unknownProduct = $allowed->execute('create_purchase_draft', [
    'sku' => 'UNKNOWN-01',
    'reason' => 'This SKU does not exist.',
]);
assertSameValue(false, $unknownProduct['ok'], 'Unknown products must not produce purchase drafts.');

$sufficientInventory = $allowed->execute('create_purchase_draft', [
    'sku' => 'MOUSE-WL-02',
    'reason' => 'The model requested a draft despite sufficient stock.',
]);
assertSameValue(false, $sufficientInventory['ok'], 'Sufficient inventory must not produce purchase drafts.');

echo "All tests passed.\n";
