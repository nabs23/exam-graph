<?php

namespace App\Http\Controllers;

use App\Models\ConceptProgress;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(): Response
    {
        $user = User::query()->firstOrFail();

        return Inertia::render('study/progress', ['progress' => ConceptProgress::query()->whereBelongsTo($user)->with('concept.subject')->latest('last_attempted_at')->get()]);
    }
}
