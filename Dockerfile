FROM php:8.3-cli-bookworm

WORKDIR /app

COPY . .

RUN php -r 'foreach (["curl", "json"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }'

CMD ["php", "bin/agent"]
