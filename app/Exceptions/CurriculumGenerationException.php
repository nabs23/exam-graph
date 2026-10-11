<?php

namespace App\Exceptions;

use Exception;

class CurriculumGenerationException extends Exception
{
    public function __construct(public string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
