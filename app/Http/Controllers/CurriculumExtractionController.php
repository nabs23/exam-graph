<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\Http\Requests\PublishCurriculumExtractionRequest;
use App\Http\Requests\StoreCurriculumExtractionRequest;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\Program;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumExtractionController extends Controller
{
    public function store(
        StoreCurriculumExtractionRequest $request,
        Program $program,
        OfficialCurriculumExtractionService $service,
    ): RedirectResponse {
        abort_unless($service->isAvailable(), 404);
        $extraction = $service->queue($program, $request->user());

        return to_route('curriculum-extractions.show', $extraction);
    }

    public function show(CurriculumExtraction $curriculumExtraction, OfficialCurriculumExtractionService $service): Response
    {
        $curriculumExtraction->load('program:id,name,code', 'requester:id,name', 'reviewer:id,name');
        $pages = collect();

        if (in_array($curriculumExtraction->status, [CurriculumExtractionStatus::Reviewing, CurriculumExtractionStatus::Published], true)) {
            $sourceHashes = collect($curriculumExtraction->source_files)->pluck('content_hash', 'id');
            $pages = FilePageEmbedding::query()
                ->whereIn('program_file_id', $sourceHashes->keys())
                ->whereNotNull('content')
                ->orderBy('program_file_id')
                ->orderBy('page_number')
                ->get(['program_file_id', 'page_number', 'source_hash', 'content'])
                ->filter(fn (FilePageEmbedding $page): bool => $page->source_hash === $sourceHashes[$page->program_file_id])
                ->map(fn ($page): array => [
                    'source_file_id' => $page->program_file_id,
                    'page_number' => $page->page_number,
                    'text' => str($page->content)->squish()->limit(1200)->toString(),
                ]);
        }

        return Inertia::render('admin/curriculum-extractions/show', [
            'extraction' => $curriculumExtraction->makeHidden(['proposal']),
            'proposal' => $curriculumExtraction->proposal,
            'reviewedProposal' => $curriculumExtraction->reviewed_proposal,
            'sourcePages' => $pages,
            'canReview' => $curriculumExtraction->status === CurriculumExtractionStatus::Reviewing,
        ]);
    }

    public function publish(
        PublishCurriculumExtractionRequest $request,
        CurriculumExtraction $curriculumExtraction,
        OfficialCurriculumExtractionService $service,
    ): RedirectResponse {
        $service->publish($curriculumExtraction, $request->validated('subjects'), $request->user());

        return back()->with('success', 'The selected official curriculum was published.');
    }

    public function reject(CurriculumExtraction $curriculumExtraction, OfficialCurriculumExtractionService $service): RedirectResponse
    {
        $service->reject($curriculumExtraction, request()->user());

        return back()->with('success', 'The curriculum proposal was rejected.');
    }
}
