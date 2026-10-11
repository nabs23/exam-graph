<?php

namespace App;

enum CurriculumGenerationError: string
{
    case SourceTextUnavailable = 'source_text_unavailable';
    case SourceSnapshotUnavailable = 'source_snapshot_unavailable';
    case SourceChanged = 'source_changed';
    case SourceUnavailable = 'source_unavailable';
    case SourceLimitExceeded = 'source_limit_exceeded';
    case InvalidProposal = 'invalid_proposal';
    case ProviderFailed = 'provider_failed';

    public function message(): string
    {
        return match ($this) {
            self::SourceTextUnavailable => 'The selected files do not contain readable text. Choose ungrounded mode explicitly or select files with extractable text.',
            self::SourceSnapshotUnavailable => 'The selected source snapshot is unavailable. Start a new proposal.',
            self::SourceChanged => 'A selected source changed after the proposal was requested. Start a new proposal.',
            self::SourceUnavailable => 'A selected source could not be read. Check the file and try again.',
            self::SourceLimitExceeded => 'The selected sources exceed the configured page or text limits.',
            self::InvalidProposal => 'The generated proposal could not be safely reviewed.',
            self::ProviderFailed => 'The curriculum proposal could not be generated. Try again later.',
        };
    }
}
