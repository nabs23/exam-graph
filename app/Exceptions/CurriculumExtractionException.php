<?php

namespace App\Exceptions;

use App\CurriculumExtractionError;
use RuntimeException;
use Throwable;

class CurriculumExtractionException extends RuntimeException
{
    public function __construct(public readonly CurriculumExtractionError $failure, ?Throwable $previous = null)
    {
        parent::__construct($failure->message(), previous: $previous);
    }
}
