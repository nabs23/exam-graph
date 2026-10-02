<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\SyllabusTopic;

abstract class Controller
{
    protected function ensureConceptContext(?SyllabusTopic $topic, Concept $concept): void
    {
        abort_if($topic === null && $concept->syllabus_topic_id !== null, 404);
    }
}
