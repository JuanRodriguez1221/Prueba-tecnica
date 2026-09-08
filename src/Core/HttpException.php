<?php

declare(strict_types=1);

namespace VotingSystem\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
