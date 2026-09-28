<?php

namespace App\Http\Controllers;

use App\Models\ConceptProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('study/progress', ['progress' => ConceptProgress::query()->whereBelongsTo($request->user())->with('concept.subject')->latest('last_attempted_at')->get()]);
    }
}
