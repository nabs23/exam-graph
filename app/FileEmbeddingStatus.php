<?php

namespace App;

enum FileEmbeddingStatus: string
{
    case NotRequested = 'not_requested';
    case Queued = 'queued';
    case Processing = 'processing';
    case Complete = 'complete';
    case Unsupported = 'unsupported';
    case Failed = 'failed';
}
