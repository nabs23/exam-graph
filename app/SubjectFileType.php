<?php

namespace App;

enum SubjectFileType: string
{
    case ReviewerEbook = 'reviewer_ebook';
    case ReviewerNotes = 'reviewer_notes';
    case LectureMaterial = 'lecture_material';
    case OfficialReference = 'official_reference';
    case PracticeMaterial = 'practice_material';
    case Other = 'other';
}
