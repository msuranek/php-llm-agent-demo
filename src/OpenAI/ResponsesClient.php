<?php

declare(strict_types=1);

namespace Demo\OpenAI;

use RuntimeException;

final class ResponsesClient implements ResponsesGateway
{
    public function __construct(
        private string $apiKey,
        private string $endpoint = 'https://api.openai.com/v1/responses',
        private int $timeoutSeconds = 90,
    ) {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY is missing. Copy .env.example to .env and add your key.');
        }
    }

    public function create(array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $curl = curl_init($this->endpoint);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('OpenAI request failed: ' . $error);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid JSON (HTTP ' . $status . ').');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? 'Unknown API error';
            throw new RuntimeException('OpenAI API error (HTTP ' . $status . '): ' . $message);
        }

        return $decoded;
    }
}
