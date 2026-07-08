<?php namespace Zen\Chub\Exceptions;

use RuntimeException;
use Throwable;

class AllowedDtoException extends RuntimeException
{
    private array $context = [];

    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function context(): array
    {
        return $this->context;
    }
}
