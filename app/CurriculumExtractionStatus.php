<?php

namespace App;

enum CurriculumExtractionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Reviewing = 'reviewing';
    case Published = 'published';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
