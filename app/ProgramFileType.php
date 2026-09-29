<?php

namespace App;

enum ProgramFileType: string
{
    case ExamSpecification = 'exam_specification';
    case OfficialSyllabus = 'official_syllabus';
    case BoardResolution = 'board_resolution';
    case AmendmentOrClarification = 'amendment_or_clarification';
    case ProgramReference = 'program_reference';
    case Other = 'other';
}
