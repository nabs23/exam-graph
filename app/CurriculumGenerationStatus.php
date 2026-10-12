<?php

namespace App;

enum CurriculumGenerationStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Reviewing = 'reviewing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Stale = 'stale';
    case Failed = 'failed';
}
