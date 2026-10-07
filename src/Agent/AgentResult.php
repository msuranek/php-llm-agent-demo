<?php

declare(strict_types=1);

namespace Demo\Agent;

final class AgentResult
{
    /** @param list<array<string, mixed>> $trace */
    public function __construct(
        public string $answer,
        public int $rounds,
        public array $trace,
    ) {
    }
}
