<?php

namespace App;

enum SubjectTopicExtractionError: string
{
    case SourceChanged = 'source_changed';
    case SourceUnavailable = 'source_unavailable';
    case InvalidProposal = 'invalid_proposal';
    case NoTopicsFound = 'no_topics_found';
    case ProviderFailed = 'provider_failed';
    case ExtractionFailed = 'extraction_failed';

    public function message(): string
    {
        return match ($this) {
            self::SourceChanged => 'A selected source file changed. Start a new extraction with the current files.',
            self::SourceUnavailable => 'A selected source file is no longer available for extraction.',
            self::InvalidProposal => 'The generated topic hierarchy or its source citations could not be verified.',
            self::NoTopicsFound => 'No source-supported syllabus topics were found in the selected files.',
            self::ProviderFailed => 'The extraction provider could not complete the request. Try again later.',
            self::ExtractionFailed => 'The extraction could not be processed. Ask an administrator to check the server logs.',
        };
    }
}
