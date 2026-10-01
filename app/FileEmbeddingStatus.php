<?php

namespace App;

enum FileEmbeddingStatus: string
{
    case NotRequested = 'not_requested';
    case Supported = 'supported';
    case Queued = 'queued';
    case Processing = 'processing';
    case Complete = 'complete';
    case Unsupported = 'unsupported';
    case Failed = 'failed';

    public static function forMimeType(?string $mimeType): self
    {
        return $mimeType === 'application/pdf' ? self::Supported : self::Unsupported;
    }
}
