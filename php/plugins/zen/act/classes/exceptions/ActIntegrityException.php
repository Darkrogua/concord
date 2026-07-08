<?php namespace Zen\Act\Classes\Exceptions;

class ActIntegrityException extends \RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
