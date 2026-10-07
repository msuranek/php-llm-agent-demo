<?php

declare(strict_types=1);

namespace Demo\Tools;

use InvalidArgumentException;
use Throwable;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    /** @param iterable<Tool> $tools */
    public function __construct(iterable $tools, private bool $writesAllowed = false)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return array_values(array_map(
            static fn (Tool $tool): array => $tool->definition(),
            $this->tools,
        ));
    }

    /**
     * Tool errors become structured observations so the model can recover or explain the problem.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments): array
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            return ['ok' => false, 'error' => 'Unknown tool: ' . $name];
        }

        if ($tool->requiresWritePermission() && !$this->writesAllowed) {
            return [
                'ok' => false,
                'error' => 'Write permission denied. Ask the user to rerun with --allow-write.',
            ];
        }

        try {
            return ['ok' => true, 'data' => $tool->execute($arguments)];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'Tool execution failed unexpectedly.'];
        }
    }
}
