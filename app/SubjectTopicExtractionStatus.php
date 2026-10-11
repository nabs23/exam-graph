<?php

namespace App;

enum SubjectTopicExtractionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Reviewing = 'reviewing';
    case Published = 'published';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
