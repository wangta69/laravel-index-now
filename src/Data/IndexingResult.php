<?php

declare(strict_types=1);

namespace Pondol\IndexNow\Data;

final readonly class IndexingResult
{
    /**
     * @param  array<int, string>  $urls
     */
    public function __construct(
        public string $engine,
        public bool $successful,
        public ?int $statusCode,
        public array $urls,
        public string $message = '',
        public mixed $rawResponse = null,
    ) {}

    /**
     * @param  array<int, string>  $urls
     */
    public static function success(
        string $engine,
        int $statusCode,
        array $urls,
        string $message = 'OK',
        mixed $rawResponse = null,
    ): self {
        return new self(
            engine: $engine,
            successful: true,
            statusCode: $statusCode,
            urls: $urls,
            message: $message,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<int, string>  $urls
     */
    public static function failure(
        string $engine,
        ?int $statusCode,
        array $urls,
        string $message,
        mixed $rawResponse = null,
    ): self {
        return new self(
            engine: $engine,
            successful: false,
            statusCode: $statusCode,
            urls: $urls,
            message: $message,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'engine' => $this->engine,
            'successful' => $this->successful,
            'status_code' => $this->statusCode,
            'urls' => $this->urls,
            'message' => $this->message,
        ];
    }
}
