<?php

declare(strict_types=1);

namespace Demo\OpenAI;

interface ResponsesGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array;
}
