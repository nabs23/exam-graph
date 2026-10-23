<?php

namespace App\Http\Controllers;

use App\FileEmbeddingStatus;
use App\FileUploadStatus;
use App\Http\Requests\StoreProgramFileUploadRequest;
use App\Http\Requests\UpdateProgramFileRequest;
use App\Jobs\DeleteProgramFile;
use App\Models\CurriculumExtraction;
use App\Models\CurriculumGeneration;
use App\Models\Program;
use App\Models\ProgramFile;
use App\Models\Subject;
use App\Models\SubjectTopicExtraction;
use App\Services\FileEmbeddingService;
use App\Services\SourceFileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProgramFileController extends Controller
{
    public function index(Request $request, Program $program): Response
    {
        $filter = (string) $request->query('subject', 'all');
        abort_unless($filter === 'all' || $filter === 'shared' || (ctype_digit($filter) && $program->subjects()->whereKey((int) $filter)->exists()), 404);

        $files = $program->files()->with(['uploader:id,name', 'subject:id,name'])->latest();
        if ($filter === 'shared') {
            $files->whereNull('subject_id');
        } elseif ($filter !== 'all') {
            $files->where('subject_id', (int) $filter);
        }

        return Inertia::render('admin/program-files/index', [
            'program' => $program,
            'files' => $files->get(),
            'subjects' => $program->subjects()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'subjectFilter' => $filter,
        ]);
    }

    public function uploadUrl(StoreProgramFileUploadRequest $request, Program $program, SourceFileStorage $storage): JsonResponse
    {
        abort_if($program->files()->where('uploaded_by', $request->user()->id)->where('upload_status', FileUploadStatus::PendingUpload)->count() >= 5, 429);
        $data = $request->validated();
        $key = $storage->createKey('program-files', (string) $program->id, $data['extension']);
        $file = $program->files()->create([
            'subject_id' => $data['subject_id'] ?? null, 'file_type' => $data['file_type'],
            'title' => $data['title'], 'original_filename' => basename($data['original_filename']),
            'storage_key' => $key, 'mime_type' => $data['mime_type'], 'file_size' => $data['file_size'], 'metadata' => $data['metadata'] ?? [],
            'uploaded_by' => $request->user()->id,
        ]);

        try {
            $presigned = $storage->presign($key, $data['mime_type']);
        } catch (\Throwable $exception) {
            $file->delete();
            throw $exception;
        }

        return response()->json(['file_id' => $file->id, 'upload_url' => $presigned['url'], 'required_headers' => $presigned['headers'], 'expires_at' => now()->addMinutes(5)->toISOString()]);
    }

    public function store(ProgramFile $programFile): JsonResponse
    {
        abort_unless($programFile->upload_status === FileUploadStatus::PendingUpload, 409);
        abort_unless(str_starts_with($programFile->storage_key, 'program-files/'.$programFile->program_id.'/'), 404);
        $disk = Storage::disk($programFile->storage_disk);
        $size = $disk->size($programFile->storage_key);
        $mime = $disk->mimeType($programFile->storage_key);

        if ($size === false || $size < 1 || $size > 104857600 || $size !== $programFile->file_size || $mime !== $programFile->mime_type) {
            $disk->delete($programFile->storage_key);
            app(SourceFileStorage::class)->markFailed($programFile);
            abort(422, 'The uploaded object did not match its declared size and content type.');
        }

        $embeddingStatus = FileEmbeddingStatus::forMimeType($mime);

        $programFile->forceFill([
            'upload_status' => FileUploadStatus::Uploaded,
            'file_size' => $size,
            'mime_type' => $mime,
            'uploaded_at' => now(),
            'embedding_status' => $embeddingStatus,
            'embedding_error_code' => $embeddingStatus === FileEmbeddingStatus::Unsupported ? 'unsupported_file_type' : null,
        ])->save();

        return response()->json($programFile->load('uploader:id,name')->makeHidden(['storage_key', 'storage_disk']));
    }

    public function show(ProgramFile $programFile, FileEmbeddingService $fileEmbeddingService): Response
    {
        $embeddingPreviews = collect();
        $embeddingDataBytes = null;
        $embeddingPageCount = null;
        $embeddingUnavailableReason = $fileEmbeddingService->unavailableReason();
        $embeddingsAvailable = $embeddingUnavailableReason === null;

        if ($programFile->embedding_status === FileEmbeddingStatus::Complete
            && Schema::hasTable('file_page_embeddings')) {
            $embeddingPreviews = $programFile->pageEmbeddings()
                ->orderBy('page_number')
                ->limit(5)
                ->get(['page_number', 'model', 'dimensions', 'content', 'embedding'])
                ->map(fn ($embedding): array => [
                    'page_number' => $embedding->page_number,
                    'model' => $embedding->model,
                    'dimensions' => $embedding->dimensions,
                    'excerpt' => str($embedding->content ?? '')->squish()->limit(180)->toString(),
                    'values' => array_slice($embedding->embedding ?? [], 0, 8),
                ]);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $embeddingSummary = $programFile->pageEmbeddings()
                    ->selectRaw('count(*) as embedding_page_count, coalesce(sum(pg_column_size(embedding)), 0) as embedding_data_bytes')
                    ->first();
                $embeddingDataBytes = (int) $embeddingSummary->embedding_data_bytes;
                $embeddingPageCount = (int) $embeddingSummary->embedding_page_count;
            }
        }

        return Inertia::render('admin/program-files/show', [
            'file' => $programFile->load('uploader:id,name', 'program:id,name,code', 'subject:id,name')->makeHidden(['storage_key', 'storage_disk']),
            'subjects' => Subject::query()->where('program_id', $programFile->program_id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'fileEmbeddingsEnabled' => $embeddingsAvailable,
            'embeddingUnavailableReason' => $embeddingUnavailableReason,
            'embeddingPreviews' => $embeddingPreviews,
            'embeddingDataBytes' => $embeddingDataBytes,
            'embeddingPageCount' => $embeddingPageCount,
        ]);
    }

    public function update(UpdateProgramFileRequest $request, ProgramFile $programFile): RedirectResponse
    {
        $programFile->update($request->validated());

        return back();
    }

    public function download(ProgramFile $programFile): JsonResponse
    {
        abort_unless($programFile->upload_status === FileUploadStatus::Uploaded, 409);
        $filename = Str::slug(pathinfo($programFile->original_filename, PATHINFO_FILENAME)).'.'.Str::lower(pathinfo($programFile->original_filename, PATHINFO_EXTENSION));
        $url = Storage::disk($programFile->storage_disk)->temporaryUrl($programFile->storage_key, now()->addMinutes(5), ['ResponseContentDisposition' => 'attachment; filename="'.$filename.'"', 'ResponseContentType' => $programFile->mime_type]);

        return response()->json(['url' => $url]);
    }

    public function embed(ProgramFile $programFile, FileEmbeddingService $fileEmbeddingService): RedirectResponse
    {
        abort_unless($fileEmbeddingService->isAvailable(), 404);
        abort_unless($programFile->upload_status === FileUploadStatus::Uploaded, 409);
        $fileEmbeddingService->queue($programFile);

        return back();
    }

    public function destroy(Request $request, ProgramFile $programFile): RedirectResponse
    {
        $deletionBlockedReason = DB::transaction(function () use ($programFile): ?string {
            $lockedFile = ProgramFile::query()->lockForUpdate()->findOrFail($programFile->id);
            abort_unless(in_array($lockedFile->upload_status, [FileUploadStatus::Uploaded, FileUploadStatus::Failed, FileUploadStatus::DeletePending], true), 409);

            if ($this->hasHistoricalReferences($lockedFile)) {
                return 'This source file is referenced by curriculum history and must remain available.';
            }

            $lockedFile->forceFill(['upload_status' => FileUploadStatus::DeletePending])->save();
            DeleteProgramFile::dispatch($lockedFile->id)->afterCommit();

            return null;
        });

        if ($deletionBlockedReason !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $deletionBlockedReason]);

            return $this->deletionRedirect($request, $programFile);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'File deletion has started.']);

        return $this->deletionRedirect($request, $programFile);
    }

    private function deletionRedirect(Request $request, ProgramFile $programFile): RedirectResponse
    {
        if ($request->query('tab') === 'files') {
            return to_route('programs.show', [
                'program' => $programFile->program_id,
                'tab' => 'files',
            ]);
        }

        $subjectFilter = $request->query('subject');
        $isValidSubjectFilter = $subjectFilter === 'all'
            || $subjectFilter === 'shared'
            || (is_string($subjectFilter)
                && ctype_digit($subjectFilter)
                && $programFile->program->subjects()->whereKey((int) $subjectFilter)->exists());

        if ($isValidSubjectFilter) {
            return to_route('programs.files.index', [
                'program' => $programFile->program_id,
                'subject' => $subjectFilter,
            ]);
        }

        return to_route('programs.files.index', $programFile->program_id);
    }

    private function hasHistoricalReferences(ProgramFile $programFile): bool
    {
        if (CurriculumExtraction::query()->where('program_file_id', $programFile->id)->exists()
            || CurriculumGeneration::query()
                ->where('program_id', $programFile->program_id)
                ->whereJsonContains('source_files', [['id' => $programFile->id]])
                ->exists()
            || DB::table('syllabus_topic_sources')->where('program_file_id', $programFile->id)->exists()) {
            return true;
        }

        if (CurriculumExtraction::query()
            ->where('program_id', $programFile->program_id)
            ->whereJsonContains('source_files', [['id' => $programFile->id]])
            ->exists()) {
            return true;
        }

        $legacyFileIds = DB::table('legacy_program_file_sources')
            ->where('program_file_id', $programFile->id)
            ->where('legacy_type', 'subject_file')
            ->pluck('legacy_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $candidateSourceIds = array_values(array_unique([$programFile->id, ...$legacyFileIds]));
        $historicalExtractions = SubjectTopicExtraction::query()
            ->whereHas('subject', fn ($query) => $query->where('program_id', $programFile->program_id))
            ->where(function ($query) use ($candidateSourceIds): void {
                foreach ($candidateSourceIds as $index => $sourceId) {
                    if ($index === 0) {
                        $query->whereJsonContains('source_files', [['id' => $sourceId]]);
                    } else {
                        $query->orWhereJsonContains('source_files', [['id' => $sourceId]]);
                    }
                }
            })
            ->get(['source_files']);

        foreach ($historicalExtractions as $extraction) {
            foreach ($extraction->source_files ?? [] as $source) {
                $sourceId = (int) ($source['id'] ?? 0);
                if (($source['source_type'] ?? null) === 'program_file' && $sourceId === $programFile->id) {
                    return true;
                }

                if (($source['source_type'] ?? null) !== 'program_file' && in_array($sourceId, $legacyFileIds, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
