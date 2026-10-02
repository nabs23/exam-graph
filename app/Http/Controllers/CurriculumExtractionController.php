<?php

namespace App\Http\Controllers;

use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\Http\Requests\PublishCurriculumExtractionRequest;
use App\Http\Requests\StoreCurriculumExtractionRequest;
use App\Models\CurriculumExtraction;
use App\Models\ProgramFile;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumExtractionController extends Controller
{
    public function store(
        StoreCurriculumExtractionRequest $request,
        ProgramFile $programFile,
        OfficialCurriculumExtractionService $service,
    ): RedirectResponse {
        abort_unless($service->isAvailable(), 404);
        abort_unless($programFile->embedding_status === FileEmbeddingStatus::Complete, 409);

        $extraction = $service->queue($programFile, $request->user());

        return to_route('curriculum-extractions.show', $extraction);
    }

    public function show(CurriculumExtraction $curriculumExtraction, OfficialCurriculumExtractionService $service): Response
    {
        $curriculumExtraction->load('programFile.program:id,name,code', 'requester:id,name', 'reviewer:id,name');
        $pages = collect();

        if (in_array($curriculumExtraction->status, [CurriculumExtractionStatus::Reviewing, CurriculumExtractionStatus::Published], true)) {
            $pages = $curriculumExtraction->programFile->pageEmbeddings()
                ->where('source_hash', $curriculumExtraction->source_hash)
                ->orderBy('page_number')
                ->get(['page_number', 'content'])
                ->map(fn ($page): array => [
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
