<?php

namespace App;

enum CurriculumGenerationKind: string
{
    case Subjects = 'subjects';
    case Topics = 'topics';
    case Concepts = 'concepts';
    case Lessons = 'lessons';
}
