<?php

namespace MacropaySolutions\LaravelCrudWizard\Exceptions;

use Exception;

class CrudValidationException extends Exception
{
    protected $code = 400;
    protected $message = 'The given data was invalid.';
    private array $errorMessages = [];

    public function __construct(
        array $errors = [],
        string $message = 'The given data was invalid.',
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        $this->code = $code;
        $this->errorMessages = $errors;
        $this->message = $message;

        parent::__construct($message, $code, $previous);
    }

    public function errors(): array
    {
        return $this->errorMessages;
    }
}
