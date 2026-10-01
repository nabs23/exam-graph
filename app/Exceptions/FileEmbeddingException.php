<?php

namespace App\Exceptions;

use RuntimeException;

class FileEmbeddingException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        string $message,
    ) {
        parent::__construct($message);
    }
}
